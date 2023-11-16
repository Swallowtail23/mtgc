<?php
/* Version:     17.0
    Date:       15/10/23
    Name:       functions_new.php
    Purpose:    Functions for all pages
    Notes:      
        
    1.0
                Initial version
    2.0         
                Added Custom error handling routine
 *  3.0
 *              Updated for classes and Mysqli_Manager
 *              including merging basicfunc and functions files
 *              Updated deckcard functions to use mysqli / $db
 * 4.0
 *              Added Function to see if an Admin user is running the page.
 * 5.0          
 *              Added functions in tidying up image routines, removing code from 
 *              carddetail page. Also added better flip image handling, and a 
 *              function to get the main image name.
 * 5.1      
 *              Added filter for # in symbolreplace function
 * 
 * 6.0
 *              Added login stamp to database to track last logged in date.
 * 7.0
 *              Added Scyfall JSON code
 * 7.1
 *              Added override for Scryfall JSON function (works with
 *              scrynameoverride column in cards table)
 * 7.2
 *              Corrected incorrect detection of null in scryfall data fetch
 * 8.0
 *              Moved from writelog to Message class
 * 9.0
 *              Marking tcgplayer function to be deprecated; improving scryfall 
 *              image function (+ get by set and number)
 * 10.0
 *              Refactoring for cards_scry
 * 11.0
 *              PHP 8.1 compatibility
 * 12.0
 *              Add flip image capability for battle cards
 * 13.0
 *              Deck card adding rewrite for Commander, etc.
 * 14.0
 *              Add function to get decks with a card
 * 15.0
 *              Functions to delete a deck and rename a deck
 * 16.0
 *              Added handling for etched cards
 * 17.0
 *              Review and improve Scryfall price routine
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

function check_logged()
{ 
/*
//  Replaced with sessionmanager.class

    global $_SESSION, $db, $logfile;
    
    if (isset($_SESSION['user'])):
        $user = $_SESSION['user'];
    else:
        $user = '';
        header("Location: /login.php");
        exit();
    endif;
    
    // Check user status
    $row = $db->select_one('status', 'users',"WHERE usernumber='$user'");
    if($row === false):
        header("Location: /login.php");
    else:
        if (!$_SESSION["logged"] == TRUE):
            header("Location: /login.php");
            exit();
        elseif (isset($row['status']) AND (($row['status'] === 'disabled') OR ($row['status'] === 'locked'))):
            session_destroy();
            header("Location: /login.php");
            exit();
        else:
            // Need a catch here?
        endif;
    endif;
    return $user;
 
 */
}

function forcechgpwd()
{ 
    global $_SESSION; 
    if ((isset($_SESSION["chgpwd"])) AND ($_SESSION["chgpwd"] == TRUE)):
        header("Location: /profile.php"); 
    endif;
}

function check_input($value)
{
    global $db;
    if (!is_numeric($value)):
        $value = "'" . mysqli_real_escape_string($db,$value) . "'";
    endif;

    return $value;
}

