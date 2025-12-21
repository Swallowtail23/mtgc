<?php

/*
Version:     6.1
Date:        21/12/25
Name:        importexport.class.php
Purpose:     Import/export management class.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, PSR1.Files.SideEffects.FoundWithSymbols


class ImportExport
{
    /**
    * @var mysqli
    */
    private $db;
    private $logfile;
    private $userEmail;
    private $serverEmail;
    private $message;
    private $siteTitle;
    private $batchedCardIds = []; // Array to store batched cards to add

    public function __construct($db, $logfile, $userEmail, $serverEmail, $siteTitle = null)
    {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->userEmail = $userEmail;
        $this->serverEmail = $serverEmail;
        $this->message = new \MTG\Core\Message($this->logfile);
        $this->siteTitle = $siteTitle ?: $GLOBALS['siteTitle'];
    }

    public function exportCollectionToCsv(
        $table,
        $myURL,
        $smtpParameters,
        $format = 'echo',
        $filename = 'export.csv',
        $userName = '',
        $userEmail = '',
        $extraAttachments = []
    ) {
        $out = $this->buildCollectionCsv($table);
        if ($out === false) :
            return false;
        endif;

        if ($format === 'echo') :
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Content-Length: " . strlen($out));
                header("Content-type: text/x-csv; charset=UTF-8");
                header("Content-Disposition: attachment; filename=$filename");
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                echo $out;
        elseif ($format === 'email') :
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                if (!empty($extraAttachments)) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Adding " . count($extraAttachments) . " extra attachments to collection export email"
                    );
                endif;
                $mail = new MyPHPMailer(true, $smtpParameters, $this->serverEmail, $this->logfile);

                $tempFile = tempnam(sys_get_temp_dir(), 'export_');
                file_put_contents($tempFile, $out);

                $siteTitleEsc = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
                $subject = "Collection export";
                $emailbody = "Your $siteTitleEsc export is attached. <br><br>"
                    . "Opt out of automated emails in your profile at "
                    . "<a href='$myURL/profile.php'>your $siteTitleEsc profile page</a>";
                $emailaltbody = "Your $this->siteTitle export is attached.\r\n\r\n"
                    . "Opt out of automated emails in your profile at your $this->siteTitle profile page "
                    . "($myURL/profile.php)\r\n\r\n";
                $mailresult = $mail->sendEmail(
                    $this->userEmail,
                    true,
                    $subject,
                    $emailbody,
                    $emailaltbody,
                    $tempFile,
                    $filename,
                    $extraAttachments
                );
                if (isset($tempFile)) :
                    unlink($tempFile);
                endif;
                if ($mailresult === true) :
                    return true;
                else :
                    return $mailresult ?: false;
                endif;
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; collection export email not sent to {$this->userEmail}"
                );
                return false;
            endif;
        elseif ($format === 'weekly' && $userName !== '' && $userEmail !== '') :
            if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
                if (!empty($extraAttachments)) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Adding " . count($extraAttachments) . " extra attachments to weekly export email"
                    );
                endif;
                $mail = new MyPHPMailer(true, $smtpParameters, $this->serverEmail, $this->logfile);

                $tempFile = tempnam(sys_get_temp_dir(), 'export_');
                file_put_contents($tempFile, $out);

                $siteTitleEsc = htmlspecialchars($this->siteTitle, ENT_QUOTES, 'UTF-8');
                $subject = "$this->siteTitle weekly collection export";
                $emailbody = "Hi $userName, your weekly collection export from $siteTitleEsc is attached."
                    . "<br><br>Opt out of automated emails in your profile at "
                    . "<a href='$myURL/profile.php'>your $siteTitleEsc profile page</a>";
                $emailaltbody = "Hi $userName, please see attached your weekly collection export from "
                    . "$this->siteTitle.\r\n\r\nOpt out of automated emails in your profile at your "
                    . "$this->siteTitle profile page ($myURL/profile.php)\r\n\r\n";
                $mailresult = $mail->sendEmail(
                    $userEmail,
                    true,
                    $subject,
                    $emailbody,
                    $emailaltbody,
                    $tempFile,
                    $filename,
                    $extraAttachments
                );
                if (isset($tempFile)) :
                    unlink($tempFile);
                endif;
                if ($mailresult === true) :
                    return true;
                else :
                    return $mailresult ?: false;
                endif;
            else :
                $this->message->logMessage(
                    '[NOTICE]',
                    "Email disabled; weekly collection email not sent to $userEmail"
                );
                return false;
            endif;
        endif;
    }

    public function buildCollectionCsv($table)
    {
        $csv_terminated = "\n";
        $csv_separator = ",";
        $csv_enclosed = '"';
        $csv_escaped = "\\";
        $table = $this->db->real_escape_string($table);
        $sql = "SELECT setcode,number_import,name,lang,normal,$table.foil,$table.etched,$table.id as scryfall_id
            FROM $table JOIN cards_scry ON $table.id = cards_scry.id
            WHERE (($table.normal > 0) OR ($table.foil > 0) OR ($table.etched > 0))";
        $this->message->logMessage('[NOTICE]', "Running Export Collection to CSV: $sql");

        // Gets the data from the database
        $result = $this->db->query($sql);
        if ($result === false) :
            throw new Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
            return false;
        else :
            $fields = method_exists($result, 'fetch_fields') ? $result->fetch_fields() : [];
            if (empty($fields)) :
                $fields_cnt = $result->field_count;
                for ($i = 0; $i < $fields_cnt; $i++) :
                    $fields[] = mysqli_fetch_field_direct($result, $i);
                endfor;
            endif;

            $fields_cnt = count($fields);
            $this->message->logMessage('[DEBUG]', "Number of fields: $fields_cnt");
            $schema_insert = '';
            foreach ($fields as $fieldinfo) :
                $l = $csv_enclosed
                    . str_replace(
                        $csv_enclosed,
                        $csv_escaped . $csv_enclosed,
                        stripslashes($fieldinfo->name)
                    )
                    . $csv_enclosed;
                $schema_insert .= $l;
                $schema_insert .= $csv_separator;
            endforeach;

            $out = trim(substr($schema_insert, 0, -1));
            $out .= $csv_terminated;

            // Format the data
            while ($row = $result->fetch_row()) :
                $schema_insert = '';
                for ($j = 0; $j < $fields_cnt; $j++) :
                    if ($row[$j] == '0' || $row[$j] != '') :
                        if ($csv_enclosed == '') :
                            $schema_insert .= $row[$j];
                        else :
                            $schema_insert .= $csv_enclosed .
                            str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                        endif;
                    else :
                        $schema_insert .= '';
                    endif;
                    if ($j < $fields_cnt - 1) :
                        $schema_insert .= $csv_separator;
                    endif;
                endfor;
                $out .= $schema_insert;
                $out .= $csv_terminated;
            endwhile;
            $out .= $csv_terminated;
        endif;

        return $out;
    }

    public function importCollectionRegex($filename, $mytable, $importType, $userEmail, $serverEmail)
    {
        // Import type = add, replace or remove
        // Import format = 'regex'
        // 'regex' may have no header row, and content like '1 All Is Dust [M3C 152]'
        // or any other style that inputInterpreter() can assess
        $importFormat = 'regex';
        global $noQuickAddLayouts;
        $this->message->logMessage('[DEBUG]', "Import starting in '$importType' mode, '$importFormat' format");

        $handle = fopen($filename, "r");
        $fileContent = fread($handle, filesize($filename));
        $i = 0;
        $count = 0;
        $total = 0;
        $warningSummary = '';
        $lines = explode("\n", $fileContent);
        $qtyLines = count($lines);
        $this->message->logMessage('[DEBUG]', "Regex deck import has $qtyLines lines");

        foreach ($lines as $line) :
            $rowNumber = $i + 1;
            $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Reviewing line");
            $linestring = htmlspecialchars($line, ENT_NOQUOTES, 'UTF-8');
            $interpretedString = inputInterpreter($linestring);
            if ($interpretedString === 'header') :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Header row");
            elseif ($interpretedString === 'empty line') :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Empty row");
            elseif (
                $interpretedString === false
                || (
                    empty($interpretedString['uuid'])
                    && (empty($interpretedString['set']) || empty($interpretedString['number']))
                )
            ) :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Not enough usable card info (or empty row)");
                $newWarning = "$rowNumber, Not enough info to identify card (row detail: '$line') \n";
                $warningSummary = $warningSummary . $newWarning;
            else :
                $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Possible card");
                $count = $count + 1; //Increment unique card row count

                // UUID
                if (isset($interpretedString['uuid']) and $interpretedString['uuid'] !== '') :
                    $quickAddUuid = $interpretedString['uuid'];
                else :
                    $quickAddUuid = '';
                endif;

                // Quantity
                if (isset($interpretedString['normal']) and $interpretedString['normal'] !== '') :
                    $quickaddnormal = $interpretedString['normal'];
                else :
                    $quickaddnormal = 0;
                endif;
                if (isset($interpretedString['foil']) and $interpretedString['foil'] !== '') :
                    $quickaddfoil = $interpretedString['foil'];
                else :
                    $quickaddfoil = 0;
                endif;
                if (isset($interpretedString['etched']) and $interpretedString['etched'] !== '') :
                    $quickaddetched = $interpretedString['etched'];
                else :
                    $quickaddetched = 0;
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
                $this->message->logMessage(
                    '[DEBUG]',
                    "Row: $rowNumber: Quick add interpreted as: "
                        . "Normal: [$quickaddnormal] "
                        . "Foil: [$quickaddfoil] "
                        . "Etched: [$quickaddetched] "
                        . " x Card: [$quickAddCard] Set: [$quickAddSet] "
                    . "Collector number: [$quickAddNumber] Language: [$quickAddLang] UUID: [$quickAddUuid]"
                );
                $stmt = null;

                if ($quickAddUuid !== '' && validUUID($quickAddUuid) !== false) :
                    // Card UUID provided and valid UUID
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided UUID: [$quickAddUuid]"
                    );
                    $query = "SELECT id,finishes,name,setcode,number FROM cards_scry WHERE id = ? LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = [$quickAddUuid];
                    $stmt->bind_param('s', $params[0]);
                elseif (
                    $quickAddCard !== ''
                    and $quickAddSet !== ''
                    and $quickAddNumber !== ''
                    and $quickAddLang !== ''
                ) :
                    // Card name, setcode, and collector number provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided name, set, number and specified language"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? 
                                                                   OR printed_name = ? OR f1_printed_name = ? OR 
                                                                   f2_printed_name = ? OR flavor_name = ? OR 
                                                                   f1_flavor_name = ? OR f2_flavor_name = ?) AND 
                                                                   setcode = ? AND number_import = ? AND 
                                                                   lang LIKE ? ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickAddCard);
                    array_push($params, $quickAddSet, $quickAddNumber, $quickAddLang);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber !== '') :
                    // Card name, setcode, and collector number provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided name, set, number and primary language"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? 
                                                                   OR printed_name = ? OR f1_printed_name = ? OR 
                                                                   f2_printed_name = ? OR flavor_name = ? OR 
                                                                   f1_flavor_name = ? OR f2_flavor_name = ?) AND 
                                                                   setcode = ? AND number_import = ? AND 
                                                                   primary_card = 1 ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickAddCard);
                    array_push($params, $quickAddSet, $quickAddNumber);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                elseif ($quickAddCard !== '' and $quickAddSet !== '' and $quickAddNumber === '') :
                    // Card name and setcode provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided name, set"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE (name = ? OR f1_name = ? OR f2_name = ? 
                                                                   OR printed_name = ? OR f1_printed_name = ? OR 
                                                                   f2_printed_name = ? OR flavor_name = ? OR 
                                                                   f1_flavor_name = ? OR f2_flavor_name = ?) AND 
                                                                   setcode = ? AND primary_card = 1 
                                                                   ORDER BY release_date DESC, number ASC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = array_fill(0, 9, $quickAddCard);
                    array_push($params, $quickAddSet);
                    $params = array_merge($params, $noQuickAddLayouts);
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                elseif ($quickAddCard === '' and $quickAddSet !== '' and $quickAddNumber !== '') :
                    // Card name not provided, setcode, and collector number provided
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Row: $rowNumber: Quick add proceeding with provided set and number"
                    );
                    $query = "SELECT id,finishes FROM cards_scry WHERE setcode = ? AND number_import = ? 
                                                               AND primary_card = 1 ORDER BY release_date DESC LIMIT 1";
                    $stmt = $this->db->prepare($query);
                    $params = [$quickAddSet, $quickAddNumber];
                    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                else :
                    // Not enough info, cannot add
                    $this->message->logMessage(
                        '[NOTICE]',
                        "Row: $rowNumber: Quick add - Not enough info to identify a card to add"
                    );
                    $cardtoadd = 'cardnotfound';
                    $newWarning = "$rowNumber, Not enough info to identify card (row detail: '$line') \n";
                    $warningSummary = $warningSummary . $newWarning;
                endif;
                if ($stmt !== null and $stmt->execute()) :
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) :
                        $row = $result->fetch_assoc();
                        $stmt->close();
                        $cardtoadd = $row['id'];
                        $finishes = $row['finishes'];
                        $this->message->logMessage(
                            '[DEBUG]',
                            "Row: $rowNumber: Quick add result: UUID result is '$cardtoadd', adding to batch"
                        );
                        $this->batchedCardIds[] = [
                            'line' => $line,
                            'row' => $rowNumber,
                            'id' => $cardtoadd,
                            'finishes' => $finishes,
                            'normal' => $quickaddnormal,
                            'foil' => $quickaddfoil,
                            'etched' => $quickaddetched,
                        ];
                        $total = $total + $quickaddnormal + $quickaddfoil + $quickaddetched;
                    else :
                        $stmt->close();
                        $this->message->logMessage('[NOTICE]', "Row: $rowNumber: Quick add - Card not found");
                        $cardtoadd = 'cardnotfound';
                        $newWarning = "$rowNumber, Card not found (row detail: '$line') \n";
                        $warningSummary = $warningSummary . $newWarning;
                    endif;
                else :
                    $stmt->close();
                    $this->message->logMessage('[ERROR]', "Quick add - SQL error: " . $stmt->error);
                    $cardtoadd = 'cardnotfound';
                    $newWarning = "$rowNumber, Unknown error (row detail: '$line') \n";
                    $warningSummary = $warningSummary . $newWarning;
                endif;
            endif;
            $i = $i + 1;
        endforeach;
        if ($count === 0) :
            $this->message->logMessage('[DEBUG]', "No cards in the file to import");
            fclose($handle);
            ?>
            <script type="text/javascript">
                alert('WARNING\n\nNo cards in the file to import');
            </script> <?php
            return 'emptyfile';
        endif;

        // Finalise any warnings from file scan phase
        if ($warningSummary === '') :
            $warningSummary = "Input file scan warnings or errors\n\nNone\n\n";
        else :
            $warningSummary = "Input file scan warnings or errors (Row number, Warning/error)\n\n" . $warningSummary;
        endif;

        // If batched card array is not empty, perform batch insert
        if (!empty($this->batchedCardIds)) :
            $batchOutput = $this->addCardsBatch($mytable, $importType, $count, $total, $this->batchedCardIds);
            if ($batchOutput['warnings'] !== 'none') :
                $warningSummary = $warningSummary . $batchOutput['warnings'];
            else :
                //
            endif;
            $actionedCards = $batchOutput['total'];
            $actionedRows = $batchOutput['batchRows'];
            // Clear array after batch insert
            $this->batchedCardIds = [];
        endif;
        // Remove any orphan rows left after removals
        $this->deleteOrphans($mytable);

        fclose($handle);
        $summary = "Import done - $count unique cards, $importType total: $total.";
        print $summary;
        $from = "From: $serverEmail\r\nReturn-path: $serverEmail";
        $subject = "Import failures / warnings";
        $message = "$warningSummary \n \n$summary";
        if (isset($GLOBALS['emailEnabled']) && $GLOBALS['emailEnabled'] === true) :
            mail($userEmail, $subject, $message, $from);
        else :
            $this->message->logMessage(
                '[NOTICE]',
                "Email disabled; import warning/summary email not sent to $userEmail"
            );
        endif;
                $this->message->logMessage(
                    '[NOTICE]',
                    "Import process run with '$importType' ($actionedRows of $count card rows actioned, "
                        . "$actionedCards of $total cards actioned)"
                );
        if ($actionedCards === 0) :?>
            <script type="text/javascript">
                alert(
                    'WARNING\n\nNo actions were taken, check your file\n\nEmail has been sent to you with '
                    + 'warnings/error details'
                );
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        elseif ($count === $actionedRows && $total === $actionedCards) : ?>
            <script type="text/javascript">
                (function() {
                    fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
                })();

                alert(
                    'Import type: <?php echo $importType;?>\n<?php echo $count;?> card rows found in file with '
                    + '<?php echo $total;?> cards\nAll card rows and cards actioned\n\nCollection value is now '
                    + 'being resynced, this can take some time for large collections, please wait'
                );
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        else : ?>
            <script type="text/javascript">
                (function() {
                    fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
                })();

                alert(
                    'Import type: <?php echo $importType;?>\n<?php echo $count;?> card rows found in file with '
                    + '<?php echo $total;?> cards\n<?php echo $actionedRows;?> card rows actioned with '
                    + '<?php echo $actionedCards;?> cards\n\nDetails have been emailed to you with '
                    + 'warnings/error details\n\nCollection value is now being resynced, this can take some time '
                    + 'for large collections, please wait'
                );
                window.onload=function(){document.body.style.cursor='wait';}
            </script> <?php
        endif;
    }

    public function addCardsBatch($mytable, $importType, $count, $total, $batchedCardIds)
    {
        $this->message->logMessage(
            '[DEBUG]',
            "Batch import process called with '$importType' ($count unique cards, $total total cards)"
        );
        $values = [];
        $placeholders = [];
        $batchWarnings = '';

        foreach ($batchedCardIds as $key => $batchedCard) :
            $line = $batchedCard['line'];
            $rowNumber = $batchedCard['row'];
            $id = $batchedCard['id'];
            $finishes = json_decode($batchedCard['finishes'], true);
            $cardtype = cardTypes($finishes);
            $normal = $batchedCard['normal'];
            $foil = $batchedCard['foil'];
            $etched = $batchedCard['etched'];
            $qty = $normal + $foil + $etched;

            // Validate card types, 'continue' out of this 'foreach' if there are any issues, logging an error
            if ($normal > 0 && !str_contains($cardtype, 'normal')) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Row: $rowNumber: Batch import finish mapping error (normal) - skipping row"
                );
                $newWarning = "$rowNumber, Normal qty cannot be mapped to card without normal finish - row skipped "
                    . "(row detail: '$line') \n";
                $batchWarnings = $batchWarnings . $newWarning;
                $total = $total - $qty;         // Deduct cards from total card count
                $count = $count - 1;            // Deduct the entire row from the row count
                unset($batchedCardIds[$key]);   // Remove this row from the batch
                continue;
            endif;
            if ($foil > 0 && !str_contains($cardtype, 'foil')) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Row: $rowNumber: Batch import finish mapping error (foil) - skipping row"
                );
                $newWarning = "$rowNumber, Foil qty cannot be mapped to card without foil finish - row skipped "
                    . "(row detail: '$line') \n";
                $batchWarnings = $batchWarnings . $newWarning;
                $total = $total - $foil;
                $count = $count - 1;
                unset($batchedCardIds[$key]);
                continue;
            endif;
            if ($etched > 0 && !str_contains($cardtype, 'etched')) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Row: $rowNumber: Batch import finish mapping error (etched) - skipping row"
                );
                $newWarning = "$rowNumber, Etched qty cannot be mapped to card without etched finish - row skipped "
                    . "(row detail: '$line') \n";
                $batchWarnings = $batchWarnings . $newWarning;
                $total = $total - $etched;
                $count = $count - 1;
                unset($batchedCardIds[$key]);
                continue;
            endif;
            // Add each card to the batch
            $this->message->logMessage('[DEBUG]', "Row: $rowNumber: Batch import - adding to batch ('$line')");
            $values[] = "($id, $normal, $foil, $etched)";
            $placeholders[] = '(?, ?, ?, ?)';
        endforeach;
        $this->message->logMessage('[DEBUG]', "Batch import warnings: '$batchWarnings'");
        if (!empty($values)) :
            $this->message->logMessage('[DEBUG]', "Batch import: Assessing import type variations ($importType)");
            $placeholdersString = implode(', ', $placeholders);
            if ($importType === 'add') :
                $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString 
                            ON DUPLICATE KEY 
                            UPDATE 
                            normal = normal + VALUES(normal), 
                            foil = foil + VALUES(foil), 
                            etched = etched + VALUES(etched)";
            elseif ($importType === 'subtract') :
                $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString 
                            ON DUPLICATE KEY 
                            UPDATE 
                            normal = greatest(normal - VALUES(normal),0), 
                            foil = greatest(foil - VALUES(foil),0),  
                            etched = greatest(etched - VALUES(etched),0)";
            elseif ($importType === 'replace') :
                $query = "INSERT INTO $mytable (id, normal, foil, etched) VALUES $placeholdersString 
                            ON DUPLICATE KEY 
                            UPDATE 
                            normal = VALUES(normal), 
                            foil = VALUES(foil),  
                            etched = VALUES(etched)";
            endif;
            // Bind parameters and execute the query
            $stmt = $this->db->prepare($query);

            // Generate the type definition string dynamically based on the number of batched cards
            $typeDefinition = str_repeat('siii', count($batchedCardIds));

            // Prepare an array with the values to be bound
            $bindValues = [];
            foreach ($batchedCardIds as $batchedCard) :
                $bindValues[] = $batchedCard['id'];
                $bindValues[] = $batchedCard['normal'];
                $bindValues[] = $batchedCard['foil'];
                $bindValues[] = $batchedCard['etched'];
            endforeach;

            // Bind the parameters dynamically
            $stmt->bind_param($typeDefinition, ...$bindValues);
            if ($stmt->execute()) :
                $this->message->logMessage('[DEBUG]', "importCollectionRegex batch process completed");
                $stmt->close();
                if ($batchWarnings === '') :
                    $batchWarnings = "\nBatch import warnings or errors\n\nNone\n\n";
                else :
                    $batchWarnings = "\nBatch import warnings or errors (Row number, Warning/error)\n\n"
                        . $batchWarnings;
                endif;
                return array('warnings' => $batchWarnings, 'total' => $total, 'batchRows' => $count);
            else :
                $this->message->logMessage('[ERROR]', "Error executing batch insert query: " . $stmt->error);
                $stmt->close();
            endif;

            $stmt->close();
        else :
            $this->message->logMessage('[DEBUG]', "importCollectionRegex batch process completed (no writes made)");
            if ($batchWarnings === '') :
                $batchWarnings = "\nBatch import warnings or errors\n\nNone\n\n";
            else :
                    $batchWarnings = "\nBatch import warnings or errors (Row number, Warning/error)\n\n"
                        . $batchWarnings;
            endif;
                return array('warnings' => $batchWarnings, 'total' => $total, 'batchRows' => $count);
        endif;
    }

    private function deleteOrphans($mytable)
    {
        $queryString = "DELETE FROM $mytable WHERE COALESCE(normal,0) + COALESCE(foil,0) + COALESCE(etched,0) = 0";
        if ($query = $this->db->execute_query($queryString)) :
            $this->message->logMessage('[NOTICE]', "Deleted {$this->db->affected_rows} orphan rows");
        else :
            throw new Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;
    }

    public function __toString()
    {
        $this->message->logMessage("[ERROR]", "Called as string");
        return "Called as a string";
    }
}
// phpcs:enable
