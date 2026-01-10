<?php

/*
Version:     2.13
Date:        10/01/26
Name:        DeckManager.php
Purpose:     Class for quickAdd and deck import.
Notes:       ProcessInput() called with deck number and input string; quickAdd() interprets and adds cards.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\Message;
use MTG\Core\MyPHPMailer;

class DeckManager
{
    /**
    * @var mysqli
    */
    private $db;
    private $logfile;
    private $batchedCardIds = []; // Array to store batched cards to add
    private $message;
    private $userEmail;
    private $serverEmail;
    private $importLinestoIgnore;
    private $nonPreferredSetCodes;
    private $anyQuantity = [];
    private $limitWarnings = [];

    public function __construct(
        $db,
        $logfile,
        $userEmail,
        $serverEmail,
        $importLinestoIgnore,
        $nonPreferredSetCodes,
        $anyQuantity
    ) {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
        $this->userEmail = $userEmail;
        $this->serverEmail = $serverEmail;
        $this->importLinestoIgnore = $importLinestoIgnore;
        $this->nonPreferredSetCodes = $nonPreferredSetCodes;
        $this->anyQuantity = is_array($anyQuantity) ? $anyQuantity : [];
    }

    public function processInput($deckNumber, $input)
    {
        // processInput can handle either single-line or multi-line 'add card' inputs using quickadd.
        // Multi-line inputs are batched for combined data write by addDeckCardsBatch; called from deckdetail.php.

        $this->message->logMessage(
            '[DEBUG]',
            "ProcessInput called for deck $deckNumber with '$input'"
        );
        // Check if input is multiline
        $lines = explode("\n", $input);
        $inputType = '';
        $qtyLines = count($lines);
        if ($qtyLines > 1) :
            $this->message->logMessage(
                '[DEBUG]',
                "Multi-line input ($qtyLines lines), calling quickadd in batch mode"
            );
            $row = 1;
            $sideboardTrigger = false;
            $commanderTrigger = false;
            $partnerTrigger = false;
            $warningSummary = '';
            $warningHeading = 'Warning type, Row number, Input line';
            foreach ($lines as $line) :
                $line = trim($line);
                $start = substr($line, 0, 8);
                if (strpos($start, 'setcode') !== false || strpos($start, 'Edition') !== false) :
                    $this->message->logMessage('[DEBUG]', "Row $row: Header row: '$line'");
                elseif (stripos($line, 'Deckname:') === 0) :
                    $this->message->logMessage('[DEBUG]', "Row $row: Deckname header");
                elseif ($line === 'Commander') :
                    $commanderTrigger = true;
                    $partnerTrigger = false;
                    $this->message->logMessage('[DEBUG]', "Row $row: Commander header");
                elseif ($line === 'Partner/Background') :
                    $partnerTrigger = true;
                    $commanderTrigger = false;
                    $this->message->logMessage('[DEBUG]', "Row $row: Partner/Background header");
                elseif (trim($line) === '' || inArrayCaseInsensitive(trim($line), $this->importLinestoIgnore)) :
                    if (trim($line) === 'Sideboard') :
                        $this->message->logMessage('[DEBUG]', "Row $row: Sideboard header");
                        $sideboardTrigger = true;
                        $commanderTrigger = false;
                        $partnerTrigger = false;
                    elseif (trim($line) === '' || inArrayCaseInsensitive(trim($line), $this->importLinestoIgnore)) :
                        $this->message->logMessage('[DEBUG]', "Row $row: Empty row");
                        if ($commanderTrigger || $partnerTrigger) :
                            $this->message->logMessage('[DEBUG]', "Row $row: Resetting commander mode");
                            $commanderTrigger = false;
                            $partnerTrigger = false;
                        endif;
                    endif;
                else :
                    $this->message->logMessage('[DEBUG]', "Row $row: Data row: '$line'");
                    $commanderMode = null;
                    if ($commanderTrigger) :
                        $commanderMode = 'commander';
                        $this->message->logMessage('[DEBUG]', "Row $row: Commander mode enabled");
                    elseif ($partnerTrigger) :
                        $commanderMode = 'partner';
                        $this->message->logMessage('[DEBUG]', "Row $row: Partner mode enabled");
                    endif;
                    // Set last parameter to true for batching
                    $quickAddResult = $this->quickAdd(
                        $deckNumber,
                        $line,
                        $sideboardTrigger,
                        true,
                        $commanderMode,
                        $row,
                        $line
                    );
                    if ($quickAddResult === false || $quickAddResult === 'cardnotfound') :
                        $this->message->logMessage('[DEBUG]', "Row $row: Result: fail");
                        $newWarning = "ERROR - Row $row, Line: '$line'" . "\n";
                        $warningSummary = $warningSummary . $newWarning;
                    else :
                        $this->message->logMessage('[DEBUG]', "Row $row: Result: success");
                    endif;
                endif;
                $row = $row + 1;
            endforeach;
        else :
            $this->message->logMessage(
                '[DEBUG]',
                "Single-line input, calling quickadd in single-line mode"
            );
            $inputType = 'SingleText';
            $quickAddResult = $this->quickAdd($deckNumber, $input);
            $this->message->logMessage('[DEBUG]', "Result: $quickAddResult");
            return $quickAddResult;
        endif;
        // If batched card array is not empty, perform batch insert
        if (!empty($this->batchedCardIds)) :
            $this->addDeckCardsBatch($deckNumber, $this->batchedCardIds);
            // Clear array after batch insert
            $this->batchedCardIds = [];
        endif;
        if (!empty($this->limitWarnings)) :
            foreach ($this->limitWarnings as $limitWarning) :
                $warningSummary = $warningSummary . $limitWarning;
            endforeach;
            $this->limitWarnings = [];
        endif;
        if ($warningSummary !== '') :
            $from = "From: $this->serverEmail\r\nReturn-path: $this->serverEmail";
            $subject = "Deck Import failures / warnings";
            $message = "$warningHeading\n\n$warningSummary\n";
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                mail($this->userEmail, $subject, $message, $from);
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; deck import warnings not sent to {$this->userEmail}"
                );
            endif;
            $this->message->logMessage('[DEBUG]', "Deck import warnings: '$warningSummary'");
            $quickAddResult = 'multierror';
        endif;

        if (isset($quickAddResult)) :
            return $quickAddResult;
        endif;
    }

    /**
     * Called from processInput().
     */
    public function quickAdd(
        $deckNumber,
        $getString,
        $sideboardTrigger = false,
        $batch = false,
        $commanderMode = null,
        $rowNumber = null,
        $originalLine = null
    ) {
        global $noQuickAddLayouts;

        $this->message->logMessage(
            '[NOTICE]',
            "Quick add interpreter called for deck $deckNumber with '$getString' (batch mode '$batch')"
        );
        $quickAddString = htmlspecialchars($getString, ENT_NOQUOTES, 'UTF-8');
        $interpretedString = ImportExport::inputInterpreter($quickAddString);
        if ($interpretedString !== false) :
            // UUID
            if (isset($interpretedString['uuid']) and $interpretedString['uuid'] !== '') :
                $quickAddUuid = $interpretedString['uuid'];
            else :
                $quickAddUuid = '';
            endif;
            // Quantity
            if (isset($interpretedString['qty']) and $interpretedString['qty'] !== '') :
                $quickAddQty = $interpretedString['qty'];
            else :
                $quickAddQty = 1;
            endif;
            // Name
            if (isset($interpretedString['name']) and $interpretedString['name'] !== '') :
                $quickAddCard = $interpretedString['name'];
            else :
                $quickAddCard = '';
            endif;
            // Set
            if (isset($interpretedString['set']) and $interpretedString['set'] !== '') :
                $quickAddSet = strtoupper($interpretedString['set']);
            else :
                $quickAddSet = '';
            endif;
            // Lang
            if (isset($interpretedString['lang']) and $interpretedString['lang'] !== '') :
                $quickAddLang = strtoupper($interpretedString['lang']);
            else :
                $quickAddLang = '';
            endif;
            // Collector number
            if (isset($interpretedString['number']) and $interpretedString['number'] !== '') :
                $quickAddNumber = $interpretedString['number'];
            else :
                $quickAddNumber = '';
            endif;
            $quickAddCard = htmlspecialchars_decode($quickAddCard, ENT_QUOTES);
            if ($sideboardTrigger) :
                $mainQty = 0;
                $sideQty = $quickAddQty;
            else :
                $mainQty = $quickAddQty;
                $sideQty = 0;
            endif;
            $this->message->logMessage(
                '[DEBUG]',
                "Quick add interpreted as: Qty: [Main: $mainQty, Side: $sideQty] x Card: [$quickAddCard] "
                . "Set: [$quickAddSet] Collector number: [$quickAddNumber] Language: [$quickAddLang] "
                . "UUID: [$quickAddUuid]"
            );
            $stmt = null;

            // Get card layouts to not include in quick add
            $placeholders = array_fill(0, count($noQuickAddLayouts), '?');
            $placeholdersString = implode(',', $placeholders);

            if ($quickAddUuid !== '' && validUUID($quickAddUuid) !== false) :
                // Card UUID provided and valid UUID
                $this->message->logMessage(
                    '[DEBUG]',
                    "Quick add proceeding with provided UUID: [$quickAddUuid]"
                );
                $query = "SELECT id,name,setcode,number FROM cards_scry WHERE id = ? LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = [$quickAddUuid];
                $stmt->bind_param('s', $params[0]);
            elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber !== '' and $quickAddLang !== '') :
                // Card name, setcode, and collector number provided
                $this->message->logMessage(
                    '[DEBUG]',
                    "Quick add proceeding with provided name, set, number and specified language"
                );
                $query = "SELECT id FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ?
                    OR printed_name = ? OR f1_printed_name = ? OR f2_printed_name = ? OR
                    flavor_name = ? OR f1_flavor_name = ? OR f2_flavor_name = ?) AND
                    setcode = ? AND number_import = ? AND
                    lang LIKE ? AND layout NOT IN ($placeholdersString)
                    ORDER BY release_date DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickAddCard);
                array_push($params, $quickAddSet, $quickAddNumber, $quickAddLang);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber !== '') :
                // Card name, setcode, and collector number provided
                $this->message->logMessage(
                    '[DEBUG]',
                    "Quick add proceeding with provided name, set, number and primary language"
                );
                $query = "SELECT id FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ?
                    OR printed_name = ? OR f1_printed_name = ? OR f2_printed_name = ? OR
                    flavor_name = ? OR f1_flavor_name = ? OR f2_flavor_name = ?) AND
                    setcode = ? AND number_import = ? AND
                    `layout` NOT IN ($placeholdersString) AND primary_card = 1
                    ORDER BY release_date DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickAddCard);
                array_push($params, $quickAddSet, $quickAddNumber);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber === '') :
                // Card name and setcode provided
                $query = "SELECT id FROM cards_scry WHERE (name = ? OR
                    f1_name = ? OR
                    f2_name = ? OR
                    printed_name = ? OR
                    f1_printed_name = ? OR
                    f2_printed_name = ? OR
                    flavor_name = ? OR
                    f1_flavor_name = ? OR
                    f2_flavor_name = ?) AND
                    setcode = ? AND
                    `layout` NOT IN ($placeholdersString)  AND
                    primary_card = 1
                    ORDER BY release_date DESC, number ASC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickAddCard);
                array_push($params, $quickAddSet);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            elseif ($quickAddCard !== '' and $quickAddSet === '') :
                // Card name only provided, or with a number (but useless without setcode) - just grab a name match
                $setcodePlaceholders = implode(',', array_fill(0, count($this->nonPreferredSetCodes), '?'));
                $query = "SELECT id FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? OR
                    printed_name = ? OR f1_printed_name = ? OR f2_printed_name = ? OR
                    flavor_name = ? OR f1_flavor_name = ? OR f2_flavor_name = ?) AND
                    `layout` NOT IN ($placeholdersString) AND
                    primary_card = 1 AND setcode NOT IN ($setcodePlaceholders)
                    ORDER BY LENGTH(setcode) ASC, release_date DESC, number ASC LIMIT 1";
                $params = array_fill(0, 9, $quickAddCard); // First 9 are for the name variations
                $params = array_merge($params, $noQuickAddLayouts); // Add layout exclusions
                $params = array_merge($params, $this->nonPreferredSetCodes); // Add non-preferred set codes
                $stmt = $this->db->prepare($query);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            elseif ($quickAddCard === '' and $quickAddSet !== '' and $quickAddNumber !== '') :
                // Card name not provided, setcode, and collector number provided
                $query = "SELECT id FROM cards_scry WHERE setcode = ? AND number_import = ? AND
                    `layout` NOT IN ($placeholdersString) AND primary_card = 1
                    ORDER BY release_date DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = [$quickAddSet, $quickAddNumber];
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            else :
                // Not enough info, cannot add
                $this->message->logMessage('[NOTICE]', "Quick add - Not enough info to identify a card to add");
                $cardtoadd = 'cardnotfound';
                return $cardtoadd;
            endif;

            if ($stmt !== null and $stmt->execute()) :
                $result = $stmt->get_result();
                if ($result->num_rows > 0) :
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    $cardtoadd = $row['id'];
                    $this->message->logMessage('[DEBUG]', "Quick add result: UUID result is '$cardtoadd'");
                    if (!$batch) :
                        // Call addDeckCard only if not in batch mode
                        $addresult = $this->addDeckCard($deckNumber, $cardtoadd, "main", "$quickAddQty");
                        if ($addresult !== false && $addresult !== 'cardnotfound' && $commanderMode !== null) :
                            if ($commanderMode === 'commander') :
                                $this->message->logMessage('[DEBUG]', "Setting commander for $cardtoadd");
                                $this->addCommander($deckNumber, $cardtoadd);
                            elseif ($commanderMode === 'partner') :
                                $this->message->logMessage('[DEBUG]', "Setting partner/background for $cardtoadd");
                                $this->addPartner($deckNumber, $cardtoadd);
                            endif;
                        endif;
                        return $addresult;
                    else :
                        // In batch mode, store the card ID and quantity in the batchedCardIds array
                        $this->batchedCardIds[] = [
                            'id' => $cardtoadd,
                            'mainqty' => $mainQty,
                            'sideqty' => $sideQty,
                            'commander' => $commanderMode,
                            'row' => $rowNumber,
                            'line' => $originalLine
                        ];
                    endif;
                else :
                    $stmt->close();
                    $this->message->logMessage('[NOTICE]', "Quick add - Card not found");
                    $cardtoadd = 'cardnotfound';
                    return $cardtoadd;
                endif;
            else :
                $stmt->close();
                $this->message->logMessage('[ERROR]', "Quick add - SQL error: " . $stmt->error);
                $cardtoadd = 'cardnotfound';
                return $cardtoadd;
            endif;
        else :
            $this->message->logMessage('[ERROR]', "Quick add interpreter failed");
            return false;
        endif;
    }

    public function addDeckCardsBatch($deckNumber, $batchedCardIds)
    {
        $this->message->logMessage('[DEBUG]', "deckManager batch process called");
        global $commander_decktypes;
        if (!is_array($commander_decktypes)) :
            $commander_decktypes = [];
        endif;
        $values = [];
        $placeholders = [];
        $filteredBatch = [];

        if (!method_exists($this->db, 'execute_query')) :
            $this->message->logMessage('[DEBUG]', "Batch insert: DB stub lacks execute_query; skipping limits");
            $filteredBatch = $batchedCardIds;
        endif;

        $decktype = 'none';
        $canQuery = method_exists($this->db, 'execute_query');
        if (empty($filteredBatch) && $canQuery) :
            $decktypesql = $this->db->execute_query(
                "SELECT type FROM decks WHERE decknumber = ? LIMIT 1",
                [$deckNumber]
            );
            if ($decktypesql !== false && $decktypesql->num_rows > 0) :
                $decktype_row = $decktypesql->fetch_assoc();
                if ($decktype_row['type'] !== null) :
                    $decktype = $decktype_row['type'];
                endif;
            endif;
        endif;

        $cdr_type_deck = in_array($decktype, $commander_decktypes);
        if ($cdr_type_deck == true) :
            $this->message->logMessage('[DEBUG]', "Batch insert: Commander deck; skipping copy limits");
        endif;

        $cardInfoById = [];
        $currentTotalsByName = [];
        if ($cdr_type_deck == false && $canQuery) :
            $cardIds = array_values(array_unique(array_column($batchedCardIds, 'id')));
            if (!empty($cardIds)) :
                $placeholdersIn = implode(',', array_fill(0, count($cardIds), '?'));
                $cardInfoQuery = "SELECT id,name,type,f1_type,f2_type,ability,f1_ability,f2_ability "
                    . "FROM cards_scry WHERE id IN ($placeholdersIn)";
                $cardInfoResult = $this->db->execute_query($cardInfoQuery, $cardIds);
                if ($cardInfoResult !== false) :
                    while ($info = $cardInfoResult->fetch_assoc()) :
                        $cardInfoById[$info['id']] = $info;
                    endwhile;
                endif;

                $nameList = array_values(
                    array_unique(
                        array_map(function ($info) {
                            return $info['name'];
                        }, $cardInfoById)
                    )
                );
                if (!empty($nameList)) :
                    $namePlaceholders = implode(',', array_fill(0, count($nameList), '?'));
                    $qtyQuery = "SELECT cards_scry.name,
                            SUM(IFNULL(deckcards.cardqty, 0) + IFNULL(deckcards.sideqty, 0)) AS totalqty
                        FROM deckcards
                        LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                        WHERE deckcards.decknumber = ? AND cards_scry.name IN ($namePlaceholders)
                        GROUP BY cards_scry.name";
                    $qtyParams = array_merge([$deckNumber], $nameList);
                    $qtyResult = $this->db->execute_query($qtyQuery, $qtyParams);
                    if ($qtyResult !== false) :
                        while ($qtyRow = $qtyResult->fetch_assoc()) :
                            $currentTotalsByName[$qtyRow['name']] = (int) ($qtyRow['totalqty'] ?? 0);
                        endwhile;
                    endif;
                endif;
            endif;
        endif;

        if (empty($filteredBatch)) :
            $queuedTotalsByName = [];
            foreach ($batchedCardIds as $batchedCard) :
                $id = $batchedCard['id'];
                $mainQty = $batchedCard['mainqty'];
                $sideQty = $batchedCard['sideqty'];
                if ($mainQty <= 0 && $sideQty <= 0) :
                    continue;
                endif;

                if ($cdr_type_deck == false && isset($cardInfoById[$id])) :
                    $info = $cardInfoById[$id];
                    $card_type = $info['type'];
                    if ($card_type === null && isset($info['f1_type'])) :
                        $card_type = $info['f1_type'];
                    elseif ($card_type === null && isset($info['f2_type'])) :
                        $card_type = $info['f2_type'];
                    endif;
                    $maxCopies = $this->mtgCardCopyLimit(
                        $card_type,
                        $info['ability'] ?? null,
                        $info['f1_ability'] ?? null,
                        $info['f2_ability'] ?? null,
                        $decktype
                    );
                    if ($maxCopies !== null) :
                        $name = $info['name'];
                        $existingQty = $currentTotalsByName[$name] ?? 0;
                        $queuedQty = $queuedTotalsByName[$name] ?? 0;
                        $availableQty = $maxCopies - ($existingQty + $queuedQty);
                        if ($availableQty <= 0) :
                            $this->message->logMessage(
                                '[DEBUG]',
                                "Batch limit reached for '$name'; skipping add"
                            );
                            $warningRow = $batchedCard['row'] ?? 'N/A';
                            $warningLine = $batchedCard['line'] ?? $name;
                            $this->limitWarnings[] = "LIMIT - Row $warningRow, Line: '$warningLine' (limit reached)\n";
                            continue;
                        endif;
                        $requestedQty = $mainQty > 0 ? $mainQty : $sideQty;
                        if ($requestedQty > $availableQty) :
                            $this->message->logMessage(
                                '[DEBUG]',
                                "Batch limiting '$name' qty from $requestedQty to $availableQty"
                            );
                            $warningRow = $batchedCard['row'] ?? 'N/A';
                            $warningLine = $batchedCard['line'] ?? $name;
                            $this->limitWarnings[] = "LIMIT - Row $warningRow, Line: '$warningLine' "
                                . "(limited to $availableQty)\n";
                            if ($mainQty > 0) :
                                $mainQty = $availableQty;
                            else :
                                $sideQty = $availableQty;
                            endif;
                            $requestedQty = $availableQty;
                        endif;
                        $queuedTotalsByName[$name] = $queuedQty + $requestedQty;
                    endif;
                endif;

                if ($mainQty <= 0 && $sideQty <= 0) :
                    continue;
                endif;

                $filteredBatch[] = [
                    'id' => $id,
                    'mainqty' => $mainQty,
                    'sideqty' => $sideQty,
                    'commander' => $batchedCard['commander'] ?? null
                ];
            endforeach;
        endif;

        if (empty($filteredBatch)) :
            $this->message->logMessage('[DEBUG]', "Batch insert: no cards after limits applied");
            return;
        endif;

        foreach ($filteredBatch as $batchedCard) :
            $id = $batchedCard['id'];
            $mainQty = $batchedCard['mainqty'];
            $sideQty = $batchedCard['sideqty'];
            // Add each card to the batch
            $values[] = "($deckNumber, $id, $mainQty, $sideQty)";
            $placeholders[] = '(?, ?, ?, ?)';
        endforeach;

        if (!empty($values)) :
            $valuesString = implode(', ', $values);
            $placeholdersString = implode(', ', $placeholders);

            $query = "INSERT INTO deckcards (decknumber, cardnumber, cardqty, sideqty) VALUES $placeholdersString
                ON DUPLICATE KEY UPDATE cardqty = cardqty + VALUES(cardqty), sideqty = sideqty + VALUES(sideqty)";

            // Bind parameters and execute the query
            $stmt = $this->db->prepare($query);

            // Generate the type definition string dynamically based on the number of batched cards
            $typeDefinition = str_repeat('isii', count($filteredBatch));

            // Prepare an array with the values to be bound
            $bindValues = [];
            foreach ($filteredBatch as $batchedCard) :
                $bindValues[] = $deckNumber;
                $bindValues[] = $batchedCard['id'];
                $bindValues[] = $batchedCard['mainqty'];
                $bindValues[] = $batchedCard['sideqty'];
            endforeach;

            // Bind the parameters dynamically
            $stmt->bind_param($typeDefinition, ...$bindValues);

            if ($stmt->execute()) :
                $this->message->logMessage('[DEBUG]', "deckManager batch process completed");
                foreach ($filteredBatch as $batchedCard) :
                    if (
                        isset($batchedCard['commander'])
                        and $batchedCard['commander'] !== null
                        and $batchedCard['sideqty'] == 0
                    ) :
                        if ($batchedCard['commander'] === 'commander') :
                            $this->message->logMessage('[DEBUG]', "Batch setting commander for {$batchedCard['id']}");
                            $this->addCommander($deckNumber, $batchedCard['id']);
                        elseif ($batchedCard['commander'] === 'partner') :
                            $this->message->logMessage(
                                '[DEBUG]',
                                "Batch setting partner/background for {$batchedCard['id']}"
                            );
                            $this->addPartner($deckNumber, $batchedCard['id']);
                        endif;
                    endif;
                endforeach;
                $this->bumpDeckUpdatedAt($deckNumber);
            else :
                $this->message->logMessage('[ERROR]', "Error executing batch insert query: " . $stmt->error);
            endif;

            $stmt->close();
        endif;
    }

    public function bumpDeckUpdatedAt($deckNumber)
    {
        $query = "UPDATE decks SET deck_updated_at = NOW(6) WHERE decknumber = ? LIMIT 1";
        if (method_exists($this->db, 'execute_query')) :
            $result = $this->db->execute_query($query, [$deckNumber]);
        else :
            $this->message->logMessage(
                '[DEBUG]',
                "Deck updated_at bump skipped execute_query for deck $deckNumber (stub)"
            );
            $result = true;
        endif;
        if ($result === false) :
            $this->message->logMessage(
                '[ERROR]',
                "Deck updated_at bump failed for deck $deckNumber: {$this->db->error}"
            );
        else :
            $this->message->logMessage('[DEBUG]', "Deck updated_at bumped for deck $deckNumber");
        endif;
    }

    public function assertDeckOwner($deck, $user, $context = '')
    {
        $contextLabel = $context !== '' ? $context . ': ' : '';
        $this->message->logMessage(
            '[DEBUG]',
            "{$contextLabel}Asserting deck ownership for deck $deck, user $user"
        );
        $sql = "SELECT deckname, owner FROM decks WHERE decknumber = ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$deck]);
        if ($result === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;

        $row = $result->fetch_assoc();
        if ($row === null) :
            $this->message->logMessage(
                '[ERROR]',
                "{$contextLabel}No deck found for deck $deck, returning to deck page"
            );
            return false;
        endif;

        $deckName = $row['deckname'];
        $owner = $row['owner'];
        $this->message->logMessage(
            '[DEBUG]',
            "{$contextLabel}Deck $deck ($deckName) belongs to owner $owner (called by $user)"
        );
        if ((int) $owner !== (int) $user) :
            $this->message->logMessage(
                '[ERROR]',
                "{$contextLabel}Deck ownership assertion failed for deck $deck, user $user"
            );
            return false;
        endif;

        $this->message->logMessage(
            '[DEBUG]',
            "{$contextLabel}Deck ownership assertion passed for deck $deck ($deckName)"
        );
        return true;
    }

    public function deckCardCheck($card, $user)
    {
        $this->message->logMessage('[DEBUG]', "Checking to see what decks this card is in for user $user...");

        $sql = "SELECT deckcards.decknumber, deckcards.cardqty, deckcards.sideqty, decks.deckname 
                FROM deckcards 
                LEFT JOIN decks ON deckcards.decknumber = decks.decknumber 
                WHERE cardnumber = ? AND owner = ?";
        $result = $this->db->execute_query($sql, [$card, $user]);
        if ($result === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        else :
            $i = 0;
            $record = array();
            while ($row = $result->fetch_assoc()) :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Card $card, mainqty {$row['cardqty']}, sideqty {$row['sideqty']} in decknumber "
                        . "{$row['decknumber']} owned by user $user"
                );
                $record[$i]['decknumber'] = $row['decknumber'];
                $record[$i]['qty'] = $row['cardqty'];
                $record[$i]['sideqty'] = $row['sideqty'];
                $record[$i]['deckname'] = $row['deckname'];
                $i = $i + 1;
            endwhile;
            return $record;
        endif;
    }

    public function addDeckCard($deck, $card, $section, $quantity)
    {
        global $commander_decktypes, $commander_multiples;
        $this->message->logMessage(
            '[NOTICE]',
            "Add card called: '$quantity' x '$card' to '$deck' ($section)"
        );

        // Get card name and other key details of card to add
        $cardnamequery = "SELECT name,type,f1_type,f2_type,ability,f1_ability,f2_ability FROM cards_scry "
            . "WHERE id = ? LIMIT 1";
        $result = $this->db->execute_query($cardnamequery, [$card]);
        $cardname = $result->fetch_assoc();
        if ($result === false) :
            throw new \Exception(
                "[ERROR] Class " . __METHOD__ . " " . __LINE__
                    . " - SQL failure: Error: " . $this->db->error
            );
        else :
            $cardnametext = $cardname['name'];
            $i = 0;
            $cdr_1_plus = false;

            // Cater for cards with NULL type (REX and SLD double-sided cards with dual art but functionally same card
            if ($cardname['type'] !== null) :
                $card_type = $cardname['type'];
            elseif ($cardname['type'] === null and isset($cardname['f1_type'])) :
                $card_type = $cardname['f1_type'];
            elseif ($cardname['type'] === null and isset($cardname['f2_type'])) :
                $card_type = $cardname['f2_type'];
            else :
                $card_type = 'None';
            endif;

            while ($i < count($commander_multiples)) :
                $this->message->logMessage('[DEBUG]', "Checking type for: {$commander_multiples[$i]}");
                if (str_contains($card_type, $commander_multiples[$i]) == true) :
                    $cdr_1_plus = true;
                endif;
                $i++;
            endwhile;
            $ability_candidates = array_filter(
                [
                    $cardname['ability'] ?? null,
                    $cardname['f1_ability'] ?? null,
                    $cardname['f2_ability'] ?? null
                ]
            );
            $i = 0;
            while ($i < count($this->anyQuantity)) :
                $this->message->logMessage('[DEBUG]', "Checking ability for: {$this->anyQuantity[$i]}");
                foreach ($ability_candidates as $ability_text) :
                    if (str_contains($ability_text, $this->anyQuantity[$i]) == true) :
                        $cdr_1_plus = true;
                        break;
                    endif;
                endforeach;
                $i++;
            endwhile;
            if ($cdr_1_plus == false) :
                $multi_allowed = "no";
            else :
                $multi_allowed = "yes";
            endif;
            $this->message->logMessage(
                '[DEBUG]',
                "Card name for $card is $cardnametext; Commander multiples allowed: $multi_allowed"
            );
        endif;

        // Get deck type and existing cards in it
        if (
            $decktypesql = $this->db->execute_query("SELECT type
                                    FROM decks 
                                    WHERE decknumber = ?", [$deck])
        ) :
            while ($row = $decktypesql->fetch_assoc()) :
                if ($row['type'] == null) :
                    $decktype = "none";
                else :
                    $decktype = $row['type'];
                endif;
            endwhile;
        else :
            $decktype = "none";
        endif;
        $cardlist = $this->db->execute_query("SELECT name,decks.type
                                    FROM deckcards 
                                LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                                LEFT JOIN decks on deckcards.decknumber = decks.decknumber
                                WHERE deckcards.decknumber = ? AND (cardqty > 0 OR sideqty > 0)", [$deck]);
        $cardlistnames = array();
        while ($row = $cardlist->fetch_assoc()) :
            if (!in_array($row['name'], $cardlistnames)) :
                $cardlistnames[] = $row['name'];
            endif;
        endwhile;
        if (in_array($cardnametext, $cardlistnames)) :
            $this->message->logMessage('[DEBUG]', "Cardname $cardnametext is already in this deck");
            $already_in_deck = true;
        else :
            $already_in_deck = false;
        endif;
        if (in_array($decktype, $commander_decktypes)) :
            $this->message->logMessage('[DEBUG]', "Deck $deck is Commander-type");
            $cdr_type_deck = true;
        else :
            $cdr_type_deck = false;
        endif;
        if ($already_in_deck == true and $cdr_type_deck == true and $cdr_1_plus == false) :
            $this->message->logMessage(
                '[DEBUG]',
                "Card already in Commander-style deck; multiples of this type not allowed"
            );
            $quantity = false;
        elseif ($already_in_deck == false and $cdr_type_deck == true and $cdr_1_plus == false) :
            $this->message->logMessage(
                '[DEBUG]',
                "Card not already in Commander-style deck; multiples of this type not allowed; adding 1"
            );
            $quantity = 1;
        elseif ($already_in_deck == true and $cdr_type_deck == true and $cdr_1_plus == true) :
            $this->message->logMessage(
                '[DEBUG]',
                "Card already in Commander-style deck; multiples allowed; adding requested qty"
            );
            $quantity = $quantity;
        elseif ($already_in_deck == false and $cdr_type_deck == true and $cdr_1_plus == true) :
            $this->message->logMessage(
                '[DEBUG]',
                "Card not already in Commander-style deck; multiples allowed; adding requested qty"
            );
            $quantity = $quantity;
        elseif ($cdr_type_deck == false) :
            $this->message->logMessage(
                '[DEBUG]',
                "Non-Commander deck; adding requested qty"
            );
            $quantity = $quantity;
        endif;
        $limitAction = null;
        if ($cdr_type_deck == false and $quantity != false) :
            $maxCopies = $this->mtgCardCopyLimit(
                $card_type,
                $cardname['ability'] ?? null,
                $cardname['f1_ability'] ?? null,
                $cardname['f2_ability'] ?? null,
                $decktype
            );
            if ($maxCopies !== null) :
                $qtyquery = "SELECT SUM(IFNULL(deckcards.cardqty, 0) + IFNULL(deckcards.sideqty, 0)) AS totalqty
                    FROM deckcards
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                    WHERE deckcards.decknumber = ? AND cards_scry.name = ?";
                $qtyresult = $this->db->execute_query($qtyquery, [$deck, $cardnametext]);
                if ($qtyresult !== false) :
                    $qtyrow = $qtyresult->fetch_assoc();
                    $existingQty = (int) ($qtyrow['totalqty'] ?? 0);
                else :
                    $existingQty = 0;
                endif;
                $availableQty = $maxCopies - $existingQty;
                $this->message->logMessage(
                    '[DEBUG]',
                    "Non-Commander copy limit for '$cardnametext' is $maxCopies (existing $existingQty)"
                );
                if ($availableQty <= 0) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Limit reached for '$cardnametext'; skipping add"
                    );
                    $quantity = false;
                    $limitAction = 'limitreached';
                elseif ((int) $quantity > $availableQty) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Limiting '$cardnametext' add qty from $quantity to $availableQty"
                    );
                    $quantity = $availableQty;
                    $limitAction = "limitpartial:$availableQty";
                endif;
            endif;
        endif;

        // Add card to deck

        if ($quantity != false) :
            $this->message->logMessage(
                '[DEBUG]',
                "...adding $quantity x $card, $cardnametext to deck #$deck"
            );
            if ($section == "side") :
                $checkqry = $this->db->execute_query(
                    "SELECT sideqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? LIMIT 1",
                    [$deck,$card]
                );
                if ($checkqry !== false) :
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0) : // The card is in the deck, no detail yet on qty or side/main
                        $check = $checkqry->fetch_assoc();
                        if ($check['sideqty'] != null) :
                            $cardquery = "UPDATE deckcards SET sideqty = sideqty + 1 WHERE decknumber = ? "
                                . "AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "+1side";
                        else :
                            $cardquery = "UPDATE deckcards SET sideqty = 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "+1side";
                        endif;
                    else :
                        // The card is not in the deck at all
                        $cardquery = "INSERT into deckcards (decknumber, cardnumber, sideqty) VALUES (?, ?, ?)";
                        $params = [$deck,$card,$quantity];
                        $status = "+newside";
                    endif;
                else :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL failure: " . $this->db->error
                    );
                endif;
            elseif ($section == "main") :
                $checkqry = $this->db->execute_query(
                    "SELECT cardqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? LIMIT 1",
                    [$deck,$card]
                );
                if ($checkqry !== false) :
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0) : // The card is in the deck, no detail yet on qty or side/main
                        $check = $checkqry->fetch_assoc();
                        if ($check['cardqty'] != null) :
                            $cardquery = "UPDATE deckcards SET cardqty = cardqty + ? WHERE decknumber = ? "
                                . "AND cardnumber = ?";
                            $params = [$quantity,$deck,$card];
                            $status = "+1main";
                        else :
                            $cardquery = "UPDATE deckcards SET cardqty = 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "+1main";
                        endif;
                    else :
                        // The card is not in the deck at all
                        $cardquery = "INSERT into deckcards (decknumber, cardnumber, cardqty) VALUES (?, ?, ?)";
                        $params = [$deck,$card,$quantity];
                        $status = "+newmain";
                    endif;
                else :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL failure: " . $this->db->error
                    );
                endif;
            endif;

            $this->message->logMessage('[NOTICE]', "Add card called: $cardquery, status is $status");
            if ($runquery = $this->db->execute_query($cardquery, $params)) :
                $this->bumpDeckUpdatedAt($deck);
                if ($limitAction !== null) :
                    return $limitAction;
                endif;
                return $status;
            else :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            endif;
        else :
            $this->message->logMessage('[DEBUG]', "...skipping $cardnametext to deck #$deck");
            if ($limitAction !== null) :
                return $limitAction;
            endif;
            return 'cardnotadded';
        endif;
    }

    public function subtractDeckCard($deck, $card, $section, $quantity)
    {
        $didUpdate = false;
        if ($quantity == "all") :
            if ($section == "side") :
                $cardquery = "UPDATE deckcards SET sideqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                $params = [$deck,$card];
                $status = "allside";
            elseif ($section == "main") :
                $cardquery = "UPDATE deckcards SET cardqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                $params = [$deck,$card];
                $status = "allmain";
            endif;
        else :
            if ($section == "side") :
                $checkqry = $this->db->execute_query(
                    "SELECT sideqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? "
                    . "AND sideqty IS NOT NULL LIMIT 1",
                    [$deck,$card]
                );
                if ($checkqry !== false) :
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0) : // The card is in the deck side
                        $check = $checkqry->fetch_assoc();
                        if ($check['sideqty'] > 1) :
                            $cardquery = "UPDATE deckcards SET sideqty = sideqty - 1 WHERE decknumber = ? "
                                . "AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "-1side";
                        elseif ($check['sideqty'] == 1) :
                            $cardquery = "UPDATE deckcards SET sideqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "lastside";
                        endif;
                    else :
                        $status = "-error";
                        $cardquery = '';
                        $params = [];
                    endif;
                else :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL failure: " . $this->db->error
                    );
                endif;
            elseif ($section == "main") :
                $checkqry = $this->db->execute_query(
                    "SELECT cardqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? "
                    . " AND cardqty IS NOT NULL LIMIT 1",
                    [$deck,$card]
                );
                if ($checkqry !== false) :
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0) : // The card is in the deck main
                        $check = $checkqry->fetch_assoc();
                        if ($check['cardqty'] > 1) :
                            $cardquery = "UPDATE deckcards SET cardqty = cardqty - 1 WHERE decknumber = ? "
                                . "AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "-1main";
                        elseif ($check['cardqty'] == 1) :
                            $cardquery = "UPDATE deckcards SET cardqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "lastmain";
                        endif;
                    else :
                        $status = "-error";
                        $cardquery = '';
                        $params = [];
                    endif;
                else :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": SQL failure: " . $this->db->error
                    );
                endif;
            endif;
        endif;

        $this->message->logMessage('[NOTICE]', "Delete deck card query called: $cardquery, status is $status");

        if ($status != '-error') :
            if ($runquery = $this->db->execute_query($cardquery, $params)) :
                $didUpdate = true;
            else :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            endif;
        else :
            $this->message->logMessage('[ERROR]', "Delete deck card query called: ERROR status is $status");
        endif;

        // Clean-up empties
        if ($status == 'lastmain' or $status == 'lastside' or $status == 'allmain' or $status == 'allside') :
            $this->message->logMessage('[NOTICE]', "Delete deck card query called: $cardquery, status is $status");
            $cardquery = "DELETE FROM deckcards WHERE decknumber = ? AND (
                (cardqty = 0 AND sideqty = 0) OR
                (cardqty = 0 AND sideqty IS NULL) OR
                (cardqty IS NULL AND sideqty = 0) OR
                (cardqty IS NULL AND sideqty IS NULL)
            )";
            $params = [$deck];
            if ($runquery = $this->db->execute_query($cardquery, $params)) :
                $didUpdate = true;
            else :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            endif;
        endif;

        if ($didUpdate === true) :
            $this->bumpDeckUpdatedAt($deck);
        endif;

        return $status;
    }

    public function addCommander($deck, $card)
    {
        // Check if commander already exists in the deck
        $check = $this->db->prepare('SELECT commander FROM deckcards WHERE decknumber = ? AND commander = 1');
        $check->bind_param('i', $deck);
        $check->execute();
        $check_result = $check->get_result();
        if ($check_result->num_rows > 0) :
            // Commander already exists, remove old commander
            $removeCommanderQuery = 'UPDATE deckcards SET commander = 0 WHERE decknumber = ?';
            $removeCommanderStmt = $this->db->prepare($removeCommanderQuery);
            $removeCommanderStmt->bind_param('i', $deck);
            if ($removeCommanderStmt->execute()) :
                $this->message->logMessage('[NOTICE]', "Old Commander removed");
            else :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            endif;
            $removeCommanderStmt->close();
        endif;
        $status = "+cdr";

        // Add new commander
        $addCommanderQuery = 'UPDATE deckcards SET commander = 1 WHERE decknumber = ? AND cardnumber = ?';
        $addCommanderStmt = $this->db->prepare($addCommanderQuery);
        $addCommanderStmt->bind_param('is', $deck, $card);
        if ($addCommanderStmt->execute()) :
            $this->message->logMessage('[NOTICE]', "Add Commander run: $addCommanderQuery, status is $status");
            $this->bumpDeckUpdatedAt($deck);
            return $status;
        else :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;
        $addCommanderStmt->close();
    }

    public function addPartner($deck, $card)
    {
        $check = $this->db->execute_query(
            'SELECT commander FROM deckcards WHERE decknumber = ? AND commander = 2',
            [$deck]
        );
        if ($check->num_rows > 0) : //Partner already there
            if (
                $runquery = $this->db->execute_query(
                    "UPDATE deckcards SET commander = 0 WHERE decknumber = ?",
                    [$deck]
                )
            ) :
                $this->message->logMessage('[NOTICE]', "Old Partner removed");
            else :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            endif;
        endif;
        $status = "+ptnr";
        if (
            $runquery = $this->db->execute_query(
                "UPDATE deckcards SET commander = '2' WHERE decknumber = ? AND cardnumber = ?",
                [$deck,$card]
            )
        ) :
            $this->message->logMessage('[NOTICE]', "Add Partner run, status is $status");
            $this->bumpDeckUpdatedAt($deck);
            return $status;
        else :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;
    }

    public function delCommander($deck, $card)
    {
        $check = $this->db->execute_query(
            "SELECT commander FROM deckcards WHERE decknumber = ? AND cardnumber = ? AND commander > 0",
            [$deck,$card]
        );
        if ($check->num_rows > 0) :
            $status = "-cdr";
            if (
                $runquery = $this->db->execute_query(
                    "UPDATE deckcards SET commander = 0 WHERE decknumber = ? AND cardnumber = ?",
                    [$deck,$card]
                )
            ) :
                $this->message->logMessage('[NOTICE]', "Remove Commander called, status is $status");
                $this->bumpDeckUpdatedAt($deck);
                return $status;
            else :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            endif;
        else :
            $status = "notcdr";
        endif;
    }

    public function delDeck($decktodelete)
    {
        $this->message->logMessage('[NOTICE]', "Delete deck called: deck $decktodelete");
        $stmt = $this->db->prepare("DELETE FROM decks WHERE decknumber = ?");
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": Preparing SQL failure: " . $this->db->error
            );
        endif;
        $bind = $stmt->bind_param("i", $decktodelete);
        if ($bind === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": Binding SQL failure: " . $this->db->error
            );
        endif;
        $exec = $stmt->execute();
        if ($exec === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": Deleting deck: " . $this->db->error
            );
        else :
            $checkgone1 = "SELECT decknumber FROM decks WHERE decknumber = ? LIMIT 1";
            $runquery1 = $this->db->execute_query($checkgone1, [$decktodelete]);
            $result1 = $runquery1->fetch_assoc();
            if ($result1 === null) :
                $deck_deleted = 1;
            else :
                $deck_deleted = 0;
            endif;
        endif;
        $stmt->close();
        $stmt = $this->db->prepare("DELETE FROM deckcards WHERE decknumber = ?");
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": Preparing SQL failure: " . $this->db->error
            );
        endif;
        $bind = $stmt->bind_param("i", $decktodelete);
        if ($bind === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": Binding SQL failure: " . $this->db->error
            );
        endif;
        $exec = $stmt->execute();
        if ($exec === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": Deleting deck cards: " . $this->db->error
            );
        else :
            $checkgone2 = "SELECT cardnumber FROM deckcards WHERE decknumber = ? LIMIT 1";
            $runquery2 = $this->db->execute_query($checkgone2, [$decktodelete]);
            $result2 = $runquery2->fetch_assoc();
            if ($result2 === null) :
                $deckcards_deleted = 1;
            else :
                $deckcards_deleted = 0;
            endif;
        endif;
        $stmt->close();
        if ($deck_deleted === 1 and $deckcards_deleted === 1) :
            $this->message->logMessage('[NOTICE]', "Deck $decktodelete deleted");
        else :?>
            <div class="msg-new error-new" onclick='closeMe(this)'><span>Deck and/or cards not deleted</span>
                <br>
                <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
            </div> <?php
        endif;
    }

    public function addDeck($user, $newdeckname)
    {
        $this->message->logMessage('[NOTICE]', "Add deck called: deck $newdeckname");
        $decksuccess = [];

        $decknamechecksql = "SELECT decknumber FROM decks WHERE owner = ? and deckname = ? LIMIT 1";
        $decknameparams = [$user,$newdeckname];
        $result = $this->db->execute_query($decknamechecksql, $decknameparams);
        if ($result !== false && $result->num_rows === 0) :
            $this->message->logMessage(
                '[NOTICE]',
                "Deck does not exist for user: $user, deckname: '$newdeckname'"
            );

            //Create new deck
            $sql = "INSERT INTO decks (owner,deckname) VALUES (?,?)";
            $params = [$user,$newdeckname];
            if ($deckinsert = $this->db->execute_query($sql, $params) && $this->db->affected_rows === 1) :
                $this->message->logMessage(
                    '[NOTICE]',
                    "SQL deck insert succeeded for user: $user, deckname: '$newdeckname'"
                );
            else :
                throw new \Exception(
                    "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $this->db->error
                );
            endif;

            //Checking if it created OK
            $this->message->logMessage('[NOTICE]', "Running confirm SQL query");
            $checksql = "SELECT decknumber FROM decks
                            WHERE owner = ? AND deckname = ? LIMIT 1";
            $checkparams = [$user,$newdeckname];
            $runquery = $this->db->execute_query($checksql, $checkparams);
            if ($runquery !== false && $runquery->num_rows === 1) :
                $this->message->logMessage('[NOTICE]', "Confirmed existence of deck: $newdeckname");
                $deckcheckrow = $runquery->fetch_assoc();
                $decksuccess['flag'] = 1; //set flag so we know we don't need to check for cards in deck.
                $decksuccess['decknumber'] = $deckcheckrow['decknumber'];
            elseif ($runquery !== false && $runquery->num_rows === 0) :
                $this->message->logMessage('[NOTICE]', "Failed - deck: $newdeckname not created");
                ?>
                <div class="msg-new error-new" onclick='closeMe(this)'><span>Deck creation failed</span>
                    <br>
                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                </div>
                <?php
                $decksuccess['flag'] = 10; //set flag so we know to break.
            else :
                throw new \Exception(
                    "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $this->db->error
                );
            endif;
        elseif ($result !== false && $result->num_rows === 1) :
            $this->message->logMessage('[NOTICE]', "New deck name already exists"); ?>
            <div class="msg-new error-new" onclick='closeMe(this)'><span>Deck name exists</span>
                <br>
                <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
            </div> <?php
            $decksuccess['flag'] = 10; //set flag so we know to break.
        else :
            throw new \Exception(
                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $this->db->error
            );
        endif;
        if (!isset($decksuccess['decknumber'])) :
            $decksuccess['decknumber'] = null;
        endif;
        return $decksuccess;
    }

    public function renameDeck($deck, $newname, $user)
    {
        $this->message->logMessage('[NOTICE]', "Rename deck called: deck $deck to '$newname'");

        // CHECK IF NAME IS ALREADY USED
        $query = 'SELECT decknumber FROM decks WHERE deckname=? AND owner=?';
        $stmt = $this->db->execute_query($query, [$newname,$user]);
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        else :
            if ($stmt->num_rows > 0) :
                $newnamereturn = 2;
                $this->message->logMessage('[NOTICE]', "Name '$newname' already used");
                return($newnamereturn);
            else :
                $newnamereturn = 0; //OK to continue
                $this->message->logMessage('[NOTICE]', "Name '$newname' not already used");
            endif;
        endif;
        $stmt->close();

        //RENAME
        $query = 'UPDATE decks SET deckname=? WHERE decknumber=?';
        $stmt = $this->db->execute_query($query, [$newname,$deck]);
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        else :
            $this->message->logMessage('[DEBUG]', "Name '$newname' query run");
            if ($this->db->affected_rows !== 1) :
                $newnamereturn = 1; //Error
                $this->message->logMessage(
                    '[DEBUG]',
                    "...result: Unknown error: {$this->db->affected_rows} row(s) affected"
                );
            endif;
            $this->message->logMessage('[DEBUG]', "...result: {$this->db->affected_rows} row affected ");
        endif;
        return($newnamereturn);
    }

    public function setDeckType($deck, $decktype)
    {
        $this->message->logMessage('[NOTICE]', "Set deck type called: deck $deck to '$decktype'");

        //RENAME
        $query = 'UPDATE decks set type = ? WHERE decknumber = ?';
        $stmt = $this->db->execute_query($query, [$decktype,$deck]);
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        else :
            $decktypereturn = 0;
            $this->message->logMessage('[DEBUG]', "Deck type '$decktype' query run");
            if ($this->db->affected_rows !== 1) :
                $decktypereturn = 1; //Error
                $this->message->logMessage(
                    '[DEBUG]',
                    "...result: Unknown error: {$this->db->affected_rows} row(s) affected"
                );
            endif;
            $this->message->logMessage('[DEBUG]', "...result: {$this->db->affected_rows} row affected ");
            if ($decktypereturn === 0) :
                $this->bumpDeckUpdatedAt($deck);
            endif;
        endif;
        return($decktypereturn);
    }

    public function exportDeck($deckNumber, $format, $zipFilePath = null)
    {
        //Format options:
        //
        // - Download
        // - Email
        // - Bulk
        // - Variable (returns the decklist from the function, used in duplicate deck function)

        global $commander_decktypes, $smtpParameters;
        $this->message->logMessage('[NOTICE]', "Deck export called for deck $deckNumber");

        $detectPlanePhenomenon = function ($cardType) {
            if ($cardType === null || $cardType === '') :
                return false;
            endif;
            return preg_match('/\bPlane\b/i', $cardType) === 1
                || preg_match('/\bPhenomenon\b/i', $cardType) === 1;
        };
        $detectTokenLike = function ($cardType) {
            if ($cardType === null || $cardType === '') :
                return false;
            endif;
            return preg_match('/\bToken\b/i', $cardType) === 1
                || preg_match('/\bEmblem\b/i', $cardType) === 1;
        };
        $normalizeType = function ($row) {
            $cardType = $row['type'] ?? '';
            if ($cardType === '' && isset($row['f1_type'])) :
                $cardType = $row['f1_type'];
            endif;
            if (strpos($cardType, ' //') !== false) :
                $len = strpos($cardType, ' //');
                $cardType = substr($cardType, 0, $len);
            endif;
            return $cardType;
        };

        $query = 'SELECT * FROM decks WHERE decknumber=?';
        $stmt = $this->db->execute_query($query, [$deckNumber]);
        if ($stmt === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        elseif ($stmt->num_rows < 1) :
            $this->message->logMessage('[ERROR]', "There is no deck $deckNumber");
            return false;
        else :
            $deckrow = $stmt->fetch_assoc();
            $deckName = $deckrow['deckname'];
            $decktype = $deckrow['type'];
            $this->message->logMessage(
                '[DEBUG]',
                "Deck name is '$deckName' and type '$decktype' for deck $deckNumber"
            );
            $detailquery = 'SELECT decknumber,cardnumber,cardqty,sideqty,commander,name,printed_name,f1_name,'
                . 'f1_printed_name,f2_name,f2_printed_name,type,setcode,number,number_import '
                . 'FROM deckcards LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id '
                . 'WHERE decknumber=?';
            $detailstmt = $this->db->execute_query($detailquery, [$deckNumber]);
            $emptyDeck = false;
            if ($detailstmt === false) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            elseif ($detailstmt->num_rows < 1) :
                $this->message->logMessage('[ERROR]', "There are no cards in deck $deckNumber");
                $emptyDeck = true;
            else :
                $allRows = [];
                while ($detailrow = $detailstmt->fetch_assoc()) :
                    $allRows[] = $detailrow;
                endwhile;
                usort($allRows, function ($a, $b) {
                    // Handle NULL values in 'type'
                    $typeA = $a['type'] ?? '';  // If NULL, use an empty string
                    $typeB = $b['type'] ?? '';  // If NULL, use an empty string

                    // First compare by 'type'
                    $typeComparison = strcmp($typeA, $typeB);

                    // If 'type' is the same, compare by 'name'
                    if ($typeComparison === 0) :
                        // Handle NULL values in 'name'
                        $nameA = $a['name'] ?? '';  // If NULL, use an empty string
                        $nameB = $b['name'] ?? '';  // If NULL, use an empty string
                        return strcmp($nameA, $nameB);
                    endif;
                    // Otherwise, return the result of the 'type' comparison
                    return $typeComparison;
                });
                if (is_null($decktype) || $decktype === "") :
                    $textfile = "Deckname: {$deckrow['deckname']}\r\n\r\n";
                else :
                    $textfile = "Deckname: $deckName ($decktype)\r\n\r\n";
                endif;
                $sidefile = "";
                $mainLines = [];
                $planeLines = [];
                $tokenLines = [];
                $sideLines = [];
                if (in_array($decktype, $commander_decktypes)) :
                    $cdrDeck = 1;
                    foreach ($allRows as $row) :
                        if ($row['commander'] === 1) :
                            $this->message->logMessage('[DEBUG]', "Commander found: {$row['name']}");
                            $textfile = $textfile . "Commander\r\n{$row['cardqty']} {$row['name']} "
                                . "({$row['setcode']} {$row['number_import']})\r\n\r\n";
                        endif;
                    endforeach;
                    foreach ($allRows as $row) :
                        if ($row['commander'] === 2) :
                            $this->message->logMessage('[DEBUG]', "Second commander found: {$row['name']}");
                            $textfile = $textfile . "Partner/Background\r\n{$row['cardqty']} {$row['name']} "
                                . "({$row['setcode']} {$row['number_import']})\r\n\r\n";
                        endif;
                    endforeach;
                else :
                    $cdrDeck = 0;
                endif;
                foreach ($allRows as $detailrow) :
                    $cardType = $normalizeType($detailrow);
                    $isPlanePhenomenon = $detectPlanePhenomenon($cardType);
                    $isTokenLike = $detectTokenLike($cardType);
                    if (
                        $detailrow['cardqty'] >= 1
                        && (
                            $cdrDeck !== 1
                            || ($cdrDeck === 1
                            && ($detailrow['commander'] !== 1 && $detailrow['commander'] !== 2))
                        )
                    ) :
                        $line = "{$detailrow['cardqty']} {$detailrow['name']} "
                            . "({$detailrow['setcode']} {$detailrow['number_import']})\r\n";
                        if ($isPlanePhenomenon) :
                            $planeLines[] = $line;
                        elseif ($isTokenLike) :
                            $tokenLines[] = $line;
                        else :
                            $mainLines[] = $line;
                        endif;
                    endif;
                    if ($detailrow['sideqty'] >= 1) :
                        $line = "{$detailrow['sideqty']} {$detailrow['name']} "
                            . "({$detailrow['setcode']} {$detailrow['number_import']})\r\n";
                        if ($isPlanePhenomenon) :
                            $planeLines[] = $line;
                        elseif ($isTokenLike) :
                            $tokenLines[] = $line;
                        else :
                            $sideLines[] = $line;
                        endif;
                    endif;
                endforeach;
                $mainfile = implode('', $mainLines);
                $planesfile = implode('', $planeLines);
                $tokensfile = implode('', $tokenLines);
                $sidefile = implode('', $sideLines);
                $this->message->logMessage(
                    '[DEBUG]',
                    'Deck export sections - main: ' . count($mainLines)
                        . ', planes: ' . count($planeLines)
                        . ', tokens: ' . count($tokenLines)
                        . ', sideboard: ' . count($sideLines)
                );
                if ($mainfile !== "") :
                    $textfile = $textfile . $mainfile;
                endif;
                if ($planesfile !== "") :
                    $textfile = $textfile . "\r\nPlanes and Phenomena\r\n\r\n" . $planesfile;
                endif;
                if ($tokensfile !== "") :
                    $textfile = $textfile . "\r\nTokens\r\n\r\n" . $tokensfile;
                endif;
                if ($sidefile !== "") :
                    $textfile = $textfile . "\r\nSideboard\r\n\r\n" . $sidefile;
                endif;
            endif;
            $detailstmt->close();
        endif;
        $stmt->close();

        if ($emptyDeck !== true) :
            if ($format !== "variable") :
                $filename = 'deck_' . $deckNumber . '.txt';
                $tmpName = tempnam(sys_get_temp_dir(), 'deck_' . $deckNumber);
                file_put_contents($tmpName, $textfile);
            endif;

            if ($format === "download") :
                header('Content-Description: File Transfer');
                header('Content-Type: text/txt');
                header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($tmpName));

                ob_clean();
                flush();
                readfile($tmpName);
                if (isset($tmpName)) :
                    unlink($tmpName);
                endif;
            elseif ($format === "email") :
                if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                    $mail = new MyPHPMailer(true, $smtpParameters, $this->serverEmail, $this->logfile);

                    $subject = "Deck export";
                    $emailbody = "Your deck export ($deckName) is attached.";
                    $emailaltbody = "Your deck export ($deckName) is attached.";
                    $mailresult = $mail->sendEmail(
                        $this->userEmail,
                        true,
                        $subject,
                        $emailbody,
                        $emailaltbody,
                        $tmpName,
                        $filename
                    );
                else :
                    $this->message->logMessage(
                        '[NOTICE]',
                        "Email disabled; deck export email not sent to {$this->userEmail}"
                    );
                    $mailresult = false;
                endif;
                if (isset($tmpName)) :
                    unlink($tmpName);
                endif;
                if ($mailresult === true) :
                    return true;
                else :
                    return false;
                endif;
            elseif ($format === "bulk") :
                // Generate a unique name for the zip file in the temp directory
                $sanitizedEmail = str_replace('@', '_', $this->userEmail);
                $currentDate = date('d-M-Y');
                if ($zipFilePath === null) :
                    $zipFilename = "Decks-{$sanitizedEmail}-{$currentDate}.zip";
                    $zipFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFilename;
                endif;
                $zip = new \ZipArchive();

                // Open the zip archive
                if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== true) {
                    $this->message->logMessage('[ERROR]', "Cannot create or open ZIP file $zipFilePath");
                    return false;
                }

                // Add the deck text file to the zip
                $zip->addFile($tmpName, $filename);

                // Close the zip archive
                $zip->close();

                // Clean up the temporary text file
                if (file_exists($tmpName)) {
                    unlink($tmpName);
                }

                // Return the zip file name to the caller
                return $zipFilePath;
            elseif ($format === "variable") :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Variable return called for deck '$deckNumber', returning $textfile"
                );
                return $textfile;
            else :
            endif;
        else :
        endif;
    }

    public function exportMissing($textdata, $filename)
    {

        // Create a temporary file
        $tmpName = tempnam(sys_get_temp_dir(), 'data');
        if ($tmpName === false) :
            $this->message->logMessage('[ERROR]', 'Failed to create temporary file');
            return false;
        endif;

        // Write text data to the file
        if (file_put_contents($tmpName, $textdata) === false) :
            $this->message->logMessage('[ERROR]', 'Failed to write to temporary file');
            unlink($tmpName);
            return false;
        endif;

        // Send headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tmpName));

        // Clean output buffer and read the file
        ob_clean();
        flush();
        readfile($tmpName);

        // Remove the temporary file
        unlink($tmpName);
        return true;
    }

    public function __toString()
    {
        $this->message->logMessage("[ERROR]", "Called as string");
        return "Called as a string";
    }

    public function mtgCardCopyLimit($card_type, $ability, $f1_ability = null, $f2_ability = null, $decktype = null)
    {
        if ($decktype === 'Wishlist') :
            return null;
        endif;

        if ($card_type !== null && str_contains($card_type, 'Basic Land')) :
            return null;
        endif;

        $ability_candidates = array_filter(
            [
                $ability,
                $f1_ability,
                $f2_ability
            ]
        );

        foreach ($ability_candidates as $ability_text) :
            foreach ($this->anyQuantity as $rule) :
                if (str_contains($ability_text, $rule)) :
                    return null;
                endif;
            endforeach;

            $pattern = '/A deck can have up to ([a-z0-9-]+) cards named/i';
            if (preg_match($pattern, $ability_text, $matches)) :
                $limit_text = strtolower(str_replace('-', ' ', $matches[1]));
                if (ctype_digit($limit_text)) :
                    return (int) $limit_text;
                endif;
                $word_map = [
                    'one' => 1,
                    'two' => 2,
                    'three' => 3,
                    'four' => 4,
                    'five' => 5,
                    'six' => 6,
                    'seven' => 7,
                    'eight' => 8,
                    'nine' => 9,
                    'ten' => 10,
                    'eleven' => 11,
                    'twelve' => 12,
                    'thirteen' => 13,
                    'fourteen' => 14,
                    'fifteen' => 15,
                    'sixteen' => 16,
                    'seventeen' => 17,
                    'eighteen' => 18,
                    'nineteen' => 19,
                    'twenty' => 20
                ];
                if (isset($word_map[$limit_text])) :
                    return $word_map[$limit_text];
                endif;
            endif;
        endforeach;

        return 4;
    }

    public function cardLegalDBField($decktype)
    {
        global $deck_legality_map;

        $this->message->logMessage('[DEBUG]', "Looking up db_field for legality for deck type '$decktype'");
        $index = array_search("$decktype", array_column($deck_legality_map, 'decktype'));
        if ($index !== false) :
            $db_field = $deck_legality_map[$index]['db_field'];
        endif;
        $this->message->logMessage('[DEBUG]', "Deck type '$decktype' has legality in '$db_field'");
        return $db_field;
    }

    public function deckLegalList($deckNumber, $deck_type, $db_field)
    {
        $this->message->logMessage(
            '[DEBUG]',
            "Getting deck legality list for $deck_type deck '$deckNumber' (using db_field '$db_field')"
        );
        $sql = "SELECT cardnumber FROM deckcards WHERE decknumber = ?";
        $this->message->logMessage('[DEBUG]', "Looking up SQL: $sql");
        $sqlresult = $this->db->execute_query($sql, [$deckNumber]);
        if ($sqlresult === false) :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        else :
            $i = 0;
            $record = array();
            while ($row = $sqlresult->fetch_assoc()) :
                $record[$i] = $row['cardnumber'];
                $i = $i + 1;
            endwhile;
        endif;
        $list = array();
        $p = 0;
        foreach ($record as $value) :
            $sql2 = "SELECT $db_field FROM cards_scry WHERE id = ? LIMIT 1";
            $sqlresult2 = $this->db->execute_query($sql2, [$value]);
            if ($sqlresult2 === false) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": SQL failure: " . $this->db->error
                );
            else :
                $row2 = $sqlresult2->fetch_array(MYSQLI_ASSOC);
                $legal = $row2["$db_field"];
            endif;
            $list[$p]['id'] = $value;
            $list[$p]['legality'] = $legal;
            $p = $p + 1;
        endforeach;
        return $list;
    }
}
