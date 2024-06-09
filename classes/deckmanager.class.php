<?php
/* Version:     2.0
    Date:       09/06/24
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
 *  
    1.2         20/01/24
 *              Move to logMessage
 * 
 *  1.3         15/02/24
 *              Empty 'type' breaks decks - cater for this (REX, SLD)
 * 
 *  2.0         09/06/2024
 *              Improve deck import capability to cater with MTGC import format 
 *              as well as quick add format
 *              Send email if multi input errors
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class DeckManager 
{
    private $db;
    private $logfile;
    private $batchedCardIds = []; // Array to store batched cards to add
    private $message;
    private $useremail;
    private $serveremail;
    
    public function __construct($db, $logfile, $useremail, $serveremail) {
        $this->db = $db;
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
        $this->useremail = $useremail;
        $this->serveremail = $serveremail;
    }
    
    public function processInput($decknumber, $input) {
    // processInput can handle either single-line or multi-line 'add card' 
    // inputs, using quickadd method to process
    // Multi-line inputs are batched for combined data write by addDeckCardsBatch
    // Called from deckdetail.php
        
        $this->message->logMessage('[DEBUG]',"ProcessInput called for deck $decknumber with '$input'");
        // Check if input is multiline
        $lines = explode("\n", $input);
        $inputType = '';
        $qtyLines = count($lines);
        if ($qtyLines > 1):
            $this->message->logMessage('[DEBUG]',"Multi-line input ($qtyLines lines), calling quickadd in batch mode");
            $row = 1;
            $warningsummary = '';
            $warningheading = 'Warning type, Row number, Input line';
            foreach ($lines as $line):
                $line = trim($line);
                $start = substr($line, 0, 8);
                if(strpos($start, 'setcode') !== false || strpos($start, 'Edition') !== false):
                    $this->message->logMessage('[DEBUG]',"Row $row: Header row: '$line'");
                else:
                    $this->message->logMessage('[DEBUG]',"Row $row: Data row: '$line'");
                    $quickaddresult = $this->quickadd($decknumber, $line, true); // Set fourth parameter to true for batching
                    if($quickaddresult === false || $quickaddresult === 'cardnotfound'):
                        $this->message->logMessage('[DEBUG]',"Row $row: Result: fail");
                        $newwarning = "ERROR - Row $row, Line: '$line'"."\n";
                        $warningsummary = $warningsummary.$newwarning;
                    else:
                        $this->message->logMessage('[DEBUG]',"Row $row: Result: success");
                    endif;
                endif;
                $row = $row + 1;
            endforeach;
            if ($warningsummary !== ''):
                $from = "From: $this->serveremail\r\nReturn-path: $this->serveremail"; 
                $subject = "Deck Import failures / warnings"; 
                $message = "$warningheading \n \n $warningsummary \n";
                mail($this->useremail, $subject, $message, $from); 
                $this->message->logMessage('[DEBUG]',"Deck import warnings: '$warningsummary'");
                $quickaddresult = 'multierror';
            endif;
        else:
            $this->message->logMessage('[DEBUG]',"Single-line input, calling quickadd in single-line mode");
            $inputType = 'SingleText';
            $quickaddresult = $this->quickadd($decknumber, $input);
            $this->message->logMessage('[DEBUG]',"Result: $quickaddresult");
            return $quickaddresult;
        endif;
        // If batched card array is not empty, perform batch insert
        if (!empty($this->batchedCardIds)):
            $this->addDeckCardsBatch($decknumber, $this->batchedCardIds);
            // Clear array after batch insert
            $this->batchedCardIds = [];
            if(isset($quickaddresult) && $quickaddresult === 'multierror'):
                return $quickaddresult;
            endif;
        endif;
    }
    
    public function quickadd($decknumber,$get_string,$batch = false)
    // Called from processInput()
    {
        global $noQuickAddLayouts;
        
        $this->message->logMessage('[NOTICE]',"Quick add interpreter called for deck $decknumber with '$get_string' (batch mode '$batch')");
        $quickaddstring = htmlspecialchars($get_string,ENT_NOQUOTES);
        $interpreted_string = input_interpreter($quickaddstring);
        if($interpreted_string !== false):
            // UUID
            if (isset($interpreted_string['uuid']) AND $interpreted_string['uuid'] !== ''):
                $quickaddUUID = $interpreted_string['uuid'];
            else:
                $quickaddUUID = '';
            endif;
            // Quantity
            if (isset($interpreted_string['qty']) AND $interpreted_string['qty'] !== ''):
                $quickaddqty = $interpreted_string['qty'];
            else:
                $quickaddqty = 1;
            endif;
            // Name
            if (isset($interpreted_string['name']) AND $interpreted_string['name'] !== ''):
                $quickaddcard = $interpreted_string['name'];
            else:
                $quickaddcard = '';
            endif;
            // Set
            if (isset($interpreted_string['set']) AND $interpreted_string['set'] !== ''):
                $quickaddset = strtoupper($interpreted_string['set']);
            else:
                $quickaddset = '';
            endif;
            // Lang
            if (isset($interpreted_string['lang']) AND $interpreted_string['lang'] !== ''):
                $quickaddlang = strtoupper($interpreted_string['lang']);
            else:
                $quickaddlang = '';
            endif;
            // Collector number
            if (isset($interpreted_string['number']) AND $interpreted_string['number'] !== ''):
                $quickaddNumber = $interpreted_string['number'];
            else:
                $quickaddNumber = '';
            endif;
            $quickaddcard = htmlspecialchars_decode($quickaddcard,ENT_QUOTES);
            $this->message->logMessage('[DEBUG]',"Quick add interpreted as: Qty: [$quickaddqty] x Card: [$quickaddcard] Set: [$quickaddset] Collector number: [$quickaddNumber] Language: [$quickaddlang] UUID: [$quickaddUUID]");
            $stmt = null;

            // Get card layouts to not include in quick add
            $placeholders = array_fill(0, count($noQuickAddLayouts), '?');
            $placeholdersString = implode(',', $placeholders);

            if ($quickaddUUID !== '' && valid_uuid($quickaddUUID) !== false):
                // Card UUID provided and valid UUID
                $this->message->logMessage('[DEBUG]',"Quick add proceeding with provided UUID: [$quickaddUUID]");
                $query = "SELECT id,name,setcode,number FROM cards_scry WHERE id = ? LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = [$quickaddUUID];
                $stmt->bind_param('s', $params[0]);
                
            elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber !== '' AND $quickaddlang !== ''):
                // Card name, setcode, and collector number provided
                $this->message->logMessage('[DEBUG]',"Quick add proceeding with provided name, set, number and specified language");
                $query = "SELECT id FROM cards_scry WHERE (name = ? OR
                                                           f1_name = ? OR 
                                                           f2_name = ? OR 
                                                           printed_name = ? OR 
                                                           f1_printed_name = ? OR 
                                                           f2_printed_name = ? OR 
                                                           flavor_name = ? OR
                                                           f1_flavor_name = ? OR 
                                                           f2_flavor_name = ?) AND 
                                                           setcode = ? AND number_import = ? AND 
                                                           lang LIKE ? AND 
                                                           layout NOT IN ($placeholdersString)
                                                           ORDER BY release_date DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickaddcard);
                array_push($params, $quickaddset, $quickaddNumber, $quickaddlang);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);
                
            elseif ($quickaddcard !== '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
                // Card name, setcode, and collector number provided
                $this->message->logMessage('[DEBUG]',"Quick add proceeding with provided name, set, number and primary language");
                $query = "SELECT id FROM cards_scry WHERE (name = ? OR
                                                           f1_name = ? OR 
                                                           f2_name = ? OR 
                                                           printed_name = ? OR 
                                                           f1_printed_name = ? OR 
                                                           f2_printed_name = ? OR 
                                                           flavor_name = ? OR
                                                           f1_flavor_name = ? OR 
                                                           f2_flavor_name = ?) AND 
                                                           setcode = ? AND number_import = ? AND 
                                                           `layout` NOT IN ($placeholdersString) AND
                                                           primary_card = 1
                                                           ORDER BY release_date DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickaddcard);
                array_push($params, $quickaddset, $quickaddNumber);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);

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
                                                           setcode = ? AND 
                                                           `layout` NOT IN ($placeholdersString)  AND
                                                           primary_card = 1
                                                           ORDER BY release_date DESC, number ASC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickaddcard);
                array_push($params, $quickaddset);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);

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
                                                           `layout` NOT IN ($placeholdersString) AND
                                                           primary_card = 1
                                                           ORDER BY release_date DESC, number ASC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = array_fill(0, 9, $quickaddcard);
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);

            elseif ($quickaddcard === '' AND $quickaddset !== '' AND $quickaddNumber !== ''):
                // Card name not provided, setcode, and collector number provided
                $query = "SELECT id FROM cards_scry WHERE
                                                        setcode = ? AND 
                                                        number_import = ? AND 
                                                        `layout` NOT IN ($placeholdersString) AND
                                                        primary_card = 1
                                                        ORDER BY release_date DESC LIMIT 1";
                $stmt = $this->db->prepare($query);
                $params = [$quickaddset, $quickaddNumber];
                $params = array_merge($params, $noQuickAddLayouts);
                $stmt->bind_param(str_repeat('s', count($params)), ...$params);

            else:
                // Not enough info, cannot add
                $this->message->logMessage('[NOTICE]',"Quick add - Not enough info to identify a card to add");
                $cardtoadd = 'cardnotfound';
                return $cardtoadd;
            endif;

            if ($stmt !== null AND $stmt->execute()):
                $result = $stmt->get_result();
                if ($result->num_rows > 0):
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    $cardtoadd = $row['id'];
                    $this->message->logMessage('[DEBUG]',"Quick add result: UUID result is '$cardtoadd'");
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
                    $this->message->logMessage('[NOTICE]',"Quick add - Card not found");
                    $cardtoadd = 'cardnotfound';
                    return $cardtoadd;
                endif;
            else:
                $stmt->close();
                $this->message->logMessage('[ERROR]',"Quick add - SQL error: " . $stmt->error);
                $cardtoadd = 'cardnotfound';
                return $cardtoadd;
            endif;
        else:
            $this->message->logMessage('[ERROR]',"Quick add interpreter failed");
            return false;
        endif;
    }

    public function addDeckCardsBatch($decknumber, $batchedCardIds) {
        $this->message->logMessage('[DEBUG]',"deckManager batch process called");
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
                $this->message->logMessage('[DEBUG]',"deckManager batch process completed");
            else :
                $this->message->logMessage('[ERROR]',"Error executing batch insert query: ".$stmt->error);
            endif;

            $stmt->close();
        endif;
    }
    
    public function deckOwnerCheck($deck,$user)
    {
        $this->message->logMessage('[DEBUG]',"Checking deck ownership: $deck, $user");
        $sql = "SELECT deckname, owner FROM decks WHERE decknumber = ? LIMIT 1";
        $result = $this->db->execute_query($sql, [$deck]);
        if ($result === false):
            trigger_error('[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__ . ": SQL failure: " . $this->db->error, E_USER_ERROR);
        else:
            if ($row = $result->fetch_assoc()):
                $deckname = $row['deckname'];
                $owner = $row['owner'];
                $this->message->logMessage('[DEBUG]',"Deck $deck ($deckname) belongs to owner $owner (called by $user)");
                if ($owner != $user):
                    $this->message->logMessage('[ERROR]',"Deck {$row['deckname']} does not belong to user $user, returning to deck page");
                    return false;
                else:
                    return $deckname;
                endif;
            else:
                $this->message->logMessage('[ERROR]',"No deck found for deck $deck, returning to deck page");
                return false;
            endif;
        endif;
    }

    public function deckCardCheck($card, $user)
    {
        $this->message->logMessage('[DEBUG]',"Checking to see what decks this card is in for user $user...");

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
                $this->message->logMessage('[DEBUG]',"Card $card, mainqty {$row['cardqty']}, sideqty {$row['sideqty']} in decknumber {$row['decknumber']} owned by user $user");
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
        $this->message->logMessage('[NOTICE]',"Add card called: '$quantity' x '$card' to '$deck' ($section)");

        // Get card name and other key details of card to add
        $cardnamequery = "SELECT name,type,f1_type,ability FROM cards_scry WHERE id = ? LIMIT 1";
        $result = $this->db->execute_query($cardnamequery, [$card]);
        $cardname = $result->fetch_assoc();
        if($result === FALSE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $this->db->error, E_USER_ERROR);
        else:
            $cardnametext = $cardname['name'];
            $i = 0;
            $cdr_1_plus = FALSE;
            
            // Cater for cards with NULL type (REX and SLD double-sided cards with dual art but functionally same card
            if($cardname['type'] !== NULL):
                $card_type = $cardname['type'];
            elseif ($cardname['type'] === NULL AND isset($cardname['f1_type'])):
                $card_type = $cardname['f1_type'];
            elseif ($cardname['type'] === NULL AND isset($cardname['f2_type'])):
                $card_type = $cardname['f2_type'];
            else:
                $card_type = 'None';
            endif;
            
            while($i < count($commander_multiples)):
                $while_result = FALSE;
                $this->message->logMessage('[DEBUG]',"Checking type for: {$commander_multiples[$i]}");
                if(str_contains($card_type,$commander_multiples[$i]) == TRUE):
                    $while_result = TRUE;
                    $cdr_1_plus = TRUE;
                endif;
                $i++;
            endwhile;
            $i = 0;
            while($i < count($any_quantity)):
                $while_result = FALSE;
                $this->message->logMessage('[DEBUG]',"Checking ability for: {$any_quantity[$i]}");
                if(isset($cardname['ability']) AND (str_contains($cardname['ability'],$any_quantity[$i]) == TRUE)):
                    $while_result = TRUE;
                    $cdr_1_plus = TRUE;
                endif;
                $i++;
            endwhile;
            if($cdr_1_plus == FALSE):
                $multi_allowed = "no";
            else:
                $multi_allowed = "yes";
            endif;
            $this->message->logMessage('[DEBUG]',"Card name for $card is $cardnametext; Commander multiples allowed: $multi_allowed");
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
            $this->message->logMessage('[DEBUG]',"Cardname $cardnametext is already in this deck");
            $already_in_deck = TRUE;
        else:
            $already_in_deck = FALSE;
        endif;
        if(in_array($decktype,$commander_decktypes)):
            $this->message->logMessage('[DEBUG]',"Deck $deck is Commander-type");
            $cdr_type_deck = TRUE;
        else:
            $cdr_type_deck = FALSE;
        endif;
        if($already_in_deck == TRUE AND $cdr_type_deck == TRUE AND $cdr_1_plus == FALSE):
            $this->message->logMessage('[DEBUG]',"This card is already in this deck, it's a Commander-style deck, and multiples of this type not allowed, can't add");
            $quantity = FALSE;
        elseif($already_in_deck == FALSE AND $cdr_type_deck == TRUE AND $cdr_1_plus == FALSE):
            $this->message->logMessage('[DEBUG]',"This card not already in this deck, it's a Commander-style deck, and multiples of this type not allowed, adding 1");
            $quantity = 1;
        elseif($already_in_deck == TRUE AND $cdr_type_deck == TRUE AND $cdr_1_plus == TRUE):
            $this->message->logMessage('[DEBUG]',"This card is already in this deck, it's a Commander-style deck, and multiples of this type are allowed, adding requested qty");
            $quantity = $quantity;
        elseif($already_in_deck == FALSE AND $cdr_type_deck == TRUE AND $cdr_1_plus == TRUE):
            $this->message->logMessage('[DEBUG]',"This card is not already in this deck, it's a Commander-style deck, and multiples of this type are allowed, adding requested qty");
            $quantity = $quantity;
        elseif($cdr_type_deck == FALSE):
            $this->message->logMessage('[DEBUG]',"This card is already in this deck, it's not a Commander-style deck, adding requested qty");
            $quantity = $quantity;
        endif;

        // Add card to deck

        if($quantity != FALSE):
            $this->message->logMessage('[DEBUG]',"...adding $quantity x $card, $cardnametext to deck #$deck");
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

            $this->message->logMessage('[NOTICE]',"Add card called: $cardquery, status is $status");
            if($runquery = $this->db->execute_query($cardquery,$params)):
                return $status;
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif;
        else:
            $this->message->logMessage('[DEBUG]',"...skipping $cardnametext to deck #$deck");
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

        $this->message->logMessage('[NOTICE]',"Delete deck card query called: $cardquery, status is $status");

        if($status != '-error'):
            if ($runquery = $this->db->execute_query($cardquery,$params)):
                //ran ok
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif;
        else:
            $this->message->logMessage('[ERROR]',"Delete deck card query called: ERROR status is $status");
        endif;

        // Clean-up empties
        if ($status == 'lastmain' OR $status == 'lastside' OR $status == 'allmain' OR $status == 'allside'):
            $this->message->logMessage('[NOTICE]',"Delete deck card query called: $cardquery, status is $status");
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
                $this->message->logMessage('[NOTICE]',"Old Commander removed");
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
            $this->message->logMessage('[NOTICE]',"Add Commander run: $addCommanderQuery, status is $status");
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
                $this->message->logMessage('[NOTICE]',"Old Partner removed");
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
            endif; 
        endif;
        $status = "+ptnr";
        if($runquery = $this->db->execute_query("UPDATE deckcards SET commander = '2' WHERE decknumber = ? AND cardnumber = ?",[$deck,$card])):
            $this->message->logMessage('[NOTICE]',"Add Partner run, status is $status");
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
                $this->message->logMessage('[NOTICE]',"Remove Commander called, status is $status");
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
        $this->message->logMessage('[NOTICE]',"Delete deck called: deck $decktodelete");
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
            $this->message->logMessage('[NOTICE]',"Deck $decktodelete deleted");
        else:?>
            <div class="msg-new error-new" onclick='CloseMe(this)'><span>Deck and/or cards not deleted</span>
                <br>
                <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
            </div> <?php
        endif;
    }

    public function addDeck($user,$newdeckname)
    {
        $this->message->logMessage('[NOTICE]',"Add deck called: deck $newdeckname");
        $decksuccess = [];
        
        $decknamechecksql = "SELECT decknumber FROM decks WHERE owner = ? and deckname = ? LIMIT 1";
        $decknameparams = [$user,$newdeckname];
        $result = $this->db->execute_query($decknamechecksql,$decknameparams);
        if($result !== false && $result->num_rows === 0):
            $this->message->logMessage('[NOTICE]',"Deck does not exist for user: $user, deckname: '$newdeckname'");
            
            //Create new deck
            $sql = "INSERT INTO decks (owner,deckname) VALUES (?,?)";
            $params = [$user,$newdeckname];
            if($deckinsert = $this->db->execute_query($sql,$params) && $this->db->affected_rows === 1):
                $this->message->logMessage('[NOTICE]',"SQL deck insert succeeded for user: $user, deckname: '$newdeckname'");
            else:
                trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $this->db->error, E_USER_ERROR);
            endif;

            //Checking if it created OK
            $this->message->logMessage('[NOTICE]',"Running confirm SQL query");
            $checksql = "SELECT decknumber FROM decks
                            WHERE owner = ? AND deckname = ? LIMIT 1";
            $checkparams = [$user,$newdeckname];
            $runquery = $this->db->execute_query($checksql,$checkparams);
            if($runquery !== false && $runquery->num_rows === 1):
                $this->message->logMessage('[NOTICE]',"Confirmed existence of deck: $newdeckname");
                $deckcheckrow = $runquery->fetch_assoc();
                $decksuccess['flag'] = 1; //set flag so we know we don't need to check for cards in deck.
                $decksuccess['decknumber'] = $deckcheckrow['decknumber'];
            elseif($runquery !== false && $runquery->num_rows === 0):  
                $this->message->logMessage('[NOTICE]',"Failed - deck: $newdeckname not created");
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
            $this->message->logMessage('[NOTICE]',"New deck name already exists"); ?>
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
        $this->message->logMessage('[NOTICE]',"Rename deck called: deck $deck to '$newname'");

        // CHECK IF NAME IS ALREADY USED
        $query = 'SELECT decknumber FROM decks WHERE deckname=? AND owner=?';
        $stmt = $this->db->execute_query($query, [$newname,$user]);
        if ($stmt === FALSE):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        else:
            if ($stmt->num_rows > 0):
                $newnamereturn = 2;
                $this->message->logMessage('[NOTICE]',"Name '$newname' already used");
                return($newnamereturn);
            else:
                $newnamereturn = 0; //OK to continue
                $this->message->logMessage('[NOTICE]',"Name '$newname' not already used");
            endif;
        endif;
        $stmt->close();

        //RENAME
        $query = 'UPDATE decks SET deckname=? WHERE decknumber=?';
        $stmt = $this->db->execute_query($query, [$newname,$deck]);
        if ($stmt === FALSE):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $this->db->error, E_USER_ERROR);
        else:
            $this->message->logMessage('[DEBUG]',"Name '$newname' query run");
            if ($this->db->affected_rows !== 1):
                $newnamereturn = 1; //Error
                $this->message->logMessage('[DEBUG]',"...result: Unknown error: {$this->db->affected_rows} row(s) affected");
            endif;
            $this->message->logMessage('[DEBUG]',"...result: {$this->db->affected_rows} row affected ");
        endif;
        return($newnamereturn);
    }

    public function __toString() {
        $this->message->logMessage("[ERROR]","Called as string");
        return "Called as a string";
    }
}