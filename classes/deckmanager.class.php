<?php
/* Version:     1.1
    Date:       11/12/23
    Name:       deckManager.class.php
    Purpose:    Class for quickAdd and deck import
    Notes:      ProcessInput() called with deck number and input string
 *              - Interprets whether it is single or multiple line
 *              - If single line, calls quickadd() in single line mode
 *              Quickadd() then called
 *              - Interprets the string and gets the card ID
 *              - returns a card ID, or cardnotfound, or cardnotadded
    To do:      -
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2023 Simon Wilson
    
 *  1.0         25/11/23
                Initial version
 * 
 *  1.1         11/12/23
 *              Move deck-related methods from functions
 *              Move to single instance of Message class
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class DeckManager {
    private $db;
    private $logfile;
    private $batchedCardIds = []; // Array to store batched cards to add
    private $message;
    
    public function __construct($db, $logfile) {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new Message();
    }
    
    // processInput can handle either single-line or multi-line inputs, using quickadd to process. Multi-line inputs are batched for combined data write by addDeckCardsBatch
    public function processInput($decknumber, $input) {
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ProcessInput called for deck $decknumber with '$input'",$this->logfile);
        // Check if input is multiline
        $lines = explode("\n", $input);
        if (count($lines) > 1):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Multi-line input, calling quickadd in batch mode",$this->logfile);
            foreach ($lines as $line):
                $line = trim($line);
                $this->quickadd($decknumber, $line, true); // Set third parameter to true for batching
            endforeach;
        else:
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Single-line input, calling quickadd in single-line mode",$this->logfile);
            $quickaddresult = $this->quickadd($decknumber, $input);
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Result: $quickaddresult",$this->logfile);
            return $quickaddresult;
        endif;
        // If batched card array is not empty, perform batch insert
        if (!empty($this->batchedCardIds)):
            $this->addDeckCardsBatch($decknumber, $this->batchedCardIds);
            // Clear array after batch insert
            $this->batchedCardIds = [];
        endif;
    }
    
    public function quickadd($decknumber,$get_string,$batch = false)
    {
        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Quick add interpreter called for deck $decknumber with '$get_string' (batch mode = $batch)",$this->logfile);
        $quickaddstring = htmlspecialchars($get_string,ENT_NOQUOTES);
        preg_match("~^(\d*)\s*([^[\]]+)?(?:\[\s*([^\]\s]+)(?:\s*([^\]\s]+(?:\s+[^\]\s]+)*)?)?\s*\])?~", $quickaddstring, $matches);
        // Quantity
        if (isset($matches[1]) AND $matches[1] !== ''):
            $quickaddqty = $matches[1];
        else:
            $quickaddqty = 1;
        endif;
        // Name
        if (isset($matches[2])):
            $quickaddcard = trim($matches[2]);
        else:
            $quickaddcard = '';
        endif;
        // Set
        if (isset($matches[3])):
            $quickaddset = strtoupper($matches[3]);
        else:
            $quickaddset = '';
        endif;
        // Collector number
        if (isset($matches[4])):
            $quickaddNumber = $matches[4];
        else:
            $quickaddNumber = '';
        endif;
        $quickaddcard = htmlspecialchars_decode($quickaddcard,ENT_QUOTES);
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Quick add called with string '$quickaddstring', interpreted as: Qty: [$quickaddqty] x Card: [$quickaddcard] Set: [$quickaddset] Collector number: [$quickaddNumber]",$this->logfile);
        $stmt = null;
        if ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
            // Card name, setcode, and collector number provided
            $query = "SELECT id FROM cards_scry WHERE (name = ? OR
                                                       f1_name = ? OR 
                                                       f2_name = ? OR 
                                                       printed_name = ? OR 
                                                       f1_printed_name = ? OR 
                                                       f2_printed_name = ? OR 
                                                       flavor_name = ? OR
                                                       f1_flavor_name = ? OR 
                                                       f2_flavor_name = ?) AND 
                                                       setcode = ? AND number_import = ? AND `layout` NOT IN ('token','double_faced_token','emblem','meld') ORDER BY release_date DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $params = array_fill(0, 9, $quickaddcard);
            $params[] = $quickaddset;
            $params[] = $quickaddNumber;
            $stmt->bind_param("sssssssssss", ...$params);
        elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber === ''):
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
                                                       setcode = ? AND `layout` NOT IN ('token','double_faced_token','emblem','meld') ORDER BY release_date DESC, number ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $params = array_fill(0, 9, $quickaddcard);
            $params[] = $quickaddset;
            $stmt->bind_param("ssssssssss", ...$params);
        elseif ($quickaddcard !== '' AND $quickaddset === ''):
            // Card name only provided, or with a number (but useless without setcode) - just grab a name match
            $query = "SELECT id FROM cards_scry WHERE (name = ? OR
                                                       f1_name = ? OR 
                                                       f2_name = ? OR 
                                                       printed_name = ? OR 
                                                       f1_printed_name = ? OR 
                                                       f2_printed_name = ? OR 
                                                       flavor_name = ? OR
                                                       f1_flavor_name = ? OR 
                                                       f2_flavor_name = ?) AND 
                                                       `layout` NOT IN ('token','double_faced_token','emblem','meld') ORDER BY release_date DESC, number ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $params = array_fill(0, 9, $quickaddcard);
            $stmt->bind_param("sssssssss", ...$params);
        elseif ($quickaddcard === '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
            // Card name not provided, setcode, and collector number provided
            $query = "SELECT id FROM cards_scry WHERE setcode = ? AND number_import = ? AND `layout` NOT IN ('token','double_faced_token','emblem','meld') ORDER BY release_date DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ss", $quickaddset, $quickaddNumber);
        else:
            // Not enough info, cannot add
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - Not enough info to identify a card to add",$this->logfile);
            $cardtoadd = 'cardnotfound';
            return $cardtoadd;
        endif;

        if ($stmt !== null AND $stmt->execute()):
            $result = $stmt->get_result();
            if ($result->num_rows > 0):
                $row = $result->fetch_assoc();
                $stmt->close();
                $cardtoadd = $row['id'];
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Quick add result: $cardtoadd",$this->logfile);
                if (!$batch):
                    // Call addDeckCard only if not in batch mode
                    $addresult = $this->addDeckCard($decknumber, $cardtoadd, "main", "$quickaddqty");
                    return $addresult;
                else:
                    // In batch mode, store the card ID and quantity in the batchedCardIds array
                    $this->batchedCardIds[] = ['id' => $cardtoadd, 'qty' => $quickaddqty];
                endif;
            else:
                $stmt->close();
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - Card not found",$this->logfile);
                $cardtoadd = 'cardnotfound';
                return $cardtoadd;
            endif;
        else:
            $stmt->close();
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - SQL error: " . $stmt->error, $this->logfile);
            $cardtoadd = 'cardnotfound';
            return $cardtoadd;
        endif;
    }

    public function addDeckCardsBatch($decknumber, $batchedCardIds) {
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": deckManager batch process called",$this->logfile);
        $values = [];
        $placeholders = [];

        foreach ($batchedCardIds as $batchedCard):
            $id = $batchedCard['id'];
            $qty = $batchedCard['qty'];
            // Add each card to the batch
            $values[] = "($decknumber, $id, $qty)";
            $placeholders[] = '(?, ?, ?)';
        endforeach;

        if (!empty($values)):
            $valuesString = implode(', ', $values);
            $placeholdersString = implode(', ', $placeholders);

            // Assuming you have a method for executing SQL queries, prepare the INSERT query
            $query = "INSERT INTO deckcards (decknumber, cardnumber, cardqty) VALUES $placeholdersString 
                        ON DUPLICATE KEY UPDATE cardqty = VALUES(cardqty)";

            // Bind parameters and execute the query
            $stmt = $this->db->prepare($query);

            // Generate the type definition string dynamically based on the number of batched cards
            $typeDefinition = str_repeat('isi', count($batchedCardIds));

            // Prepare an array with the values to be bound
            $bindValues = [];
            foreach ($batchedCardIds as $batchedCard):
                $bindValues[] = $decknumber;
                $bindValues[] = $batchedCard['id'];
                $bindValues[] = $batchedCard['qty'];
            endforeach;

            // Bind the parameters dynamically
            $stmt->bind_param($typeDefinition, ...$bindValues);

            if ($stmt->execute()) :
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": deckManager batch process completed",$this->logfile);
            else :
                $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Error executing batch insert query: ".$stmt->error, $this->logfile);
            endif;

            $stmt->close();
        endif;
    }
    
    public function deckOwnerCheck($deck,$user)
    {
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ". __LINE__,"Function ".__FUNCTION__.": Checking deck ownership: $deck, $user", $this->logfile);
        $sql = "SELECT deckname, owner FROM decks WHERE decknumber = ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$deck]);
        if ($result === false):
            trigger_error('[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $this->db->error, E_USER_ERROR);
        else:
            if ($row = $result->fetch_assoc()):
                $deckname = $row['deckname'];
                $owner = $row['owner'];
                $this->message->MessageTxt('[DEBUG]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Deck $deck ($deckname) belongs to owner $owner (called by $user)", $this->logfile);
                if ($owner != $user):
                    $this->message->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Deck {$row['deckname']} does not belong to user $user, returning to deck page", $this->logfile);
                    return false;
                else:
                    return $deckname;
                endif;
            else:
                $this->message->MessageTxt('[ERROR]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": No deck found for deck $deck, returning to deck page", $this->logfile);
                return false;
            endif;
        endif;
    }

    public function deckCardCheck($card, $user)
    {
        $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking to see what decks this card is in for user $user...", $this->logfile);

        $sql = "SELECT deckcards.decknumber, deckcards.cardqty, deckcards.sideqty, decks.deckname 
                FROM deckcards 
                LEFT JOIN decks ON deckcards.decknumber = decks.decknumber 
                WHERE cardnumber = ? AND owner = ?";
        $result = $this->db->execute_query($sql, [$card, $user]);
        if ($result === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ".$this->db->error, E_USER_ERROR);
        else:
            $i = 0;
            $record = array();
            while ($row = $result->fetch_assoc()):
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Card $card, mainqty {$row['cardqty']}, sideqty {$row['sideqty']} in decknumber {$row['decknumber']} owned by user $user", $this->logfile);
                $record[$i]['decknumber'] = $row['decknumber'];
                $record[$i]['qty'] = $row['cardqty'];
                $record[$i]['sideqty'] = $row['sideqty'];
                $record[$i]['deckname'] = $row['deckname'];
                $i = $i + 1;
            endwhile;
            return $record;
        endif;
    }

    public function addDeckCard($deck,$card,$section,$quantity)
    {
        global $commander_decktypes, $commander_multiples, $any_quantity;
        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add card called: '$quantity' x '$card' to '$deck' ($section)", $this->logfile);

        // Get card name of addition
        $cardnamequery = "SELECT name,type,ability FROM cards_scry WHERE id = ? LIMIT 1";
        $result = $this->db->execute_query($cardnamequery, [$card]);
        $cardname = $result->fetch_assoc();
        if($result === FALSE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
        else:
            $cardnametext = $cardname['name'];
            $i = 0;
            $cdr_1_plus = FALSE;
            while($i < count($commander_multiples)):
                $while_result = FALSE;
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking type for: {$commander_multiples[$i]}", $this->logfile);
                if(str_contains($cardname['type'],$commander_multiples[$i]) == TRUE):
                    $while_result = TRUE;
                    $cdr_1_plus = TRUE;
                endif;
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...outcome: $while_result", $this->logfile);
                $i++;
            endwhile;
            $i = 0;
            while($i < count($any_quantity)):
                $while_result = FALSE;
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking ability for: {$any_quantity[$i]}", $this->logfile);
                if(isset($cardname['ability']) AND (str_contains($cardname['ability'],$any_quantity[$i]) == TRUE)):
                    $while_result = TRUE;
                    $cdr_1_plus = TRUE;
                endif;
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...outcome: $while_result", $this->logfile);
                $i++;
            endwhile;
            if($cdr_1_plus == FALSE):
                $multi_allowed = "no";
            else:
                $multi_allowed = "yes";
            endif;
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Card name for $card is $cardnametext; Commander multiples allowed: $multi_allowed", $this->logfile);
        endif;

        // Get deck type and existing cards in it
        if($decktypesql = $this->db->execute_query("SELECT type
                                    FROM decks 
                                    WHERE decknumber = ?",[$deck])):
            while ($row = $decktypesql->fetch_assoc()):
                if ($row['type'] == NULL):
                    $decktype = "none";
                else:
                    $decktype = $row['type'];
                endif;
            endwhile;
        else:
            $decktype = "none";
        endif;
        $cardlist = $this->db->execute_query("SELECT name,decks.type
                                    FROM deckcards 
                                LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                                LEFT JOIN decks on deckcards.decknumber = decks.decknumber
                                WHERE deckcards.decknumber = ? AND (cardqty > 0 OR sideqty > 0)",[$deck]);
        $cardlistnames = array();
        while ($row = $cardlist->fetch_assoc()):
            if(!in_array($row['name'], $cardlistnames)):
                $cardlistnames[] = $row['name'];
            endif;
        endwhile;
        if(in_array($cardnametext,$cardlistnames)):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Cardname $cardnametext is already in this deck", $this->logfile);
            $already_in_deck = TRUE;
        else:
            $already_in_deck = FALSE;
        endif;
        if(in_array($decktype,$commander_decktypes)):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck $deck is Commander-type", $this->logfile);
            $cdr_type_deck = TRUE;
        else:
            $cdr_type_deck = FALSE;
        endif;
        if($already_in_deck == TRUE AND $cdr_type_deck == TRUE AND $cdr_1_plus == FALSE):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is already in this deck, it's a Commander-style deck, and multiples of this type not allowed, can't add", $this->logfile);
            $quantity = FALSE;
        elseif($already_in_deck == FALSE AND $cdr_type_deck == TRUE AND $cdr_1_plus == FALSE):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card not already in this deck, it's a Commander-style deck, and multiples of this type not allowed, adding 1", $this->logfile);
            $quantity = 1;
        elseif($already_in_deck == TRUE AND $cdr_type_deck == TRUE AND $cdr_1_plus == TRUE):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is already in this deck, it's a Commander-style deck, and multiples of this type are allowed, adding requested qty", $this->logfile);
            $quantity = $quantity;
        elseif($already_in_deck == FALSE AND $cdr_type_deck == TRUE AND $cdr_1_plus == TRUE):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is not already in this deck, it's a Commander-style deck, and multiples of this type are allowed, adding requested qty", $this->logfile);
            $quantity = $quantity;
        elseif($cdr_type_deck == FALSE):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is already in this deck, it's not a Commander-style deck, adding requested qty", $this->logfile);
            $quantity = $quantity;
        endif;

        // Add card to deck

        if($quantity != FALSE):
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...adding $quantity x $card, $cardnametext to deck #$deck", $this->logfile);
            if($section == "side"):
                $checkqry = $this->db->execute_query("SELECT sideqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? LIMIT 1",[$deck,$card]);
                if ($checkqry !== false):
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0): // The card is in the deck, no detail yet on qty or side/main
                        $check = $checkqry->fetch_assoc();
                        if($check['sideqty'] != NULL):
                            $cardquery = "UPDATE deckcards SET sideqty = sideqty + 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "+1side";
                        else:
                            $cardquery = "UPDATE deckcards SET sideqty = 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "+1side";
                        endif;
                    else:
                        // The card is not in the deck at all
                        $cardquery = "INSERT into deckcards (decknumber, cardnumber, sideqty) VALUES (?, ?, ?)";
                        $params = [$deck,$card,$quantity];
                        $status = "+newside";
                    endif;
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
                endif;
            elseif($section == "main"):
                $checkqry = $this->db->execute_query("SELECT cardqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? LIMIT 1",[$deck,$card]);
                if ($checkqry !== false):
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0): // The card is in the deck, no detail yet on qty or side/main
                        $check = $checkqry->fetch_assoc();
                        if($check['cardqty'] != NULL):
                            $cardquery = "UPDATE deckcards SET cardqty = cardqty + ? WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$quantity,$deck,$card];
                            $status = "+1main";
                        else:
                            $cardquery = "UPDATE deckcards SET cardqty = 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "+1main";
                        endif;
                    else:
                        // The card is not in the deck at all
                        $cardquery = "INSERT into deckcards (decknumber, cardnumber, cardqty) VALUES (?, ?, ?)";
                        $params = [$deck,$card,$quantity];
                        $status = "+newmain";
                    endif;
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
                endif;
            endif;

            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add card called: $cardquery, status is $status", $this->logfile);
            if($runquery = $this->db->execute_query($cardquery,$params)):
                return $status;
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif;
        else:
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...skipping $cardnametext to deck #$deck", $this->logfile);
            return 'cardnotadded';
        endif;
    }

    public function subtractDeckCard($deck,$card,$section,$quantity)
    {
        if($quantity == "all"):
            if($section == "side"):
                $cardquery = "UPDATE deckcards SET sideqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                $params = [$deck,$card];
                $status = "allside";
            elseif($section == "main"):
                $cardquery = "UPDATE deckcards SET cardqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                $params = [$deck,$card];
                $status = "allmain";
            endif;
        else:
            if($section == "side"):
                $checkqry = $this->db->execute_query("SELECT sideqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? AND sideqty IS NOT NULL LIMIT 1",[$deck,$card]);
                if ($checkqry !== false):
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0): // The card is in the deck side
                        $check = $checkqry->fetch_assoc();
                        if($check['sideqty'] > 1):
                            $cardquery = "UPDATE deckcards SET sideqty = sideqty - 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "-1side";
                        elseif($check['sideqty'] == 1):
                            $cardquery = "UPDATE deckcards SET sideqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "lastside";
                        endif;
                    else:
                        $status = "-error";
                        $cardquery = '';
                        $params = [];
                    endif;
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
                endif;
            elseif($section == "main"):
                $checkqry = $this->db->execute_query("SELECT cardqty FROM deckcards WHERE decknumber = ? AND cardnumber = ?  AND cardqty IS NOT NULL LIMIT 1",[$deck,$card]);
                if ($checkqry !== false):
                    $rowcount = $checkqry->num_rows;
                    if ($rowcount > 0): // The card is in the deck main
                        $check = $checkqry->fetch_assoc();
                        if($check['cardqty'] > 1):
                            $cardquery = "UPDATE deckcards SET cardqty = cardqty - 1 WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "-1main";
                        elseif($check['cardqty'] == 1):
                            $cardquery = "UPDATE deckcards SET cardqty = NULL WHERE decknumber = ? AND cardnumber = ?";
                            $params = [$deck,$card];
                            $status = "lastmain";
                        endif;
                    else:
                        $status = "-error";
                        $cardquery = '';
                        $params = [];
                    endif;
                else:
                    trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
                endif;
            endif;
        endif;

        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck card query called: $cardquery, status is $status", $this->logfile);

        if($status != '-error'):
            if ($runquery = $this->db->execute_query($cardquery,$params)):
                //ran ok
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif;
        else:
            $this->message->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck card query called: ERROR status is $status", $this->logfile);
        endif;

        // Clean-up empties
        if ($status == 'lastmain' OR $status == 'lastside' OR $status == 'allmain' OR $status == 'allside'):
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck card query called: $cardquery, status is $status", $this->logfile);
            $cardquery = "DELETE FROM deckcards WHERE decknumber = ? AND ((cardqty = 0 AND sideqty = 0) OR (cardqty = 0 AND sideqty IS NULL) OR (cardqty IS NULL AND sideqty = 0) OR (cardqty IS NULL AND sideqty IS NULL))";
            $params = [$deck];
            if ($runquery = $this->db->execute_query($cardquery,$params)):
                //ran ok
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif;
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
        if ($check_result->num_rows > 0):
            // Commander already exists, remove old commander
            $removeCommanderQuery = 'UPDATE deckcards SET commander = 0 WHERE decknumber = ?';
            $removeCommanderStmt = $this->db->prepare($removeCommanderQuery);
            $removeCommanderStmt->bind_param('i', $deck);
            if ($removeCommanderStmt->execute()):
                $this->message->MessageTxt('[NOTICE]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Old Commander removed", $this->logfile);
            else:
                trigger_error('[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $this->db->error, E_USER_ERROR);
            endif;
            $removeCommanderStmt->close();
        endif;
        $status = "+cdr";

        // Add new commander
        $addCommanderQuery = 'UPDATE deckcards SET commander = 1 WHERE decknumber = ? AND cardnumber = ?';
        $addCommanderStmt = $this->db->prepare($addCommanderQuery);
        $addCommanderStmt->bind_param('is', $deck, $card);
        if ($addCommanderStmt->execute()):
            $this->message->MessageTxt('[NOTICE]', basename(__FILE__) . " " . __LINE__, "Function " . __FUNCTION__ . ": Add Commander run: $addCommanderQuery, status is $status", $this->logfile);
            return $status;
        else:
            trigger_error('[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $this->db->error, E_USER_ERROR);
        endif;
        $addCommanderStmt->close();
    }

    public function addPartner($deck,$card)
    {
        $check = $this->db->execute_query('SELECT commander FROM deckcards WHERE decknumber = ? AND commander = 2',[$deck]);
        if ($check->num_rows > 0): //Partner already there
            if($runquery = $this->db->execute_query("UPDATE deckcards SET commander = 0 WHERE decknumber = ?",[$deck])):
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Old Partner removed",$this->logfile);
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif; 
        endif;
        $status = "+ptnr";
        if($runquery = $this->db->execute_query("UPDATE deckcards SET commander = '2' WHERE decknumber = ? AND cardnumber = ?",[$deck,$card])):
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add Partner run, status is $status",$this->logfile);
            return $status;
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        endif; 
    }

    public function delCommander($deck,$card)
    {
        $check = $this->db->execute_query("SELECT commander FROM deckcards WHERE decknumber = ? AND cardnumber = ? AND commander > 0",[$deck,$card]);
        if ($check->num_rows > 0):
            $status = "-cdr";
            if($runquery = $this->db->execute_query("UPDATE deckcards SET commander = 0 WHERE decknumber = ? AND cardnumber = ?",[$deck,$card])):
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Remove Commander called, status is $status",$this->logfile);
                return $status;
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif;    
        else:
            $status = "notcdr";
        endif;
    }

    public function delDeck($decktodelete)
    {
        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck called: deck $decktodelete",$this->logfile);
        $stmt = $this->db->prepare("DELETE FROM decks WHERE decknumber = ?");
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;
        $bind = $stmt->bind_param("i", $decktodelete); 
        if ($bind === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Deleting deck: ". $this->db->error, E_USER_ERROR);
        else:
            $checkgone1 = "SELECT decknumber FROM decks WHERE decknumber = ? LIMIT 1";
            $runquery1 = $this->db->execute_query($checkgone1,[$decktodelete]);
            $result1=$runquery1->fetch_assoc();
            if ($result1 === null):
                $deck_deleted = 1;
            else:
                $deck_deleted = 0;
            endif;
        endif;
        $stmt->close();
        $stmt = $this->db->prepare("DELETE FROM deckcards WHERE decknumber = ?");
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;
        $bind = $stmt->bind_param("i", $decktodelete); 
        if ($bind === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL failure: ". $this->db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Deleting deck cards: ". $this->db->error, E_USER_ERROR);
        else:
            $checkgone2 = "SELECT cardnumber FROM deckcards WHERE decknumber = ? LIMIT 1";
            $runquery2 = $this->db->execute_query($checkgone2,[$decktodelete]);
            $result2=$runquery2->fetch_assoc();
            if ($result2 === null):
                $deckcards_deleted = 1;
            else:
                $deckcards_deleted = 0;
            endif;
        endif;
        $stmt->close();
        if($deck_deleted === 1 AND $deckcards_deleted === 1):
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck $decktodelete deleted",$this->logfile);
        else:?>
            <div class="msg-new error-new" onclick='CloseMe(this)'><span>Deck and/or cards not deleted</span>
                <br>
                <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
            </div> <?php
        endif;
    }

    public function addDeck($user,$newdeckname)
    {
        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add deck called: deck $newdeckname",$this->logfile);
        $decksuccess = [];
        
        $decknamechecksql = "SELECT decknumber FROM decks WHERE owner = ? and deckname = ? LIMIT 1";
        $decknameparams = [$user,$newdeckname];
        $result = $this->db->execute_query($decknamechecksql,$decknameparams);
        if($result !== false && $result->num_rows === 0):
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck does not exist for user: $user, deckname: '$newdeckname'",$this->logfile);
            
            //Create new deck
            $sql = "INSERT INTO decks (owner,deckname) VALUES (?,?)";
            $params = [$user,$newdeckname];
            if($deckinsert = $this->db->execute_query($sql,$params) && $this->db->affected_rows === 1):
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": SQL deck insert succeeded for user: $user, deckname: '$newdeckname'",$this->logfile);
            else:
                trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $this->db->error, E_USER_ERROR);
            endif;

            //Checking if it created OK
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Running confirm SQL query",$this->logfile);
            $checksql = "SELECT decknumber FROM decks
                            WHERE owner = ? AND deckname = ? LIMIT 1";
            $checkparams = [$user,$newdeckname];
            $runquery = $this->db->execute_query($checksql,$checkparams);
            if($runquery !== false && $runquery->num_rows === 1):
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Confirmed existence of deck: $newdeckname",$this->logfile);
                $deckcheckrow = $runquery->fetch_assoc();
                $decksuccess['flag'] = 1; //set flag so we know we don't need to check for cards in deck.
                $decksuccess['decknumber'] = $deckcheckrow['decknumber'];
            elseif($runquery !== false && $runquery->num_rows === 0):  
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Failed - deck: $newdeckname not created",$this->logfile);
                ?>
                <div class="msg-new error-new" onclick='CloseMe(this)'><span>Deck creation failed</span>
                    <br>
                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                </div>
                <?php
                $decksuccess['flag'] = 10; //set flag so we know to break.
            else:
                trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $this->db->error, E_USER_ERROR);
            endif;
        elseif($result !== false && $result->num_rows === 1):
            $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": New deck name already exists",$this->logfile); ?>
            <div class="msg-new error-new" onclick='CloseMe(this)'><span>Deck name exists</span>
                <br>
                <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
            </div> <?php
            $decksuccess['flag'] = 10; //set flag so we know to break.
        else:
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $this->db->error, E_USER_ERROR);
        endif;
        if(!isset($decksuccess['decknumber'])):
            $decksuccess['decknumber'] = NULL;
        endif;
        return $decksuccess;
    }
    
    public function renameDeck($deck,$newname,$user)
    {
        $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Rename deck called: deck $deck to '$newname'",$this->logfile);

        // CHECK IF NAME IS ALREADY USED
        $query = 'SELECT decknumber FROM decks WHERE deckname=? AND owner=?';
        $stmt = $this->db->execute_query($query, [$newname,$user]);
        if ($stmt === FALSE):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        else:
            if ($stmt->num_rows > 0):
                $newnamereturn = 2;
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Name '$newname' already used",$this->logfile);
                return($newnamereturn);
            else:
                $newnamereturn = 0; //OK to continue
                $this->message->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Name '$newname' not already used",$this->logfile);
            endif;
        endif;
        $stmt->close();

        //RENAME
        $query = 'UPDATE decks SET deckname=? WHERE decknumber=?';
        $stmt = $this->db->execute_query($query, [$newname,$deck]);
        if ($stmt === FALSE):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        else:
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Name '$newname' query run",$this->logfile);
            if ($this->db->affected_rows !== 1):
                $newnamereturn = 1; //Error
                $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...result: Unknown error: {$this->db->affected_rows} row(s) affected",$this->logfile);
            endif;
            $this->message->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...result: {$this->db->affected_rows} row affected ",$this->logfile);
        endif;
        return($newnamereturn);
    }

    public function __toString() {
        $this->message->MessageTxt("[ERROR]", "Class " . __CLASS__, "Called as string");
        return "Called as a string";
    }
}