function cssver()
{
    global $db;
    if($row = $db->select_one('usemin', 'admin')):
        if($row['usemin'] == 1):
            $cssver = "-min";
        else:
            $cssver = "";
        endif;
        return $cssver;
    else:
        trigger_error('[ERROR]',basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function spamcheck($field) 
{
    global $db, $logfile;
    // Sanitize e-mail address
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking email address <$field>",$logfile);
    $field=filter_var($field, FILTER_SANITIZE_EMAIL);
    if(!filter_var($field, FILTER_VALIDATE_EMAIL)):
        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Invalid email address <$field> passed",$logfile);
        return FALSE;
    else:
        if($row = $db->select_one('usernumber,username', 'users',"WHERE email = '$field'")):
            if(empty($row)):
                return FALSE;
            elseif(filter_var($field, FILTER_VALIDATE_EMAIL)):
                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Email address validated for reset request",$logfile);
                return $field;
            else:
                return FALSE;
            endif;
        else:
            trigger_error('[ERROR]',basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    endif;
}

function mtcemode($user)
{
    global $db;
    if($row = $db->select_one('mtce', 'admin')):
        if ($row['mtce'] == 1):
            if($row2 = $db->select_one('admin', 'users',"WHERE usernumber='$user'")):
                if ($row2['admin'] == 1):   //admin user logged on
                    return 2;
                else:
                    return 1;               //non-admin user logged on
                endif;
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
            endif;
        else:
            return 0;                           // maintenance mode not set
        endif;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function collection_view($user)
{
    global $db;
    if($row = $db->select_one('collection_view', 'users',"WHERE usernumber='$user'")):
        $collection_view = $row['collection_view'];
    endif;
    return $collection_view;
}

function username($user)
{
    global $db;
    if($row = $db->select_one('username','users',"WHERE usernumber='$user'")):
        $username = $row['username'];
        return $username;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
}

function deckownercheck($deck,$user)
{
    global $db, $logfile;
    $sql = "SELECT * FROM decks WHERE decknumber = $deck LIMIT 1";
    $result = $db->query($sql);
    if($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        while($row = $result->fetch_assoc()):
            $deckname = $row['deckname'];
            if ($row['owner'] !== $user):
                $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck {$row['deckname']} does not belong to user $user, returning to deck page",$logfile);
                return false;
            else:
                return $deckname;
            endif;
        endwhile;
    endif;    
}

function deckcardcheck($card,$user)
{
    global $db, $logfile;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking to see what decks this card is in for user $user...",$logfile);
    $sql = "SELECT * FROM deckcards LEFT JOIN decks ON deckcards.decknumber = decks.decknumber WHERE cardnumber = '$card' and owner = $user";
    $result = $db->query($sql);
    if($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        $i = 0;
        $record = array();
        while($row = $result->fetch_assoc()):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Card $card, mainqty {$row['cardqty']}, sideqty {$row['sideqty']} in decknumber {$row['decknumber']} owned by user $user",$logfile);
            $record[$i]['decknumber'] = $row['decknumber'];
            $record[$i]['qty'] = $row['cardqty'];
            $record[$i]['sideqty'] = $row['sideqty'];
            $record[$i]['deckname'] = $row['deckname'];
            $i = $i + 1;
        endwhile;
        return $record;
    endif;    
}

function quickadd($decknumber,$get_string)
{
    global $db, $logfile, $commander_decktypes;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Quick add interpreter called for deck $decknumber with '$get_string'",$logfile);
    
    $quickaddstring = htmlspecialchars($get_string,ENT_NOQUOTES);
    
    //Quantity
    preg_match("~^(\d+)~", $quickaddstring,$qty);
    if (isset($qty[0])):
        $quickaddstring = ltrim(ltrim($quickaddstring,$qty[0]));
        $quickaddqty = $qty[0];
    else:
        $quickaddqty = 1;
    endif;
    
    //Set (qty has been removed if it was set)
    preg_match('#\((.*?)\)#', $quickaddstring, $settomatch);
    if (isset($settomatch[0])):
        $quickaddset = rtrim(ltrim(strtoupper($settomatch[0]),"("),")");
        $quickaddcard = rtrim(rtrim($quickaddstring,$settomatch[0]));
    else:
        $quickaddset = '';
        $quickaddcard = $quickaddstring;
    endif;
    //Card
    
    $quickaddcard = htmlspecialchars_decode($quickaddcard,ENT_QUOTES);
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add called with string '$quickaddstring', interpreted as: [$quickaddqty] x [$quickaddcard] [$quickaddset]",$logfile);
    $quickaddcard = $db->escape($quickaddcard);
    
    if($quickaddset == ''):
        if ($quickaddcardid = $db->query("SELECT id,setcode,layout from cards_scry
                                     WHERE name = '$quickaddcard' AND `layout` NOT IN ('token','double_faced_token','emblem') ORDER BY release_date DESC LIMIT 1")):
            if ($quickaddcardid->num_rows > 0):
                while ($results = $quickaddcardid->fetch_assoc()):
                    $cardtoadd = $results['id'];
                endwhile;
            else:
                $cardtoadd = 'cardnotfound';
            endif;
        else:
            trigger_error('[ERROR] deckdetail.php: Error: Quickadd SQL error', E_USER_ERROR);
        endif;
    else:
        if ($quickaddcardid = $db->query("SELECT id,setcode,layout from cards_scry
                                     WHERE name = '$quickaddcard' AND setcode = '$quickaddset' AND `layout` NOT IN ('token','double_faced_token','emblem') ORDER BY release_date DESC LIMIT 1")):
            if ($quickaddcardid->num_rows > 0):
                while ($results = $quickaddcardid->fetch_assoc()):
                    $cardtoadd = $results['id'];
                endwhile;
            else:
                $cardtoadd = 'cardnotfound';
            endif;
        else:
            trigger_error('[ERROR] deckdetail.php: Error: Quickadd SQL error', E_USER_ERROR);
        endif;
    endif;
    if($cardtoadd == 'cardnotfound'):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add - Card not found",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Quick add result: $cardtoadd",$logfile);
        adddeckcard($decknumber,$cardtoadd,"main","$quickaddqty");
    endif;
    return $cardtoadd;
}

function adddeckcard($deck,$card,$section,$quantity)
{
    global $db, $logfile, $commander_decktypes, $commander_multiples, $any_quantity;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add card called: $quantity x $card to $deck ($section)",$logfile);
    
    // Get card name of addition
    $cardnamequery = "SELECT name,type,ability FROM cards_scry WHERE id = ? LIMIT 1";
    $result = $db->execute_query($cardnamequery, [$card]);
    $cardname = $result->fetch_assoc();
    if($result != TRUE):
        trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
    else:
        $cardnametext = $cardname['name'];
        $i = 0;
        $cdr_1_plus = FALSE;
        while($i < count($commander_multiples)):
            $while_result = FALSE;
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking type for: {$commander_multiples[$i]}",$logfile);
            if(str_contains($cardname['type'],$commander_multiples[$i]) == TRUE):
                $while_result = TRUE;
                $cdr_1_plus = TRUE;
            endif;
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...outcome: $while_result",$logfile);
            $i++;
        endwhile;
        $i = 0;
        while($i < count($any_quantity)):
            $while_result = FALSE;
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking ability for: {$any_quantity[$i]}",$logfile);
            if(isset($cardname['ability']) AND (str_contains($cardname['ability'],$any_quantity[$i]) == TRUE)):
                $while_result = TRUE;
                $cdr_1_plus = TRUE;
            endif;
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...outcome: $while_result",$logfile);
            $i++;
        endwhile;
        if($cdr_1_plus == FALSE):
            $multi_allowed = "no";
        else:
            $multi_allowed = "yes";
        endif;
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Card name for $card is $cardnametext; Commander multiples allowed: $multi_allowed",$logfile);
    endif;
    
    // Get deck type and existing cards in it
    $decktypesql = $db->query("SELECT type
                                FROM decks 
                                WHERE decknumber = $deck");
    while ($row = $decktypesql->fetch_assoc()):
        if ($row['type'] == NULL):
            $decktype = "none";
        else:
            $decktype = $row['type'];
        endif;
    endwhile;
    $cardlist = $db->query("SELECT name,decks.type
                                FROM deckcards 
                            LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                            LEFT JOIN decks on deckcards.decknumber = decks.decknumber
                            WHERE deckcards.decknumber = $deck AND (cardqty > 0 OR sideqty > 0)");
    $cardlistnames = array();
    while ($row = $cardlist->fetch_assoc()):
        if(!in_array($row['name'], $cardlistnames)):
            $cardlistnames[] = $row['name'];
        endif;
    endwhile;
    if(in_array($cardnametext,$cardlistnames)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Cardname $cardnametext is already in this deck",$logfile);
        $already_in_deck = TRUE;
    else:
        $already_in_deck = FALSE;
    endif;
    if(in_array($decktype,$commander_decktypes)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck $deck is Commander-type",$logfile);
        $cdr_type_deck = TRUE;
    else:
        $cdr_type_deck = FALSE;
    endif;
    if($already_in_deck == TRUE AND $cdr_type_deck == TRUE AND $cdr_1_plus == FALSE):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is already in this deck, it's a Commander-style deck, and multiples of this type not allowed, can't add",$logfile);
        $quantity = FALSE;
    elseif($already_in_deck == FALSE AND $cdr_type_deck == TRUE AND $cdr_1_plus == FALSE):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card not already in this deck, it's a Commander-style deck, and multiples of this type not allowed, adding 1",$logfile);
        $quantity = 1;
    elseif($already_in_deck == TRUE AND $cdr_type_deck == TRUE AND $cdr_1_plus == TRUE):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is already in this deck, it's a Commander-style deck, and multiples of this type are allowed, adding requested qty",$logfile);
        $quantity = $quantity;
    elseif($already_in_deck == FALSE AND $cdr_type_deck == TRUE AND $cdr_1_plus == TRUE):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is not already in this deck, it's a Commander-style deck, and multiples of this type are allowed, adding requested qty",$logfile);
        $quantity = $quantity;
    elseif($cdr_type_deck == FALSE):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": This card is already in this deck, it's not a Commander-style deck, adding requested qty",$logfile);
        $quantity = $quantity;
    endif;
    
    // Add card to deck
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...adding $quantity x $card, $cardnametext to deck #$deck",$logfile);
    if($quantity != FALSE):
        if($section == "side"):
            $check = $db->select_one('sideqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card'");
            if ($check !== null):
                if($check['sideqty'] != NULL):
                    $cardquery = "UPDATE deckcards SET sideqty = sideqty + 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "+1side";
                else:
                    $cardquery = "UPDATE deckcards SET sideqty = 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "+1side";
                endif;
            else:
                $cardquery = "INSERT into deckcards (decknumber, cardnumber, sideqty) VALUES ($deck, '$card', $quantity)";
                $status = "+newside";
            endif;
        elseif($section == "main"):
            $check = $db->select_one('cardqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card'");
            if ($check !== null):
                if($check['cardqty'] != NULL):
                    $cardquery = "UPDATE deckcards SET cardqty = cardqty + $quantity WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "+1main";
                else:
                    $cardquery = "UPDATE deckcards SET cardqty = 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "+1main";
                endif;
            else:
                $cardquery = "INSERT into deckcards (decknumber, cardnumber, cardqty) VALUES ($deck, '$card', $quantity)";
                $status = "+newmain";
            endif;
        endif;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add card called: $cardquery, status is $status",$logfile);
        if($runquery = $db->query($cardquery)):
            return $status;
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    endif;
}

function subtractdeckcard($deck,$card,$section,$quantity)
{
    global $db, $logfile;
    if($quantity == "all"):
        if($section == "side"):
            $cardquery = "UPDATE deckcards SET sideqty = NULL WHERE decknumber = $deck AND cardnumber = '$card'";
            $status = "allside";
        elseif($section == "main"):
            $cardquery = "UPDATE deckcards SET cardqty = NULL WHERE decknumber = $deck AND cardnumber = '$card'";
            $status = "allmain";
        endif;
        
    else:
        if($section == "side"):
            $check = $db->select_one('sideqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND sideqty IS NOT NULL");
            if ($check !== null):
                if($check['sideqty'] > 1):
                    $cardquery = "UPDATE deckcards SET sideqty = sideqty - 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "-1side";
                elseif($check['sideqty'] == 1):
                    $cardquery = "UPDATE deckcards SET sideqty = NULL WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "lastside";
                endif;
            else:
                $status = "-error";
                $cardquery = '';
            endif;
        elseif($section == "main"):
            $check = $db->select_one('cardqty','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND cardqty IS NOT NULL");
            if ($check !== null):
                if($check['cardqty'] > 1):
                    $cardquery = "UPDATE deckcards SET cardqty = cardqty - 1 WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "-1main";
                elseif($check['cardqty'] == 1):
                    $cardquery = "UPDATE deckcards SET cardqty = NULL WHERE decknumber = $deck AND cardnumber = '$card'";
                    $status = "lastmain";
                endif;
            else:
                $status = "-error";
                $cardquery = '';
            endif;
        endif;
    endif;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck card query called: $cardquery, status is $status",$logfile);
    if($status != '-error'):
        if ($runquery = $db->query($cardquery)):
            //ran ok
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    
    // Clean-up empties
    if ($status == 'lastmain' OR $status == 'lastside' OR $status == 'allmain' OR $status == 'allside'):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck card query called: $cardquery, status is $status",$logfile);
        $cardquery = "DELETE FROM deckcards WHERE decknumber = $deck AND ((cardqty = 0 AND sideqty = 0) OR (cardqty = 0 AND sideqty IS NULL) OR (cardqty IS NULL AND sideqty = 0) OR (cardqty IS NULL AND sideqty IS NULL))";
        if ($runquery = $db->query($cardquery)):
            //ran ok
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;
    endif;
    
    return $status;
}

function addcommander($deck,$card)
{
    global $db, $logfile;
    $check = $db->select('commander','deckcards',"WHERE decknumber = $deck AND commander = 1");
    if ($check->num_rows > 0): //Commander already there
        $cardquery = "UPDATE deckcards SET commander = 0 WHERE decknumber = $deck";
        if($runquery = $db->query($cardquery)):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Old Commander removed",$logfile);
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif; 
    endif;
    $status = "+cdr";
    $cardquery = "UPDATE deckcards SET commander = '1' WHERE decknumber = $deck AND cardnumber = '$card'";
    if($runquery = $db->query($cardquery)):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add Commander run: $cardquery, status is $status",$logfile);
        return $status;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function addpartner($deck,$card)
{
    global $db, $logfile;
    $check = $db->select('commander','deckcards',"WHERE decknumber = $deck AND commander = 2");
    if ($check->num_rows > 0): //Partner already there
        $cardquery = "UPDATE deckcards SET commander = 0 WHERE decknumber = $deck";
        if($runquery = $db->query($cardquery)):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Old Partner removed",$logfile);
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif; 
    endif;
    $status = "+ptnr";
    $cardquery = "UPDATE deckcards SET commander = '2' WHERE decknumber = $deck AND cardnumber = '$card'";
    if($runquery = $db->query($cardquery)):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Add Partner run: $cardquery, status is $status",$logfile);
        return $status;
    else:
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif; 
}

function delcommander($deck,$card)
{
    global $db, $logfile;
    $cardquery = "UPDATE deckcards SET commander = 0 WHERE decknumber = $deck AND cardnumber = '$card'";
    $check = $db->select('commander','deckcards',"WHERE decknumber = $deck AND cardnumber = '$card' AND commander > 0");
    if ($check->num_rows > 0): 
        $status = "-cdr";
        if($runquery = $db->query($cardquery)):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Remove Commander called: $cardquery, status is $status",$logfile);
            return $status;
        else:
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        endif;    
    else:
        $status = "notcdr";
    endif;
}

function deldeck($decktodelete)
{
    global $db, $logfile;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Delete deck called: deck $decktodelete",$logfile);
    $stmt = $db->prepare("DELETE FROM decks WHERE decknumber=?");
    if ($stmt === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    $bind = $stmt->bind_param("i", $decktodelete); 
    if ($bind === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    $exec = $stmt->execute();
    if ($exec === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Deleting deck: ". $db->error, E_USER_ERROR);
    else:
        $checkgone1 = "SELECT decknumber FROM decks WHERE decknumber = '$decktodelete' LIMIT 1";
        $runquery1 = $db->query($checkgone1);
        $result1=$runquery1->fetch_assoc();
        if ($result1 === null):
            $deck_deleted = 1;
        else:
            $deck_deleted = 0;
        endif;
    endif;
    $stmt->close();
    $stmt = $db->prepare("DELETE FROM deckcards WHERE decknumber=?");
    if ($stmt === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    $bind = $stmt->bind_param("i", $decktodelete); 
    if ($bind === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    $exec = $stmt->execute();
    if ($exec === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Deleting deck cards: ". $db->error, E_USER_ERROR);
    else:
        $checkgone2 = "SELECT cardnumber FROM deckcards WHERE decknumber = '$decktodelete' LIMIT 1";
        $runquery2 = $db->query($checkgone2);
        $result2=$runquery2->fetch_assoc();
        if ($result2 === null):
            $deckcards_deleted = 1;
        else:
            $deckcards_deleted = 0;
        endif;
    endif;
    $stmt->close();
    if($deck_deleted === 1 AND $deckcards_deleted === 1):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Deck $decktodelete deleted",$logfile);
    else:?>
        <div class="msg-new error-new" onclick='CloseMe(this)'><span>Deck and/or cards not deleted</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div> <?php
    endif;
}

function renamedeck($deck,$newname,$user)
{
    global $db, $logfile;
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Rename deck called: deck $deck to '$newname'",$logfile);
    
    // CHECK IF NAME IS ALREADY USED
    $query = 'SELECT decknumber FROM decks WHERE deckname=? AND owner=?';
    $stmt = $db->execute_query($query, [$newname,$user]);
    if ($stmt != TRUE):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        if ($stmt->num_rows > 0):
            $newnamereturn = 2;
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Name '$newname' already used",$logfile);
            return($newnamereturn);
        else:
            $newnamereturn = 0; //OK to continue
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Name '$newname' not already used",$logfile);
        endif;
    endif;
    $stmt->close();
    
    //RENAME
    $query = 'UPDATE decks SET deckname=? WHERE decknumber=?';
    $stmt = $db->execute_query($query, [$newname,$deck]);
    if ($stmt != TRUE):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Name '$newname'query run",$logfile);
        if ($db->affected_rows !== 1):
            $newnamereturn = 1; //Error
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...result: Unknown error: $db->affected_rows row(s) affected",$logfile);
        endif;
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...result: $db->affected_rows row affected ",$logfile);
    endif;
    return($newnamereturn);
}

function symbolreplace($str)
{
    $str = str_replace('{E}','<img src="images/e.png" alt="{E}" class="manaimg">',$str);
    $str = str_replace('{T}','<img src="images/t.png" alt="{T}" class="manaimg">',$str);
    $str = str_replace('{Q}','<img src="images/q.png" alt="{Q}" class="manaimg">',$str);
    
    $str = str_replace('{W}','<img src="images/w.png" alt="{W}" class="manaimg">',$str);
    $str = str_replace('{U}','<img src="images/u.png" alt="{U}" class="manaimg">',$str);
    $str = str_replace('{B}','<img src="images/b.png" alt="{B}" class="manaimg">',$str);
    $str = str_replace('{R}','<img src="images/r.png" alt="{R}" class="manaimg">',$str);
    $str = str_replace('{G}','<img src="images/g.png" alt="{G}" class="manaimg">',$str);
    $str = str_replace('{S}','<img src="images/s.png" alt="{S}" class="manaimg">',$str);
    $str = str_replace('{C}','<img src="images/colourless_mana.png" alt="{C}" class="manaimg">',$str);
    
    $str = str_replace('{HR}','<img src="images/hr.png" alt="{HR}" class="manaimg">',$str);
    $str = str_replace('{+oo}','<img src="images/inf.png" alt="{+oo}" class="manaimg">',$str);
    $str = str_replace('{100}','<img src="images/100.png" alt="{100}" class="manaimg">',$str);
    $str = str_replace('{1000000}','<img src="images/1m.png" alt="{1000000}" class="manaimg">',$str);
    
    $str = str_replace('{WU}','<img src="images/wu.png" alt="{WU}" class="manaimg">',$str);
    $str = str_replace('{W/U}','<img src="images/wu.png" alt="{WU}" class="manaimg">',$str);
    $str = str_replace('{WB}','<img src="images/wb.png" alt="{WB}" class="manaimg">',$str);
    $str = str_replace('{W/B}','<img src="images/wb.png" alt="{WB}" class="manaimg">',$str);
    $str = str_replace('{UB}','<img src="images/ub.png" alt="{UB}" class="manaimg">',$str);
    $str = str_replace('{U/B}','<img src="images/ub.png" alt="{UB}" class="manaimg">',$str);
    $str = str_replace('{UR}','<img src="images/ur.png" alt="{UR}" class="manaimg">',$str);
    $str = str_replace('{U/R}','<img src="images/ur.png" alt="{UR}" class="manaimg">',$str);
    $str = str_replace('{BR}','<img src="images/br.png" alt="{BR}" class="manaimg">',$str);
    $str = str_replace('{B/R}','<img src="images/br.png" alt="{BR}" class="manaimg">',$str);
    $str = str_replace('{BG}','<img src="images/bg.png" alt="{BG}" class="manaimg">',$str);
    $str = str_replace('{B/G}','<img src="images/bg.png" alt="{BG}" class="manaimg">',$str);
    $str = str_replace('{RW}','<img src="images/rw.png" alt="{RW}" class="manaimg">',$str);
    $str = str_replace('{R/W}','<img src="images/rw.png" alt="{RW}" class="manaimg">',$str);
    $str = str_replace('{RG}','<img src="images/rg.png" alt="{RG}" class="manaimg">',$str);
    $str = str_replace('{R/G}','<img src="images/rg.png" alt="{RG}" class="manaimg">',$str);
    $str = str_replace('{GW}','<img src="images/gw.png" alt="{GW}" class="manaimg">',$str);
    $str = str_replace('{G/W}','<img src="images/gw.png" alt="{GW}" class="manaimg">',$str);
    $str = str_replace('{GU}','<img src="images/gu.png" alt="{GU}" class="manaimg">',$str);
    $str = str_replace('{G/U}','<img src="images/gu.png" alt="{GU}" class="manaimg">',$str);
    
    $str = str_replace('{2W}','<img src="images/2w.png" alt="{2W}" class="manaimg">',$str);
    $str = str_replace('{2U}','<img src="images/2u.png" alt="{2U}" class="manaimg">',$str);
    $str = str_replace('{2B}','<img src="images/2b.png" alt="{2B}" class="manaimg">',$str);
    $str = str_replace('{2R}','<img src="images/2r.png" alt="{2R}" class="manaimg">',$str);
    $str = str_replace('{2G}','<img src="images/2g.png" alt="{2G}" class="manaimg">',$str);
    $str = str_replace('{2/W}','<img src="images/2w.png" alt="{2/W}" class="manaimg">',$str);
    $str = str_replace('{2/B}','<img src="images/2b.png" alt="{2/B}" class="manaimg">',$str);
    $str = str_replace('{2/G}','<img src="images/2g.png" alt="{2/G}" class="manaimg">',$str);
    $str = str_replace('{2/U}','<img src="images/2u.png" alt="{2/U}" class="manaimg">',$str);
    $str = str_replace('{2/R}','<img src="images/2r.png" alt="{2/R}" class="manaimg">',$str);
    
    $str = str_replace('{X}','<img src="images/x.png" alt="{X}" class="manaimg">',$str);
    $str = str_replace('{Y}','<img src="images/y.png" alt="{Y}" class="manaimg">',$str);
    $str = str_replace('{Z}','<img src="images/z.png" alt="{Z}" class="manaimg">',$str);
    
    $str = str_replace('{1/2}','<img src="images/half.png" alt="{1/2}" class="manaimg">',$str);
    $str = str_replace('{0}','<img src="images/0.png" alt="{0}" class="manaimg">',$str);
    $str = str_replace('{1}','<img src="images/1.png" alt="{1}" class="manaimg">',$str);
    $str = str_replace('{2}','<img src="images/2.png" alt="{2}" class="manaimg">',$str);
    $str = str_replace('{3}','<img src="images/3.png" alt="{3}" class="manaimg">',$str);
    $str = str_replace('{4}','<img src="images/4.png" alt="{4}" class="manaimg">',$str);
    $str = str_replace('{5}','<img src="images/5.png" alt="{5}" class="manaimg">',$str);
    $str = str_replace('{6}','<img src="images/6.png" alt="{6}" class="manaimg">',$str);
    $str = str_replace('{7}','<img src="images/7.png" alt="{7}" class="manaimg">',$str);
    $str = str_replace('{8}','<img src="images/8.png" alt="{8}" class="manaimg">',$str);
    $str = str_replace('{9}','<img src="images/9.png" alt="{9}" class="manaimg">',$str);
    $str = str_replace('{10}','<img src="images/10.png" alt="{10}" class="manaimg">',$str);
    $str = str_replace('{11}','<img src="images/11.png" alt="{11}" class="manaimg">',$str);
    $str = str_replace('{12}','<img src="images/12.png" alt="{12}" class="manaimg">',$str);
    $str = str_replace('{13}','<img src="images/13.png" alt="{13}" class="manaimg">',$str);
    $str = str_replace('{14}','<img src="images/14.png" alt="{14}" class="manaimg">',$str);
    $str = str_replace('{15}','<img src="images/15.png" alt="{15}" class="manaimg">',$str);
    $str = str_replace('{16}','<img src="images/16.png" alt="{16}" class="manaimg">',$str);
    $str = str_replace('{17}','<img src="images/17.png" alt="{17}" class="manaimg">',$str);
    $str = str_replace('{18}','<img src="images/18.png" alt="{18}" class="manaimg">',$str);
    $str = str_replace('{19}','<img src="images/19.png" alt="{19}" class="manaimg">',$str);
    $str = str_replace('{20}','<img src="images/20.png" alt="{20}" class="manaimg">',$str);
    
    $str = str_replace('{PW}','<img src="images/pw.png" alt="{PW}" class="manaimg">',$str);
    $str = str_replace('{W/P}','<img src="images/pw.png" alt="{W/P}" class="manaimg">',$str);
    $str = str_replace('{PU}','<img src="images/pu.png" alt="{PU}" class="manaimg">',$str);
    $str = str_replace('{U/P}','<img src="images/pu.png" alt="{U/P}" class="manaimg">',$str);
    $str = str_replace('{PB}','<img src="images/pb.png" alt="{PB}" class="manaimg">',$str);
    $str = str_replace('{B/P}','<img src="images/pb.png" alt="{B/P}" class="manaimg">',$str);
    $str = str_replace('{PR}','<img src="images/pr.png" alt="{PR}" class="manaimg">',$str);
    $str = str_replace('{R/P}','<img src="images/pr.png" alt="{R/P}" class="manaimg">',$str);
    $str = str_replace('{PG}','<img src="images/pg.png" alt="{PG}" class="manaimg">',$str);
    $str = str_replace('{G/P}','<img src="images/pg.png" alt="{G/P}" class="manaimg">',$str);

    $str = str_replace('{CHAOS}','<img src="images/chaos.png" alt="{PG}" class="manaimg">',$str);
    $str = str_replace('{G/U/P}','<img src="images/gup.png" alt="{G/U/P}" class="manaimg">',$str);

    $str = str_replace('?','-',$str);
    $str = str_replace('£','<br>',$str);
    $str = str_replace('#','',$str);
    $str = str_replace('{PWk}','Planeswalk',$str);
    $str = str_replace('{Ch}','Chaos',$str);
    $str = str_replace("\n","<br>",$str);
    return $str;
}

function langreplace($str)
{
    $str = str_replace("ja","Japanese",$str);
    return $str;
}

function finddates($str)
{
    $matches = array();
    $pattern = '/'
               . '([0-9]{1,2})'     
               . '([\/])'       
               . '([0-9]{1,2})' 
               . '([\/])'       
               . '([0-9]{0,4})'
               . '/';
    if (preg_match_all($pattern, $str, $matches)):
        return $matches[0];
    endif;
}

function checkRemoteFile($url)
{
    global $logfile;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_USERAGENT,'MtGCollection/1.0');
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $curlresult = curl_exec($ch);
    $curldetail = curl_getinfo($ch);
    curl_close($ch);
    if($curlresult === FALSE or $curldetail['http_code'] != 200 or $curldetail['download_content_length'] < 1):
        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": {$curldetail['url']} DOES NOT exist, HTTP code is: ".
                $curldetail['http_code'].", file size is: ".$curldetail['download_content_length']." bytes",$logfile);
        return false;
    else: 
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": {$curldetail['url']} exists, HTTP code is: ".
                $curldetail['http_code'].", file size is: ".$curldetail['download_content_length']." bytes",$logfile);
        return true;
    endif;
}

function getimgname($cardid)
{
    global $logfile;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Getting image name - cardid: $cardid",$logfile);
    $imgname = $cardid.'.jpg';
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": card image name is $imgname",$logfile);
    return $imgname;
}

function getImageNew($setcode,$cardid,$ImgLocation,$layout,$two_card_detail_sections)

// Replaced by class ImageManager

{
    /* global $db, $logfile, $serveremail, $adminemail;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": called for $setcode, $cardid, $ImgLocation, $layout",$logfile);
    $localfile = $ImgLocation.$setcode.'/'.$cardid.'.jpg';
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": File should be at $localfile",$logfile);
    if(in_array($layout,$two_card_detail_sections)):
        $localfile_b = $ImgLocation.$setcode.'/'.$cardid.'_b.jpg';
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Back file should be at $localfile_b",$logfile);
    endif;
    // Front face
    if (!file_exists($localfile)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $localfile missing, running get image function",$logfile);
        $sql = "SELECT image_uri,layout,f1_image_uri FROM cards_scry WHERE id like '$cardid' LIMIT 1";
        $result = $db->query($sql);
        if($result === false):
             trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL error: ".$db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Query $sql successful",$logfile);
            $coderow = $result->fetch_array(MYSQLI_ASSOC);
            $imageurl = '';
            if(isset($coderow['image_uri']) AND !is_null($coderow['image_uri'])):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Standard card, {$coderow['image_uri']}",$logfile);
                $imageurl = strtolower($coderow['image_uri']);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for image to use as $localfile",$logfile);
            elseif(isset($coderow['f1_image_uri']) AND !is_null($coderow['f1_image_uri'])):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Flip card, {$coderow['f1_image_uri']}",$logfile);
                $imageurl = strtolower($coderow['f1_image_uri']);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for images to use as $localfile",$logfile);
            endif;
            if (strpos($imageurl,'.jpg?') !== false):
                $imageurl = substr($imageurl, 0, (strpos($imageurl, ".jpg?") + 5))."1";
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Imageurl is $imageurl",$logfile);
            endif;
            if ((checkRemoteFile($imageurl) == false) OR ($imageurl === '')):
                $imageurl = '';
                $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
                $subject = "Invalid image from Scryfall API"; 
                $message = "$imageurl for card $cardid does not exist - check database entry against API, has it been deleted?";
                mail($adminemail, $subject, $message, $from); 
                $frontimg = 'error';
            else:
                $options  = array('http' => array('user_agent' => 'MtGCollection/1.0'));
                $context  = stream_context_create($options);
                $image = file_get_contents($imageurl, false, $context);
                if (!file_exists($ImgLocation.$setcode)):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Creating new directory $setcode",$logfile);
                    mkdir($ImgLocation.$setcode);
                endif;
                file_put_contents($localfile, $image);
                $relativepath = strpos($localfile,'cardimg');
                $frontimg = substr($localfile,$relativepath);
            endif;
        endif;
    else:
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": File exists already at $localfile",$logfile);
        $relativepath = strpos($localfile,'cardimg');
        $frontimg = substr($localfile,$relativepath);
    endif;
    $imageurl = array('front' => $frontimg,
                      'back' => '');
    //Back face
    if (isset($localfile_b)):
        if(!file_exists($localfile_b)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $localfile_b missing, running get image function",$logfile);
            $sql = "SELECT layout,f2_image_uri FROM cards_scry WHERE id like '$cardid' LIMIT 1";
            $result2 = $db->query($sql);
            if($result2 === false):
                 trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL error: ".$db->error, E_USER_ERROR);
            else:
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Query $sql successful",$logfile);
                $coderow2 = $result2->fetch_array(MYSQLI_ASSOC);
                $imageurl_2 = '';
                if(isset($coderow2['f2_image_uri']) AND !is_null($coderow2['f2_image_uri'])):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Flip card back, {$coderow2['f2_image_uri']}",$logfile);
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for images to use as $localfile_b",$logfile);
                    $imageurl_2 = strtolower($coderow2['f2_image_uri']);
                endif;
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Flip card back image, {$coderow2['f2_image_uri']}",$logfile);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Looking on scryfall.com ($cardid) for image to use as $localfile_b",$logfile);
                if (strpos($imageurl_2,'.jpg?') !== false):
                    $imageurl_2 = substr($imageurl_2, 0, (strpos($imageurl_2, ".jpg?") + 5))."1";
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Imageurl_2 is $imageurl_2",$logfile);
                endif;
                if ($imageurl_2 === ''):
                    $backimg = 'empty';
                elseif(checkRemoteFile($imageurl_2) == false):
                    $backimg = 'error';
                else:
                    $options  = array('http' => array('user_agent' => 'MtGCollection/1.0'));
                    $context  = stream_context_create($options);
                    $image2 = file_get_contents($imageurl_2, false, $context);
                    if (!file_exists($ImgLocation.$setcode)):
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Creating new directory $setcode",$logfile);
                        mkdir($ImgLocation.$setcode);
                    endif;
                    file_put_contents($localfile_b, $image2);
                    $relativepath_2 = strpos($localfile_b,'cardimg');
                    $backimg = substr($localfile_b,$relativepath_2);
                endif;
            endif;
        elseif (file_exists($localfile_b)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": File exists already at $localfile_b",$logfile);
            $relativepath_2 = strpos($localfile_b,'cardimg');
            $backimg = substr($localfile_b,$relativepath_2);
        endif;
        $imageurl = array('front' => $frontimg,
                          'back' => $backimg);
    endif;
    return $imageurl;
*/
}
     


function getStringParameters($input,$ignore1,$ignore2='')
// This function takes a parsed GET string and passes it back with SET sub-arrays included, and a specified KEY excluded
{
    $output="";
    foreach($input as $key => $value):
        if ((isset($input['set'])) AND (is_array($input['set']))):
            $sets = filter_var_array($input['set'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
        endif;
        if ($key === "set"):
            foreach($sets AS $keys=>$values):
                if (empty($output)):
                    $output .= '?set%5B%5D='.htmlspecialchars($values, ENT_COMPAT, 'UTF-8');
                else :
                    $output .= '&amp;set%5B%5D='.htmlspecialchars($values, ENT_COMPAT, 'UTF-8');
                endif;
            endforeach;
        elseif (($key === $ignore1) OR ($key === $ignore2)):
            // don't do anything!
        elseif ($key === 'name'):
            if (empty($output)) :
                $output .= '?'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            else :
                $output .= '&amp;'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            endif; 
        elseif ($key === 'layout'):
            $validlayouts = array('grid','list','bulk','');
            if (!in_array($value, $validlayouts)):
                $value = 'grid';
            endif;
            if (empty($output)) :
                $output .= '?'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            else :
                $output .= '&amp;'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            endif; 
        else:
            if (empty($output)) :
                $output .= '?'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            else :
                $output .= '&amp;'.htmlspecialchars($key, ENT_COMPAT, 'UTF-8').'='.htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            endif;    
        endif;
    endforeach;
       
    return $output;
}

function valid_pass($candidate) 
{
    if (!preg_match_all('$\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $candidate, $hole)):
        return FALSE;
    else:
        return TRUE;
    endif;
    $hole='';
}

function exportCollectionToCsv($table,$filename = 'export.csv')
{
        global $db, $logfile;
        $csv_terminated = "\n";
	$csv_separator = ",";
	$csv_enclosed = '"';
	$csv_escaped = "\\";
	$table = $db->escape($table);
        $sql = "SELECT setcode,number_import,name,normal,$table.foil,$table.etched,$table.id as scryfall_id FROM $table JOIN cards_scry ON $table.id = cards_scry.id WHERE (($table.normal > 0) OR ($table.foil > 0) OR ($table.etched > 0))";
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Running Export Collection to CSV: $sql",$logfile);
        
	// Gets the data from the database
	$result = $db->query($sql);
        if($result === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        else:
            $fields_cnt = $result->field_count;
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Number of fields: $fields_cnt",$logfile);
            $schema_insert = '';
            for ($i = 0; $i < $fields_cnt; $i++):
                $fieldinfo = mysqli_fetch_field_direct($result, $i);
                $l = $csv_enclosed.str_replace($csv_enclosed, $csv_escaped.$csv_enclosed, stripslashes($fieldinfo->name)).$csv_enclosed;
                $schema_insert .= $l;
                $schema_insert .= $csv_separator;
            endfor;

            $out = trim(substr($schema_insert, 0, -1));
            $out .= $csv_terminated;

            // Format the data
            while($row = $result->fetch_row()):
                $schema_insert = '';
                for ($j = 0; $j < $fields_cnt; $j++):
                    if ($row[$j] == '0' || $row[$j] != ''):
                        if ($csv_enclosed == ''):
                            $schema_insert .= $row[$j];
                        else:
                            $schema_insert .= $csv_enclosed .
                            str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                        endif;
                    else:
                        $schema_insert .= '';
                    endif;
                    if ($j < $fields_cnt - 1):
                        $schema_insert .= $csv_separator;
                    endif;
                endfor;
                $out .= $schema_insert;
                $out .= $csv_terminated;
            endwhile;
                $out .= $csv_enclosed;
                $out .= $csv_terminated;
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Length: " . strlen($out));
            // Output to browser with appropriate mime type, you choose ;)
            header("Content-type: text/x-csv; charset=UTF-8");
            //header("Content-type: text/csv");
            //header("Content-type: application/csv");
            header("Content-Disposition: attachment; filename=$filename");
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            echo $out;
            exit;
        endif;
}
function importmapcheck($import_id)
{
    global $db;
    $sql = "SELECT mapped_id FROM importlookup WHERE original_id = '$import_id' LIMIT 1";
    $result = $db->query($sql);
    if($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        $row = $result->fetch_assoc();
        if (!empty($row[0])):
            $import_id = $row[0];
        endif;
        return $import_id;
    endif;
}

function autolink($str, $attributes=array()) {
    $attrs = '';
    foreach ($attributes as $attribute => $value):
        $attrs .= " {$attribute}=\"{$value}\"";
    endforeach;
    $str = ' ' . $str;
    $str = preg_replace(
            '`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i',
            '$1<a href="$2"'.$attrs.'>$2</a>',
            $str
    );
    $str = substr($str, 1);
    return $str;
}

function check_admin_control($adminip)
{ 
    // Check for Session variable for admin access (set on login)
    if ((isset($_SESSION['admin'])) AND ($_SESSION['admin'] === TRUE)):
        if (($adminip === 1) OR ($adminip === $_SERVER['REMOTE_ADDR'])):
            //Admin and secure location, or Admin and admin IP set to ''
            $admin = 1;
        else:
            //Admin but not a secure location
            $admin = 2;
        endif;
    else:
        //Not an admin
        $admin = 3;
    endif;
    return $admin;
}

function get_full_url()
{
    // Get HTTP/HTTPS (the possible values for this vary from server to server)
    $myUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && !in_array(strtolower($_SERVER['HTTPS']),array('off','no'))) ? 'https' : 'http';
    // Get domain portion
    $myUrl .= '://'.$_SERVER['HTTP_HOST'];
    // Get path to script
    $myUrl .= $_SERVER['REQUEST_URI'];
    // Add path info, if any
    if (!empty($_SERVER['PATH_INFO'])) $myUrl .= $_SERVER['PATH_INFO'];
    
    return $myUrl;
}

function scryfall($cardid,$action = '')
// Fetch TCG buy URI and price from scryfall.com JSON data
{
    //Set up the function
    global $db,$logfile,$useremail,$max_card_data_age;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail for $cardid",$logfile);
    if(!isset($cardid)):
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail without required card id",$logfile);
        exit;
    endif;
    $baseurl = "https://api.scryfall.com/";
    $cardid = $db->escape($cardid);
    $time = time();
    //Set the URL
    $url = $baseurl."cards/".$cardid."?".$time;
    $obj = new Message;
    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail URL for $cardid is $url",$logfile);
        
    if($row = $db->select('id','cards_scry',"WHERE id='$cardid'")):
        if ($row->num_rows === 0):
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, no card with this id - exiting (2)",$logfile);
            exit;
        elseif ($row->num_rows === 1):
            $scrymethod = 'id';
        endif;
    else:
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API error",$logfile);
        $this->status = 0;
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    endif;
    
    // Check for existing data, not too old, and set required action
    $row = $db->select_one('jsonupdatetime, tcg_buy_uri','scryfalljson',"WHERE id='$cardid'");
    if ($row !== null):
        $lastjsontime = $row['jsonupdatetime'];
        $record_age = (time() - $lastjsontime);
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data exists for $cardid, $record_age seconds old",$logfile);
        if ($record_age > $max_card_data_age):
            //Old data, fetch and update:
            $scryaction = 'update';
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data stale (older than $max_card_data_age seconds) for $cardid, running '$scryaction'",$logfile);
        elseif ($action == "update"):
            //Update forced
            $scryaction = 'update';
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data update requested for $cardid, running '$scryaction'",$logfile);
        else:
            //data is there and is current:
            $scryaction = 'read';
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: Data not stale (younger than $max_card_data_age seconds) for $cardid, running '$scryaction'",$logfile);
        endif;
    else:
        //No data, fetch and insert:
        $scryaction = 'get';
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with result: No data exists for $cardid, running '$scryaction'",$logfile);
    endif;
            
    // Actions:

    // UPDATE
    if($scryaction === 'update'):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with 'update' result: fetching $url",$logfile);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $curlresult = curl_exec($ch);
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with update: $curlresult",$logfile);
        curl_close($ch);
        $scryfall_result = json_decode($curlresult,true);
        if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
            $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
        else:
            $tcg_buy_uri = null;
        endif;
        if(isset($scryfall_result["prices"])):
            $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price section included",$logfile);
            if(isset($scryfall_result["prices"]["usd"])):
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd set: {$scryfall_result["prices"]["usd"]}",$logfile);
                if($scryfall_result["prices"]["usd"] == ''):
                    $price = 0.00;
                elseif($scryfall_result["prices"]["usd"] == 'null'):
                    $price = NULL;
                else:
                    $price = $scryfall_result["prices"]["usd"];
                endif;
            else:
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd not set, setting to null",$logfile);
                $price = NULL;
            endif;
            if(isset($scryfall_result["prices"]["usd_foil"])):
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}",$logfile);
                if($scryfall_result["prices"]["usd_foil"] == ''):
                    $price_foil = 0.00;
                elseif($scryfall_result["prices"]["usd_foil"] == 'null'):
                    $price_foil = NULL;
                else:
                    $price_foil = $scryfall_result["prices"]["usd_foil"];
                endif;
            else:
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_foil not set, setting to null",$logfile);
                $price_foil = NULL;
            endif;
            if(isset($scryfall_result["prices"]["usd_etched"])):
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_etched set: {$scryfall_result["prices"]["usd_etched"]}",$logfile);
                if($scryfall_result["prices"]["usd_etched"] == ''):
                    $price_etched = 0.00;
                elseif($scryfall_result["prices"]["usd_etched"] == 'null'):
                    $price_etched = NULL;
                else:
                    $price_etched = $scryfall_result["prices"]["usd_etched"];
                endif;
            else:
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_etched not set, setting to null",$logfile);
                $price_etched = NULL;
            endif;

            if(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                $price_sort = 0.00;
            elseif(($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                $price_sort = $price;
            elseif(($price == 0.00 OR $price === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                $price_sort = $price_foil;
            elseif(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL)):
                $price_sort = $price_etched;
            elseif($price == 0.00 OR $price === NULL):
                $price_sort = min($price_etched,$price_foil);
            elseif($price_foil == 0.00 OR $price_foil === NULL):
                $price_sort = min($price_etched,$price);
            elseif($price_etched == 0.00 OR $price_etched === NULL):
                $price_sort = min($price,$price_foil);
            else:
                $price_sort = min($price,$price_foil,$price_etched);
            endif;

            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Scryfall data: price: $price, price foil: $price_foil, price etched: $price_etched, therefore $price_sort is used for sorting price",$logfile);
            $update_tcg_uri = 'UPDATE scryfalljson SET tcg_buy_uri=?,jsonupdatetime=? WHERE id=?';
            $stmt = $db->prepare($update_tcg_uri);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $db->error, E_USER_ERROR);
            endif;
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $update_tcg_uri",$logfile);
            $stmt->bind_param('sss', $tcg_buy_uri,$time,$cardid);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                $obj = new Message;
                $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Updating tcg uri failed ".$db->error, E_USER_ERROR,$logfile);
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Updating tcg uri, new data written for $cardid: Insert ID: ".$stmt->insert_id,$logfile);
            endif;

            $update_prices = 'UPDATE cards_scry SET price=?,price_foil=?,price_etched=?,price_sort=? WHERE id=?';
            $stmt = $db->prepare($update_prices);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $db->error, E_USER_ERROR);
            endif;
            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $update_prices",$logfile);
            $stmt->bind_param('sssss', $price,$price_foil,$price_etched,$price_sort,$cardid);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                $obj = new Message;
                $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data update failed: ".$db->error, E_USER_ERROR,$logfile);
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data updated for $cardid: Insert ID: ".$stmt->insert_id,$logfile);
            endif;
        else:
            $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, result does not contain a prices section",$logfile);
            $prices = 0;
            $price = 0;
            $price_foil = 0;
            $price_etched = 0;
        endif;
        $returnarray = array("action" => "update", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);

    // READ
    elseif($scryaction === 'read'):
        $tcg_buy_uri = $row['tcg_buy_uri'];
        $obj = new Message;
        $price = NULL;
        $price_foil = NULL;
        $price_etched = NULL;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, returning $tcg_buy_uri",$logfile);
        $returnarray = array("action" => "read", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);
    
    // GET
    elseif($scryaction === 'get'):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with 'get' result: fetching $url",$logfile);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $curlresult = curl_exec($ch);
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail with get: $curlresult",$logfile);
        curl_close($ch);
        $scryfall_result = json_decode($curlresult,true);
        if(isset($scryfall_result["purchase_uris"]["tcgplayer"])):
            $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, result contain tcg link {$scryfall_result["purchase_uris"]["tcgplayer"]}",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, result does not contain a tcg link",$logfile);
            $tcg_buy_uri = 0;
        endif;
        if(isset($scryfall_result["prices"])):
            $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price section included",$logfile);
            if(isset($scryfall_result["prices"]["usd"])):
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd set: {$scryfall_result["prices"]["usd"]}",$logfile);
                if($scryfall_result["prices"]["usd"] == ''):
                    $price = 0.00;
                elseif($scryfall_result["prices"]["usd"] == 'null'):
                    $price = NULL;
                else:
                    $price = $scryfall_result["prices"]["usd"];
                endif;
            else:
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd not set, setting to null",$logfile);
                $price = NULL;
            endif;
            if(isset($scryfall_result["prices"]["usd_foil"])):
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}",$logfile);
                if($scryfall_result["prices"]["usd_foil"] == ''):
                    $price_foil = 0.00;
                elseif($scryfall_result["prices"]["usd_foil"] == 'null'):
                    $price_foil = NULL;
                else:
                    $price_foil = $scryfall_result["prices"]["usd_foil"];
                endif;
            else:
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_foil not set, setting to null",$logfile);
                $price_foil = NULL;            
            endif;
            if(isset($scryfall_result["prices"]["usd_etched"])):
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_etched set: {$scryfall_result["prices"]["usd_etched"]}",$logfile);
                if($scryfall_result["prices"]["usd_etched"] == ''):
                    $price_etched = 0.00;
                elseif($scryfall_result["prices"]["usd_etched"] == 'null'):
                    $price_etched = NULL;
                else:
                    $price_etched = $scryfall_result["prices"]["usd_etched"];
                endif;
            else:
                $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price/usd_etched not set, setting to null",$logfile);
                $price_etched = NULL;            
            endif;
            
            if(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                $price_sort = 0.00;
            elseif(($price_foil == 0.00 OR $price_foil === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                $price_sort = $price;
            elseif(($price == 0.00 OR $price === NULL) AND ($price_etched == 0.00 OR $price_etched === NULL)):
                $price_sort = $price_foil;
            elseif(($price == 0.00 OR $price === NULL) AND ($price_foil == 0.00 OR $price_foil === NULL)):
                $price_sort = $price_etched;
            elseif($price == 0.00 OR $price === NULL):
                $price_sort = min($price_etched,$price_foil);
            elseif($price_foil == 0.00 OR $price_foil === NULL):
                $price_sort = min($price_etched,$price);
            elseif($price_etched == 0.00 OR $price_etched === NULL):
                $price_sort = min($price,$price_foil);
            else:
                $price_sort = min($price,$price_foil,$price_etched);
            endif;
            $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, prices are: $price, $price_foil and $price_etched; Sort price = $price_sort",$logfile);
        else:
            $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, result does not contain a prices section",$logfile);
            $prices = 0;
            $price = 0;
            $price_foil = 0;
            $price_etched = 0;
            
        endif;
        $query = 'INSERT INTO scryfalljson (id, jsonupdatetime, tcg_buy_uri) VALUES (?,?,?)';
        $stmt = $db->prepare($query);
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Preparing SQL: ". $db->error, E_USER_ERROR);
        endif;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $query",$logfile);
        $stmt->bind_param('sss', $cardid, $time, $tcg_buy_uri);
        if ($stmt === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
        endif;
        $exec = $stmt->execute();
        if ($exec === false):
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Adding update notice: failed ".$db->error, E_USER_ERROR,$logfile);
        else:
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, new data written for $cardid: Insert ID: ".$stmt->insert_id,$logfile);
        endif;
        if(!isset($prices)):
            $obj = new Message; $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, writing prices $price, $price_foil, $price_sort",$logfile);
            $query = 'UPDATE cards_scry SET price=?,price_foil=?,price_sort=? WHERE id=?';
            $stmt = $db->prepare($query);
            $stmt->bind_param('ssss',$price,$price_foil,$price_sort,$cardid);
            if ($stmt === false):
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": Binding SQL: ". $db->error, E_USER_ERROR);
            endif;
            $exec = $stmt->execute();
            if ($exec === false):
                $obj = new Message;
                $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data update failed",$logfile);
            else:
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": scryfall API by $useremail, price data updated: Insert ID: ".$stmt->insert_id,$logfile);
            endif;
        endif;
        $returnarray = array("action" => "get", "tcg_uri" => $tcg_buy_uri, "price" => $price, "price_foil" => $price_foil, "price_etched" => $price_etched);
    endif;
    return $returnarray;
}

function loginstamp($useremail)
{
    global $db, $logfile;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login",$logfile);
        $logindate = date("Y-m-d");
        $query = "UPDATE users SET lastlogin_date = '$logindate' WHERE email = '$useremail'";
    if($db->query($query) === TRUE):
        $obj = new Message;
        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login successful",$logfile);
        return 1;
    else:
        $obj = new Message;
        $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Writing user login failed",$logfile);
        return 0;
    endif;
}

if(!function_exists('hash_equals')):
    function hash_equals($str1, $str2)
    {
        if(strlen($str1) != strlen($str2)):
            return false;
        else:
            $res = $str1 ^ $str2;
            $ret = 0;
            for($i = strlen($res) - 1; $i >= 0; $i--)
            {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        endif;
    }
endif;

function downloadbulk($url, $dest)
{
    global $db, $logfile;
    $options = array(
      CURLOPT_FILE => is_resource($dest) ? $dest : fopen($dest, 'w'),
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_URL => $url,
      CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
      CURLOPT_USERAGENT => "MtGCollection/1.0",
      CURLOPT_HTTPHEADER => array("Accept: application/json;q=0.9,*/*;q=0.8"),
    );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    
    # DEBUG
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    $fp = fopen($logfile, 'a');
    curl_setopt($ch, CURLOPT_STDERR, $fp);
    # END DEBUG
    
    $return = curl_exec($ch);
    
    if ($return === false):
        return curl_error($ch);
    else:
        return true;
    endif;
}
  
function validateTrueDecimal($v) 
{	
    global $logfile;
    $result = floor($v);
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Checking $v for true decimal, result is $result",$logfile);
    return(floor($v) != $v);
    
}

function refresh_image($cardid)
{
    global $db, $logfile, $ImgLocation, $two_card_detail_sections, $serveremail, $adminemail;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Refresh image called for $cardid",$logfile);
    $sql = "SELECT id,setcode,layout FROM cards_scry WHERE id = '$cardid' LIMIT 1";
    $result = $db->query($sql);
    if ($result === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
    else:
        $row = $result->fetch_assoc();
        $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
        $imagefunction = $imageManager->getImage($row['setcode'],$cardid,$ImgLocation,$row['layout'],$two_card_detail_sections); //$ImgLocation is set in ini
        if($imagefunction['front'] != 'error'):
            $imagename = substr($imagefunction['front'], strrpos($imagefunction['front'], '/') + 1);
            $imageurl = $ImgLocation.$row['setcode']."/".$imagename;
            if (!unlink($imageurl)): 
                $imagedelete = 'failure'; 
            else:
                $imagedelete = 'success'; 
            endif;
        endif;
        if($imagefunction['back'] != '' AND $imagefunction['back'] != 'error' AND $imagefunction['back'] != 'empty'):
            $imagebackname = substr($imagefunction['back'], strrpos($imagefunction['back'], '/') + 1);
            $imagebackurl = $ImgLocation.$row['setcode']."/".$imagebackname;
            if (!unlink($imagebackurl)): 
                $imagebackdelete = 'failure'; 
            else:
                $imagebackdelete = 'success'; 
            endif;
        endif;
    endif;
    //Refresh image
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Re-fetching image for $cardid",$logfile);
    $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
    $imagefunction = $imageManager->getImage($row['setcode'],$cardid,$ImgLocation,$row['layout'],$two_card_detail_sections); //$ImgLocation is set in ini
}

function update_collection_values($collection)
{
    global $db, $logfile;
    if($findcards = $db->query("SELECT
                            `$collection`.id AS id,
                            IFNULL(`$collection`.normal,0) AS mynormal,
                            IFNULL(`$collection`.foil, 0) AS myfoil,
                            IFNULL(`$collection`.etched, 0) AS myetch,
                            topvalue,
                            IFNULL(price, 0) AS normalprice,
                            IFNULL(price_foil, 0) AS foilprice,
                            IFNULL(price_etched, 0) AS etchedprice
                            FROM `$collection` LEFT JOIN `cards_scry` 
                            ON `$collection`.id = `cards_scry`.id
                            WHERE IFNULL(`$collection`.normal,0) + IFNULL(`$collection`.foil,0) + IFNULL(`$collection`.etched,0) > 0")):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
        $i = 0;
        while($row = $findcards->fetch_array(MYSQLI_ASSOC)):
            $normalqty = $row['mynormal'];
            $normalprice = $row['normalprice'];
            $foilqty = $row['myfoil'];
            $foilprice = $row['foilprice'];
            $etchedqty = $row['myetch'];
            $etchedprice = $row['etchedprice'];
            if($normalqty * $normalprice > 0):
                $normalrate = $normalprice;
            else:
                $normalrate = 0;
            endif;
            if($foilqty * $foilprice > 0):
                $foilrate = $foilprice;
            else:
                $foilrate = 0;
            endif;
            if($etchedqty * $etchedprice > 0):
                $etchedrate = $etchedprice;
            else:
                $etchedrate = 0;
            endif;
            $selectedrate = max($normalrate,$foilrate,$etchedrate);
            $cardid = $db->real_escape_string($row['id']);
            $updatemaxqry = "INSERT INTO `$collection` (topvalue,id)
                VALUES ($selectedrate,'$cardid')
                ON DUPLICATE KEY UPDATE topvalue=$selectedrate";
            if($updatemax = $db->query($updatemaxqry)):
                //succeeded
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
            endif;
            $i = $i + 1;
        endwhile;
        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Collection value update completed",$logfile);
    else: 
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
    endif;
    return $i;
}

function update_topvalue_card($collection,$scryid)
{
    global $db, $logfile;
    if($findcards = $db->query("SELECT
                            `$collection`.id AS id,
                            IFNULL(`$collection`.normal,0) AS mynormal,
                            IFNULL(`$collection`.foil, 0) AS myfoil,
                            IFNULL(`$collection`.etched, 0) AS myetch,
                            notes,
                            topvalue,
                            IFNULL(price, 0) AS normalprice,
                            IFNULL(price_foil, 0) AS foilprice,
                            IFNULL(price_etched, 0) AS etchedprice
                            FROM `$collection` LEFT JOIN `cards_scry` 
                            ON `$collection`.id = `cards_scry`.id
                            WHERE IFNULL(`$collection`.normal,0) + IFNULL(`$collection`.foil,0) + IFNULL(`$collection`.etched,0) > 0
                            AND `$collection`.id = '$scryid'")):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
        while($row = $findcards->fetch_array(MYSQLI_ASSOC)):
            $normalqty = $row['mynormal'];
            $normalprice = $row['normalprice'];
            $foilqty = $row['myfoil'];
            $foilprice = $row['foilprice'];
            $etchedqty = $row['myetch'];
            $etchedprice = $row['etchedprice'];
            if($normalqty * $normalprice > 0):
                $normalrate = $normalprice;
            else:
                $normalrate = 0;
            endif;
            if($foilqty * $foilprice > 0):
                $foilrate = $foilprice;
            else:
                $foilrate = 0;
            endif;
            if($etchedqty * $etchedprice > 0):
                $etchedrate = $etchedprice;
            else:
                $etchedrate = 0;
            endif;
            $selectedrate = max($normalrate,$foilrate,$etchedrate);
            $cardid = $db->real_escape_string($row['id']);
            $updatemaxqry = "INSERT INTO `$collection` (topvalue,id)
                VALUES ($selectedrate,'$cardid')
                ON DUPLICATE KEY UPDATE topvalue=$selectedrate";
            if($updatemax = $db->query($updatemaxqry)):
                //succeeded
            else:
                trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
            endif;
        endwhile;
    else: 
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL: ". $db->error, E_USER_ERROR);
    endif;
    
}

function cardtypes($finishes)
{
    global $db, $logfile;
    $cardtypes = 'none';
    $card_normal = 0;
    $card_foil = 0;
    $card_etched = 0;
    foreach($finishes as $key => $value):
        if($value == 'nonfoil'):
            $card_normal = 1;
        elseif($value == 'foil'):
            $card_foil = 1;
        elseif($value == 'etched'):
            $card_etched = 1;
        endif;
    endforeach;
    if ($card_normal == 1 AND $card_foil == 1 AND $card_etched == 1):
        $cardtypes = 'normalfoiletched';
    elseif ($card_normal == 1 AND $card_foil == 1 AND $card_etched == 0):
        $cardtypes = 'normalfoil';
    elseif ($card_normal == 1 AND $card_foil == 0 AND $card_etched == 1):
        $cardtypes = 'normaletched';
    elseif ($card_normal == 0 AND $card_foil == 1 AND $card_etched == 1):
        $cardtypes = 'foiletched';
    elseif ($card_normal == 0 AND $card_foil == 0 AND $card_etched == 1):
        $cardtypes = 'etchedonly';
    elseif ($card_normal == 0 AND $card_foil == 1 AND $card_etched == 0):
        $cardtypes = 'foilonly';
    elseif ($card_normal == 1 AND $card_foil == 0 AND $card_etched == 0):
        $cardtypes = 'normalonly';
    endif;
    return $cardtypes;
}

function cardtype_for_id($id)
{
    global $db, $logfile;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up card types for card $id",$logfile);
    $stmt = $db->execute_query("SELECT finishes FROM cards_scry WHERE id = ? LIMIT 1", ["$id"]);
    if($stmt != TRUE):
        trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
    else:
        if ($stmt->num_rows > 0):
            $result = $stmt->fetch_assoc();
            if(isset($result['finishes'])):
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card $id is valid, looking up finishes",$logfile);
                $finishes = json_decode($result['finishes'], TRUE);
                $cardtypes = cardtypes($finishes);
            else:
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card $id is valid, but no finishes",$logfile);
                $cardtypes = 'none';
            endif;
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card $id has no match",$logfile);
            $cardtypes = 'nomatch';
        endif;
    endif;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card type for $id is $cardtypes",$logfile);
    return $cardtypes;
}

function import($filename)
{
    global $db, $logfile, $mytable, $useremail, $serveremail;
    //Import uploaded file to Database
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": Import starting",$logfile);
    $handle = fopen($filename, "r");
    $i = 0;
    $count = 0;
    $total = 0;
    $warningsummary = 'Warning type, Setcode, Row number, Setcode, Number, Import Name, Import Normal, Import Foil, Import Etched, Supplied ID, Database Name (if applicable), Database ID (if applicable) \n \n';
    while (($data = fgetcsv ($handle, 100000, ',')) !== FALSE):
        $idimport = 0;
        $row_no = $i + 1;
        if ($i === 0):
            if (       (strpos($data[0],'setcode') === FALSE)
                    OR (strpos($data[1],'number') === FALSE)
                    OR (strpos($data[2],'name') === FALSE)
                    OR (strpos($data[3],'normal') === FALSE)
                    OR (strpos($data[4],'foil') === FALSE)
                    OR (strpos($data[5],'etched') === FALSE) 
                    OR (strpos($data[6],'id') === FALSE)):
                echo "<h4>Incorrect file format</h4>";
                $obj = new Message;
                $obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} does not contain header row",$logfile);
                exit;
            endif;
        elseif(isset($data[0]) AND isset($data[1]) AND isset($data[2])):
            $data0 = $data[0];
            $data1 = $data[1];
            $data2 = stripslashes($data[2]);
            if (!empty($data[3])): // normal qty
                $data3 = $data[3];
            else:
                $data3 = 0;
            endif;
            if (!empty($data[4])): // foil qty
                $data4 = $data[4];
            else:
                $data4 = 0;
            endif;
            if (!empty($data[5])): // etched qty
                $data5 = $data[5];
            else:
                $data5 = 0;
            endif;
            if (!empty($data[6])): // ID
                $data6 = $data[6];
            else:
                $data6 = null;
            endif;
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Row $row_no of import file: setcode({$data0}), number({$data1}), name ({$data2}), normal ({$data3}), foil ({$data4}), etched ({$data5}), id ({$data6})",$logfile);
            $supplied_id = $data6; // id
            if (!is_null($data6)): // ID has been supplied, run an ID check / import first
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Data has an ID ($data6), checking for a match",$logfile);
                $cardtype = cardtype_for_id($data6);
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card type is: $cardtype",$logfile);
                if($cardtype == 'nomatch'):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: ID $data6 is not a valid id, trying setcode/number...",$logfile);
                    $importable = FALSE;
                elseif($cardtype == 'none'):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: ID $data6 is valid but db has no cardtype info",$logfile);
                    $importable = FALSE;
                else:
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: ID $data6 is valid and we have cardtype info",$logfile);
                    if($cardtype == 'normalfoiletched'):
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import",$logfile);
                        // All options available for import, no checks to be made
                        $importable = TRUE;
                    elseif($cardtype == 'normalfoil'):
                        if($data5 > 0):
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards",$logfile);
                            echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        else:
                            $importable = TRUE;
                        endif; 
                    elseif($cardtype == 'normaletched'):
                        if($data4 > 0):
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards",$logfile);
                            echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        else:
                            $importable = TRUE;
                        endif; 
                    elseif($cardtype == 'foiletched'):
                        if($data3 > 0):
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards",$logfile);
                            echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        else:
                            $importable = TRUE;
                        endif; 
                    elseif($cardtype == 'etchedonly'):
                        if($data3 > 0 or $data4 > 0):
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards",$logfile);
                            echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        else:
                            $importable = TRUE;
                        endif;                                                
                    elseif($cardtype == 'foilonly'):
                        if($data3 > 0 or $data5 > 0):
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards",$logfile);
                            echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        else:
                            $importable = TRUE;
                        endif;
                    elseif($cardtype == 'normalonly'):
                        if($data4 > 0 or $data5 > 0):
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards",$logfile);
                            echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                            echo "<img src='/images/error.png' alt='Error'><br>";
                            $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6"."\n";
                            $warningsummary = $warningsummary.$newwarning;
                            $i = $i + 1;
                            continue;
                        else:
                            $importable = TRUE;
                        endif;
                    endif;
                endif;
                if(isset($importable) AND $importable != FALSE):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Match found for ID $data6 with no misallocated card types, will import",$logfile);
                    $stmt = $db->prepare("  INSERT INTO
                                                `$mytable`
                                                (id,normal,foil,etched)
                                            VALUES
                                                (?,?,?,?)
                                            ON DUPLICATE KEY UPDATE
                                                id=VALUES(id),normal=VALUES(normal),foil=VALUES(foil),etched=VALUES(etched)
                                        ");
                    if ($stmt === false):
                        trigger_error('[ERROR] profile.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
                    endif;
                    $bind = $stmt->bind_param("ssss",
                                    $data6,
                                    $data3,
                                    $data4,
                                    $data5
                                );
                    if ($bind === false):
                        trigger_error('[ERROR] profile.php: Binding parameters: ' . $db->error, E_USER_ERROR);
                    endif;
                    $exec = $stmt->execute();
                    if ($exec === false):
                        trigger_error("[ERROR] profile.php: Importing row $row_no" . $db->error, E_USER_ERROR);
                    else:
                        $status = mysqli_affected_rows($db); // 1 = add, 2 = change, 0 = no change
                        if($status === 1):
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: New, imported - no error returned; return code: $status",$logfile);
                        elseif($status === 2):
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Updated - no error returned; return code: $status",$logfile);
                        else:
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: No change - no error returned; return code: $status",$logfile);
                        endif;
                    endif;
                        $stmt->close();
                    if($status === 1 OR $status === 2 OR $status === 0):
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Import query ran - checking",$logfile);
                        if($sqlcheck = $db->select_one('normal,foil,etched',$mytable,"WHERE id = '$data6'")):
                            $obj = new Message;
                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}",$logfile);
                            if (($sqlcheck['normal'] == $data3) AND ($sqlcheck['foil'] == $data4) AND ($sqlcheck['etched'] == $data5)):
                                // echo "Row $row_no: NORMAL: ID matched, successful import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) <img src='/images/success.png' alt='Success'><br>";
                                $total = $total + $sqlcheck['normal'] + $sqlcheck['foil'] + $sqlcheck['etched'];
                                $count = $count + 1;
                                $idimport = 1;
                            endif;
                        else:
                            trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                        endif;
                    endif;
                endif;    
            endif;
            if (!empty($data0) AND !empty($data1) AND !empty($data2) AND $idimport === 0): // ID import has not been successful, try with setcode, number, name
                $obj = new Message;
                $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Data place 1 (setcode - $data0), place 2 (number - $data1) place 3 (name - $data2) without ID - trying setcode/number",$logfile);
                $stmt = $db->execute_query("SELECT id,name,printed_name,flavor_name,f1_name,f1_printed_name,f1_flavor_name,f2_name,f2_printed_name,f2_flavor_name,finishes FROM cards_scry WHERE setcode = ? AND number_import = ? LIMIT 1", ["$data0","$data1"]);
                if($stmt != TRUE):
                    trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
                else:
                    if ($stmt->num_rows > 0):
                        $result = $stmt->fetch_assoc();
                        if(isset($result['name'])):
                            $db_name = $result['name'];
                            $db_id = $result['id'];
                            $db_all_names = array("{$result['name']}","{$result['printed_name']}","{$result['flavor_name']}","{$result['f1_name']}","{$result['f1_printed_name']}","{$result['f1_flavor_name']}","{$result['f2_name']}","{$result['f2_printed_name']}","{$result['f2_flavor_name']}");
                            if($db_name != $data2):
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Supplied card setcode and number do not match primary db name for id {$result['id']}, checking other db names",$logfile);
                                if(!in_array($data2,$db_all_names)):
                                    $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"No db name match for {$result['id']} (db names: $db_all_names[0], $db_all_names[1], $db_all_names[2], $db_all_names[3], $db_all_names[4], $db_all_names[5], $db_all_names[6], $db_all_names[7], $db_all_names[8])",$logfile);
                                    echo "Row $row_no: ERROR: ID and Name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                    echo "<img src='/images/error.png' alt='Error'><br>";
                                    $newwarning = "ERROR - name mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                    $warningsummary = $warningsummary.$newwarning;
                                    $i = $i + 1;
                                    continue;
                                else:
                                    $importtype = 'alternate_name';
                                    $data6 = $result['id'];
                                    $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Supplied name $data2 matches with a secondary name for id {$result['id']}, will import",$logfile);
                                endif;
                            else:
                                if(isset($result['finishes'])):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card setcode and number matches on supplied name ($data2) for db id {$result['id']}, looking up finishes",$logfile);
                                    $data6 = $result['id'];
                                    $finishes = json_decode($result['finishes'], TRUE);
                                    $cardtype = cardtypes($finishes);
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card type is: $cardtype",$logfile);
                                    if($cardtype != 'none'):
                                        if($cardtype == 'normalfoiletched'):
                                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Normal/Foil/Etched ID, no restrictions on card import",$logfile);
                                        elseif($cardtype == 'normalfoil'):
                                            if($data5 > 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Normal and Foil ID, but import contains Etched cards",$logfile);
                                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                echo "<img src='/images/error.png' alt='Error'><br>";
                                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                                $i = $i + 1;
                                                continue;
                                            endif; 
                                        elseif($cardtype == 'normaletched'):
                                            if($data4 > 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Normal and Etched ID, but import contains Foil cards",$logfile);
                                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                echo "<img src='/images/error.png' alt='Error'><br>";
                                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                                $i = $i + 1;
                                                continue;
                                            endif; 
                                        elseif($cardtype == 'foiletched'):
                                            if($data3 > 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Foil and Etched ID, but import contains Normal cards",$logfile);
                                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                echo "<img src='/images/error.png' alt='Error'><br>";
                                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                                $i = $i + 1;
                                                continue;
                                            endif; 
                                        elseif($cardtype == 'etchedonly'):
                                            if($data3 > 0 or $data4 > 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Etched-only ID, but import contains Normal and/or Foil cards",$logfile);
                                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                echo "<img src='/images/error.png' alt='Error'><br>";
                                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                                $i = $i + 1;
                                                continue;
                                            endif;                                                
                                        elseif($cardtype == 'foilonly'):
                                            if($data3 > 0 or $data5 > 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Foil-only ID, but import contains Normal and/or Etched cards",$logfile);
                                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                echo "<img src='/images/error.png' alt='Error'><br>";
                                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                                $i = $i + 1;
                                                continue;
                                            endif;
                                        elseif($cardtype == 'normalonly'):
                                            if($data4 > 0 or $data5 > 0):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Card matches to a Foil-only ID, but import contains Foil and/or Etched cards",$logfile);
                                                echo "Row $row_no: ERROR: Cardtype not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                                                echo "<img src='/images/error.png' alt='Error'><br>";
                                                $newwarning = "ERROR - Cardtype mismatch, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, $db_name, $db_id \n";
                                                $warningsummary = $warningsummary.$newwarning;
                                                $i = $i + 1;
                                                continue;
                                            endif;
                                        endif;
                                    endif;    
                                endif; 
                            endif;
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Setcode ($data0)/collector number ($data1) with supplied ID ($supplied_id) matched on name and importing as ID $data6",$logfile);
                        endif;
                    else: //if ($stmt->num_rows > 0)
                        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Card setcode and number do not match a card in db",$logfile);
                        echo "Row $row_no: ERROR: ID and name not matched, failed import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) ";
                        echo "<img src='/images/error.png' alt='Error'><br>";
                        $newwarning = "ERROR - failed to find an ID and name match, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, N/A, N/A \n";
                        $warningsummary = $warningsummary.$newwarning;
                        $i = $i + 1;
                        continue;
                    endif;
                endif;    

                if (!empty($data6)): //write the import
                    $stmt = $db->prepare("  INSERT INTO
                                                `$mytable`
                                                (id,normal,foil,etched)
                                            VALUES
                                                (?,?,?,?)
                                            ON DUPLICATE KEY UPDATE
                                                id=VALUES(id),normal=VALUES(normal),foil=VALUES(foil),etched=VALUES(etched)
                                        ");
                    if ($stmt === false):
                        trigger_error('[ERROR] profile.php: Preparing SQL: ' . $db->error, E_USER_ERROR);
                    endif;
                    $bind = $stmt->bind_param("ssss",
                                    $data6,
                                    $data3,
                                    $data4,
                                    $data5
                                );
                    if ($bind === false):
                        trigger_error('[ERROR] profile.php: Binding parameters: ' . $db->error, E_USER_ERROR);
                    endif;
                    $exec = $stmt->execute();
                    if ($exec === false):
                        trigger_error("[ERROR] profile.php: Importing row $row_no" . $db->error, E_USER_ERROR);
                    else:
                        $status = mysqli_affected_rows($db); // 1 = add, 2 = change, 0 = no change
                        if($status === 1):
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: New, imported - no error returned; return code: $status",$logfile);
                        elseif($status === 2):
                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Updated - no error returned; return code: $status",$logfile);
                        else:
                            $obj = new Message;
                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: No change - no error returned; return code: $status",$logfile);
                        endif;
                    endif;
                    $stmt->close();
                    if($status === 1 OR $status === 2 OR $status === 0):
                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Import query ran OK - checking...",$logfile);
                        if($sqlcheck = $db->select_one('normal,foil,etched',$mytable,"WHERE id = '$data6'")):
                            $obj = new Message;
                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,": Row $row_no: Check result = Normal: {$sqlcheck['normal']}; Foil: {$sqlcheck['foil']}; Etched: {$sqlcheck['etched']}",$logfile);
                            if (($sqlcheck['normal'] == $data3) AND ($sqlcheck['foil'] == $data4) AND ($sqlcheck['etched'] == $data5)):
                                if(isset($importtype) AND $importtype == 'alternate_name'):
                                    echo "Row $row_no: WARNING: Matched on alternate name, successful import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) <img src='/images/warning.png' alt='Warning'><br>";
                                    $newwarning = "WARNING - card matched to alternate card name, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $supplied_id, $db_name, $db_id \n";
                                    $warningsummary = $warningsummary.$newwarning;
                                else:
                                    // echo "Row $row_no: NORMAL: Setcode/number matched, successful import for ($data0, $data1, $data2, $data3, $data4, $data5, $data6) <img src='/images/success.png' alt='Success'><br>";
                                endif;
                                    $total = $total + $sqlcheck['normal'] + $sqlcheck['foil'] + $sqlcheck['etched'];
                                    $count = $count + 1;
                            else: ?>
                                <img src='/images/error.png' alt='Failure'><br> <?php
                            endif;
                        else:
                            trigger_error("[ERROR]: SQL failure: " . $db->error, E_USER_ERROR);
                        endif;
                    endif;
                endif;
            elseif($idimport === 1):
                // do nothing
            else:
                echo "Row ",$i+1,": Check row - not enough data to identify card <img src='/images/error.png' alt='Failure'><br>";
                $newwarning = "ERROR - not enough data to identify card, $row_no, $data0, $data1, $data2, $data3, $data4, $data5, $data6, N/A, N/A \n";
                $warningsummary = $warningsummary.$newwarning;
            endif;
        endif;
        $i = $i + 1;
    endwhile;
    fclose($handle);
    $summary = "Import done - $count unique cards, $total in total.";
    print $summary;
    $from = "From: $serveremail\r\nReturn-path: $serveremail"; 
    $subject = "Import failures / warnings"; 
    $message = "$warningsummary \n \n $summary";
    mail($useremail, $subject, $message, $from); ?>
    <script>
        (function() {
            fetch('/valueupdate.php?table=<?php echo("$mytable"); ?>');
        })();
    </script>
    <script type="text/javascript">
        alert('Import completed - a full collection value resync is being run, and can also take several minutes. Accessing your Profile page while this is running will take longer than usual.');
        window.onload=function(){document.body.style.cursor='default';}
    </script> <?php
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,": Import finished",$logfile);
}

function card_legal_db_field($decktype)
{
    global $db, $deck_legality_map, $logfile;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up db_field for legality for deck type '$decktype'",$logfile);
    $index = array_search("$decktype", array_column($deck_legality_map, 'decktype'));
    if ($index !== false) $db_field = $deck_legality_map[$index]['db_field'];
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Deck type '$decktype' has legality in '$db_field'",$logfile);
    return $db_field;
}

function deck_legal_list($decknumber,$deck_type,$db_field)
{
    global $db, $logfile;
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Getting deck legality list for $deck_type deck '$decknumber' (using db_field '$db_field')",$logfile);
    $sql = "SELECT cardnumber FROM deckcards WHERE decknumber = '$decknumber'";
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Looking up SQL: $sql",$logfile);
    $sqlresult = $db->query($sql);
    if($sqlresult === false):
        trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
    else:
        $i = 0;
        $record = array();
        while($row = $sqlresult->fetch_assoc()):
            $record[$i] = $row['cardnumber'];
            $i = $i + 1;
        endwhile;
    endif;
    $list = array();
    $p = 0;
    foreach($record as $value):
        $sql2 = "SELECT $db_field FROM cards_scry WHERE id = '$value' LIMIT 1";
        $sqlresult2 = $db->query($sql2);
        if($sqlresult2 === false):
            trigger_error('[ERROR]'.basename(__FILE__)." ".__LINE__."Function ".__FUNCTION__.": SQL failure: ". $db->error, E_USER_ERROR);
        else:
            $row2 = $sqlresult2->fetch_array(MYSQLI_ASSOC);
            $legal = $row2["$db_field"];
        endif;
        $list[$p]['id'] = $value;
        $list[$p]['legality'] = $legal;
        $p = $p + 1;
    endforeach;
    return $list;
}