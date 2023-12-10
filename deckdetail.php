<?php
/* Version:     19.1
    Date:       04/12/23
    Name:       deckdetail.php
    Purpose:    Deck detail page
    Notes:      {none}
    To do:      
    
    1.0
                Initial version
 *  2.0         
 *              Migrated to mysqli
 *  3.0     
 *              Added export deck list
 *  4.0     
 *              Added 'Need' list
 *  5.0         
 *              Added 'Quick Add'
 *  6.0         
 *              Some tweaks in Quick Add code to work with apostrophes
 *  7.0     
 *              Added Google Charts for CMC chart view
 *  8.0
 *              Added Commander capability
 *  9.0 
 *              Move to use scryfall image function
 *  10.0
 *              Moved from writelog to Message class
 *  11.0
 *              Refactoring for cards_scry
 *  12.0
 *              PHP 8.1 compatibility
 *  12.1
 *              Removed unnecessary db escaping on notes
 *  13.0
 *              Fixed quick add import not reading setcode, tidied up some logging to include line number
 *  14.0
 *              Removed ability to add more than 1 for commander decks
 *              Removed qty display for Commander decks
 *  15.0   
 *              Improved performance by making "Missing" check manually called, not every page load
 *              Updated icons
 *              Added ability to have Partner Commander
 *  16.0   
 *              Added deckname edit, and delete deck from deck detail page
 *  17.0
 *              Add functional wishlist in decks
 *              Add commander colour identity
 *  18.0
 *              25/11/23
 *              Add import, and significantly more robust Quick Add
 *  19.0
 *              03/12/23
 *              Add photo capability
 *  19.1
 *              04/12/2023
 *              Refine photo security by serving images through a php script
*/
ini_set('session.name', '5VDSjp7k-n-_yS-_');
session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
require ('includes/colour.php');
forcechgpwd();                              //Check if user is disabled or needs to change password
?> 

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title> MtG collection </title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        jQuery(document).ready(function(){
            $("img").each(function(){
            $(this).attr("onerror","this.src=’/cardimg/back.jpg'");
            });
        });
    </script>
    <script type="text/javascript"> 
        $(document).ready(function() {
            $('.deckcardimgdiv').click(function(e){
                e.stopPropagation();
                $('.deckcardimgdiv').hide("slow");
            });
        });
        $(document).click(function() {
            $('.deckcardimgdiv').hide("slow");
        });
        $(window).scroll(function() { 
            $('.deckcardimgdiv').hide("slow");
        });
    </script>  
    <script>
        function toggleForm() 
        {
          var form = document.getElementById("renameForm");
          if (form.style.display === "none") {
            form.style.display = "block";
            document.getElementById("newname").focus();
            document.getElementById("newname").select();
          } else {
            form.style.display = "none";
          }
          var form = document.getElementById("changeType");
          if (form.style.display === "none") {
            form.style.display = "block";
          } else {
            form.style.display = "none";
          }
          var form = document.getElementById("currentType");
          if (form.style.display === "none") {
            form.style.display = "block";
          } else {
            form.style.display = "none";
          }
        }
    </script>
    <script type="text/javascript"> 
        function ComparePrep()
            {
                document.body.style.cursor='wait';
            }
    </script> 
</head>

<body class="body">
<?php
include_once("includes/analyticstracking.php");
require('includes/overlays.php');
require('includes/header.php'); 
require('includes/menu.php'); //mobile menu

$redirect = false;
if (isset($_GET["missing"])):
    $missing = 'yes';
else:
    $missing = 'no';
endif;

if (isset($_GET["deck"])):
    $decknumber = filter_input(INPUT_GET, 'deck', FILTER_SANITIZE_NUMBER_INT);
    if (isset($_GET["updatetype"])):
        $updatetype = $_GET["updatetype"];
    endif;
elseif (isset($_POST["deck"])):
    $decknumber     = filter_input(INPUT_POST, 'deck', FILTER_SANITIZE_NUMBER_INT);
    $updatenotes    = isset($_POST['updatenotes']) ? 'yes' : '';
    $newnotes       = filter_input(INPUT_POST, 'newnotes', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    $newsidenotes   = filter_input(INPUT_POST, 'newsidenotes', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    $renamedeck     = isset($_POST['renamedeck']) ? 'yes' : '';
    $newname        = isset($_POST['newname']) ? filter_input(INPUT_POST, 'newname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES): '';
else: ?>
    <div id='page'>
    <div class='staticpagecontent'>
    <h3>No decknumber given - returning to your deck list...</h3>
    <meta http-equiv='refresh' content='2;url=decks.php'>
    </div>
    </div> <?php
    require('includes/footer.php');
    exit();
endif;?>
<script type="text/javascript"> 
    function CloseMe( obj )
    {
        obj.style.display = 'none';
        window.location.href="deckdetail.php?deck=<?php echo $decknumber;?>";
    }
</script> <?php
$cardtoaction   = isset($_GET['card'])          ? filter_input(INPUT_GET, 'card', FILTER_SANITIZE_SPECIAL_CHARS):'';
$deletemain     = isset($_GET['deletemain'])    ? 'yes' : '';
$deleteside     = isset($_GET['deleteside'])    ? 'yes' : '';
$maintoside     = isset($_GET['maintoside'])    ? 'yes' : '';
$sidetomain     = isset($_GET['sidetomain'])    ? 'yes' : '';
$plusmain       = isset($_GET['plusmain'])      ? 'yes' : '';
$minusmain      = isset($_GET['minusmain'])     ? 'yes' : '';
$plusside       = isset($_GET['plusside'])      ? 'yes' : '';
$minusside      = isset($_GET['minusside'])     ? 'yes' : '';
$valid_commander = array("yes","no");
if(isset($_GET['commander']) AND (in_array($_GET['commander'],$valid_commander))):
    $commander = $_GET['commander'];
else:
    $commander = '';
endif;
if(isset($_GET['partner']) AND (in_array($_GET['partner'],$valid_commander))):
    $partner = $_GET['partner'];
else:
    $partner = '';
endif;

// Check to see if the called deck belongs to the logged in user.
$obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Checking deck $decknumber",$logfile);
if(deckownercheck($decknumber,$user) == FALSE): ?>
    <div id='page'>
    <div class='staticpagecontent'>
    <h3>This deck is not yours... returning to your deck page...</h3>
    <meta http-equiv='refresh' content='2;url=decks.php'>
    </div>
    </div> <?php
    require('includes/footer.php');
    exit();
endif;

// Update notes if called before reading info
if ((isset($updatenotes)) AND ($updatenotes == 'yes')):
    if ($db->execute_query("UPDATE decks SET notes = ?, sidenotes = ? WHERE decknumber = ?",[$newnotes,$newsidenotes,$decknumber]) === FALSE):
        trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
    else:
        $redirect = true;
    endif;
endif;

// Update name if called before reading info (we've already checked ownership)
if(isset($_POST['newname'])):
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Renaming deck to $newname",$logfile);
    $renameresult = renamedeck($decknumber,$newname,$user);
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Renaming deck result: $renameresult",$logfile);
    if($renameresult == 2):
        ?>
        <div class="msg-new error-new" onclick='CloseMe(this)'><span>Deck name exists already</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <?php
    elseif($renameresult > 0):
         ?>
        <div class="msg-new error-new" onclick='CloseMe(this)'><span>Unknown error</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <?php
    else:
        $redirect = true;
    endif;
endif;

//Update deck type if called before reading info
if (isset($updatetype)):
    if(in_array($updatetype,$validtypes)):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Updating deck type to '$updatetype'",$logfile);
        if ($db->execute_query("UPDATE decks set type = ? WHERE decknumber = ?",[$updatetype,$decknumber]) === FALSE):
            trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
        else:
            if(!in_array($updatetype,$commander_decktypes)):
                if ($db->execute_query("UPDATE deckcards SET commander = 0 WHERE decknumber = ?",[$decknumber]) === FALSE):    
                    trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
                endif;
            endif;
        endif;
    else:
        trigger_error('[ERROR] deckdetail.php: Error: Invalid deck type', E_USER_ERROR);
    endif;
    
    // Set quantities to 1 for commander decks
    if(in_array($updatetype,$commander_decktypes)):
        $query = 'UPDATE deckcards SET cardqty=? WHERE (decknumber = ? AND (sideqty IS NULL or sideqty = 0) )';
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Updating deck type to a Commander type, setting quantities to 1",$logfile);
        if ($db->execute_query($query, [1,$decknumber]) != TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
        endif;
        $query = 'UPDATE deckcards SET sideqty=? WHERE (decknumber = ? AND (cardqty IS NULL or cardqty = 0) )';
        if ($db->execute_query($query, [1,$decknumber]) != TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
        endif;
        $query = 'UPDATE deckcards SET sideqty = NULL WHERE (decknumber = ? AND cardqty > 0)';
        if ($db->execute_query($query, [$decknumber]) != TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
        endif;
    endif;
    if($updatetype == 'Wishlist'):
        $query = 'UPDATE deckcards SET sideqty = NULL WHERE (decknumber = ? AND cardqty > 0)';
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Updating deck type to a Wishlist, deleting sideboard cards",$logfile);
        if ($db->execute_query($query, [$decknumber]) != TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
        endif;
    endif;
    $redirect = true;
endif;

//Carry out quick add requests
if (isset($_GET["quickadd"])):
    $deckManager = new DeckManager($db, $logfile);
    $cardtoadd = $deckManager->processInput($decknumber,$_GET["quickadd"]);
endif;

//Deck import
if (isset($_POST['import'])):
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import called, checking file uploaded...",$logfile);
    if (is_uploaded_file($_FILES['filename']['tmp_name'])):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} uploaded",$logfile);
        $file = fopen($_FILES['filename']['tmp_name'], 'r');
        $deckManager = new DeckManager($db, $logfile);
        // Read the entire file content into a variable
        $fileContent = fread($file, filesize($_FILES['filename']['tmp_name']));
        fclose($file);
        
        // Call the processInput method with the decknumber and file content
        $deckManager->processInput($decknumber, $fileContent);
        $redirect = true;
    else:
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Import file {$_FILES['filename']['name']} failed",$logfile);
    endif; 
endif;

// Get deck details from database
if($deckinfoqry = $db->execute_query("SELECT deckname,notes,sidenotes,type FROM decks WHERE decknumber = ? LIMIT 1",[$decknumber])):
    $deckinfo = $deckinfoqry->fetch_assoc();
    $deckname   = $deckinfo['deckname'];
    $notes      = $deckinfo['notes'];
    $sidenotes  = $deckinfo['sidenotes'];
    $decktype   = $deckinfo['type'];
else:
    trigger_error('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ".$db->error, E_USER_ERROR);
endif;

// Get relevant db_field with legality
if($decktype != ''):
    $db_field = card_legal_db_field($decktype);
else:
    $db_field = '';
endif;
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Legality db-field for this deck is '$db_field'",$logfile);

// Get deck legalities
if($db_field != ''):
    $deck_legality_list = deck_legal_list($decknumber,$decktype,$db_field);
else:
    $deck_legality_list = '';
endif;

// Add / delete, before calling the deck list
if($deletemain == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,"main","all");
    $redirect = true;
elseif($deleteside == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,"side","all");
    $redirect = true;
elseif($maintoside == 'yes'):
    if (subtractdeckcard($decknumber,$cardtoaction,'main','1') != "-error"):
        adddeckcard($decknumber,$cardtoaction,"side","1");
    endif;
    $redirect = true;
elseif($sidetomain == 'yes'):
    if (subtractdeckcard($decknumber,$cardtoaction,'side','1') != "-error"):
        adddeckcard($decknumber,$cardtoaction,"main","1");
    endif;
    $redirect = true;
elseif($plusmain == 'yes'):
    adddeckcard($decknumber,$cardtoaction,"main","1");
    $redirect = true;
elseif($minusmain == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,'main','1');
    $redirect = true;
elseif($plusside == 'yes'):
    adddeckcard($decknumber,$cardtoaction,"side","1");
    $redirect = true;
elseif($minusside == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,'side','1');
    $redirect = true;
elseif($commander == 'yes'):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Adding Commander to deck $decknumber: $cardtoaction",$logfile);
    addcommander($decknumber,$cardtoaction);
    $redirect = true;
elseif($partner == 'yes'):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Moving Commander to Partner for deck $decknumber: $cardtoaction",$logfile);
    addpartner($decknumber,$cardtoaction);
    $redirect = true;
elseif($commander == 'no'):
    delcommander($decknumber,$cardtoaction);
    $redirect = true;
endif;

// PRG
if($redirect == true): ?>
    <meta http-equiv='refresh' content='0; url=deckdetail.php?deck=<?php echo $decknumber; ?>'> <?php
    exit();
endif;

//Get card list
$mainquery = ("SELECT *,cards_scry.id AS cardsid 
                        FROM deckcards 
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id 
                    WHERE decknumber = ? AND cardqty > 0 ORDER BY name");
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": $mainquery",$logfile);
$result = $db->execute_query($mainquery, [$decknumber]);
if ($result != TRUE):
    trigger_error("[ERROR] Line ".__LINE__." - SQL failure: Error: " . $db->error, E_USER_ERROR);
endif;

$sidequery = ("SELECT *,cards_scry.id AS cardsid 
                        FROM deckcards 
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id 
                    WHERE decknumber = ? AND sideqty > 0 ORDER BY name");
$sideresult = $db->execute_query($sidequery, [$decknumber]);
if ($sideresult != TRUE):
    trigger_error("[ERROR] Line ".__LINE__." - SQL failure: Error: " . $db->error, E_USER_ERROR);
endif;

//Initialise variables to 0
$cdr = $creatures = $instantsorcery = $other = $lands = $deckvalue = 0;
$deck_colour_mismatch = $illegal_cards = '';

//Illegal card style tags
$red_font_tag = "style='color: OrangeRed; font-weight: bold'";
$firebrick_font_tag = "style='color: FireBrick; font-weight: bold'";

//This section works out which cards the user DOES NOT have, for later linking
// in a text file to download
$resultnames = array();
while ($row = $result->fetch_assoc()):
    if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
        $row['name'] = $row['flavor_name'];
    endif;
    if(!in_array($row['name'], $resultnames)):
        $resultnames[] = $row['name'];
    endif;
endwhile;
while ($row = $sideresult->fetch_assoc()):
    if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
        $row['name'] = $row['flavor_name'];
    endif;
    if(!in_array($row['name'], $resultnames)):
        $resultnames[] = $row['name'];
    endif;
endwhile;
$uniquecardscount = count($resultnames);
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Cards in deck: $uniquecardscount",$logfile);
$requiredlist = '';
$requiredbuy = '';
if($uniquecardscount > 0):
    //reset the results and get the quantities for each into an array with matching $key
    mysqli_data_seek($result, 0);
    mysqli_data_seek($sideresult, 0);
    $resultqty = array_fill(0,$uniquecardscount,'0'); //create an array the right size, all '0'
    //write total of each unique name to the results array
    while ($row = $result->fetch_assoc()):
        $qty = $row['cardqty'] + $row['sideqty'];
        if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
            $row['name'] = $row['flavor_name'];
        endif;
        $key = array_search($row['name'], $resultnames);
        $resultqty[$key] = $resultqty[$key] + $qty;
    endwhile;
    while ($row = $sideresult->fetch_assoc()):
        if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
            $row['name'] = $row['flavor_name'];
        endif;
        $qty = $row['cardqty'] + $row['sideqty'];
        $key = array_search($row['name'], $resultnames);
        $resultqty[$key] = $resultqty[$key] + $qty;
    endwhile;

    if($missing == 'yes'):
        $shortqty = array_fill(0,$uniquecardscount,'0'); //create an array the right size, all '0'
        foreach($resultnames as $key=>$value):
            $searchname = $db->real_escape_string($value);
            $query = "SELECT SUM(IFNULL(`$mytable`.etched, 0)) + SUM(IFNULL(`$mytable`.foil, 0)) + SUM(IFNULL(`$mytable`.normal, 0)) as allcopies from cards_scry LEFT JOIN $mytable ON cards_scry.id = $mytable.id WHERE name = '$searchname'";
            if ($totalresult = $db->query($query)):
                $totalrow = $totalresult->fetch_assoc();
                $total = $totalrow['allcopies'];
                $shortqty[$key] = $resultqty[$key] - $total;
                if($shortqty[$key] < 1):
                    $shortqty[$key] = 0;
                else:
                    $requiredlist = $requiredlist.$shortqty[$key]." x ".$value."\r\n";
                    $requiredbuy = $requiredbuy.$shortqty[$key]." ".$value."||";
                endif;
            else:
                //
            endif;
        endforeach;
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Cards required list: $requiredlist",$logfile);
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": Cards required buy: $requiredbuy",$logfile);
    endif;
endif;

//This section builds hidden divs for each card with the image and a link,
// and increments type and value counters
// for main and side
// It also builds the legal Colour identity for Commander decks
mysqli_data_seek($result, 0);
$cdrSet = FALSE;
$cdr_colours = array();
$i = 0;
while ($row = $result->fetch_assoc()):
    if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
        $row['name'] = $row['flavor_name'];
    endif;
    if($row['commander'] != 0 AND $row['commander'] != NULL):
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking card, colour identity {$row['color_identity']}",$logfile);
        //card is a commander, get its colour identity
        $cdrSet = TRUE;
        $cdr_colours[$i] = $row['color_identity'];
        $i = $i + 1;
    endif;
    $cardset = strtolower($row['setcode']);
    if (strpos($row['type'],' //') !== false):
        $len = strpos($row['type'], ' //');
        $row['type'] = substr($row['type'], 0, $len);
    endif;
    if ((strpos($row['type'],'Creature') !== false) AND ($row['commander'] == 0)):
        $creatures = $creatures + $row['cardqty'];
    elseif ((strpos($row['type'],'Sorcery') !== false) OR (strpos($row['type'],'Instant') !== false)):  
        $instantsorcery = $instantsorcery + $row['cardqty'];
    elseif ((strpos($row['type'],'Sorcery') === false) AND (strpos($row['type'],'Instant') === false) AND (strpos($row['type'],'Creature') === false) AND (strpos($row['type'],'Land') === false) AND ($row['commander'] == 0)):
        $other = $other + $row['cardqty'];
    elseif (strpos($row['type'],'Land') !== false):
        $lands = $lands + $row['cardqty'];
    endif;
    $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
    $imagefunction = $imageManager->getImage($cardset,$row['cardsid'],$ImgLocation,$row['layout'],$two_card_detail_sections);
    if($imagefunction['front'] == 'error'):
        $imageurl = '/cardimg/back.jpg';
    else:
        $imageurl = $imagefunction['front'];
    endif;
    $deckcardname = str_replace("'",'&#39;',$row["name"]); 
    $deckvalue = $deckvalue + ($row['price_sort'] * $row['cardqty']);
    $cardref = str_replace('.','-',$row['cardsid']);
    ?>
    <div class='deckcardimgdiv' id='card-<?php echo $cardref;?>'>
        <a href='carddetail.php?setabbrv=<?php echo $row['setcode'] ?>&amp;number=<?php echo $row['number'] ?>&amp;id=<?php echo $row['cardsid'] ?>' target='_blank'>
            <img alt='<?php echo $deckcardname;?>' class='deckcardimg' src='<?php echo $imageurl;?>'></a>
    </div> 
    <?php
endwhile;

if(isset($cdrSet) AND $cdrSet === TRUE):
    // Finalise allowable colour identity for Commander decks
    $cdr_colours_raw = $cdr_colours = '["'.count_chars( str_replace(array('"','[',']',',',' '),'',implode(",",$cdr_colours)),3).'"]';
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Commander value (variable i) is $i, Colour identity to check is $cdr_colours",$logfile);

    if($i > 0 AND $cdr_colours == '[""]'):
        $cdr_colours = '["C"]';
    endif;
    $cdr_colours = colourfunction($cdr_colours);
else:
    $cdr_colours_raw = $cdr_colours = "";
endif;

mysqli_data_seek($sideresult, 0);
while ($row = $sideresult->fetch_assoc()):
    if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
        $row['name'] = $row['flavor_name'];
    endif;
    $cardset = strtolower($row["setcode"]);
    $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
    $imagefunction = $imageManager->getImage($cardset,$row['cardsid'],$ImgLocation,$row['layout'],$two_card_detail_sections);
    if($imagefunction['front'] == 'error'):
        $imageurl = '/cardimg/back.jpg';
    else:
        $imageurl = $imagefunction['front'];
    endif;
    $deckvalue = $deckvalue + ($row['price_sort'] * $row['sideqty']);
    $cardref = str_replace('.','-',$row['cardsid']);
    ?>
    <div class='deckcardimgdiv' id='side-<?php echo $cardref;?>'>
        <a href='carddetail.php?setabbrv=<?php echo $row['setcode'] ?>&amp;number=<?php echo $row['number'] ?>&amp;id=<?php echo $row['cardsid'] ?>' target='_blank'>
            <img alt='<?php echo $row["name"];?>' class='deckcardimg' src='<?php echo $imageurl;?>'></a>
    </div>
    <?php
endwhile;

// Next the main DIV section ?>
<?php
if(isset($cardtoadd) AND ($cardtoadd == 'cardnotfound' OR $cardtoadd == 'cardnotadded')): ?>
    <div class="msg-new error-new" onclick='CloseMe(this)'><span>That didn't work... check card name</span>
        <br>
        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
    </div>
<?php
elseif(isset($cardtoadd)): ?>
    <meta http-equiv='refresh' content='0; url=deckdetail.php?deck=<?php echo $decknumber; ?>'> <?php
    exit();
endif;
?>
<div id="page">
    <div class="staticpagecontent">
        <div id="decklist">
            <span id="printtitle" class="headername">
                <img src="images/white_m.png"> MtG collection
            </span>
            <form id="deletedeck" action="decks.php" method="POST">
                <input type='hidden' name="deletedeck" value="yes">
                <input type='hidden' name="decktodelete" value="<?php echo $decknumber; ?>">
            </form>
            <h2 class='h2pad'><?php echo $deckname; ?> &nbsp; 
                <span 
                    title="Delete"
                    onmouseover="" 
                    style="cursor: pointer;"
                    onclick="if(confirm('Confirm OK to delete deck?')) document.getElementById('deletedeck').submit();"
                    class='material-symbols-outlined'>
                    delete
                </span>
                &nbsp;
                <span
                    title="Edit"
                    onclick="toggleForm()"
                    onmouseover=""
                    style="cursor: pointer;"
                    class='material-symbols-outlined'>
                    edit
                </span>
            </h2>
                <form id="renameForm" style="display: none;" action="?" method="POST">
                    <br><textarea class='textinput' id='newname' name='newname' rows='1' cols='30' placeholder="New deck name" autofocus></textarea>
                    <input type='hidden' id='renamedeck' name='renamedeck' value='yes'>
                    <input type='hidden' id='deck' name='deck' value="<?php echo $decknumber; ?>">
                    <input class='inline_button stdwidthbutton noprint' type="submit" value="RENAME">
                </form>
                <script type="text/javascript">
                    document.getElementById('renameForm').addEventListener('submit', function(event) {
                      event.preventDefault(); // Prevent form submission
                      var fieldValue = document.getElementById('newname').value;
                      if (fieldValue.trim() === '') {
                        alert('Rename field cannot be empty');
                        return;
                      }
                      else if (fieldValue.trim() === '<?php echo $deckname; ?>') 
                      {
                        alert('To cancel rename click edit button again');
                        return;
                      }
                      else
                      {
                        this.submit();
                      }
                    });
                </script> <?php
                if ($decktype == ''):
                    $decktype = "<i>Not set, click edit above</i>";
                endif;        ?>
                <h3>Deck type:<br><span id="currentType"><?php echo "<span style='font-weight:500' >$decktype</span><br></span>"; ?></h3>
                <form id="changeType" style="display: none;">
                    <select class='dropdown' size="1" name="updatetype" onchange='this.form.submit()'>
                        <option <?php if($decktype=='' OR $decktype == "<i>Not set, click edit above</i>"):echo "selected='selected'";endif;?>disabled='disabled'>Pick one</option>
                        <?php 
                        foreach($validtypes as $deck):
                            if ($decktype == $deck):
                                echo "<option value='$deck' selected='selected'>$deck</option>";
                            else:
                                echo "<option value='$deck'>$deck</option>";
                            endif;
                        endforeach; ?>
                    </select>    
                    <input type="hidden"name="deck" value="<?php echo $decknumber;?>" />
                </form>
                
            <?php 
            if(in_array($decktype,$commander_decktypes) and $i > 0):
                if($cdr_colours == 'five'):
                    $identity_title = 'All';
                else:
                    $identity_title = ucfirst($cdr_colours);
                endif;
                echo "Colour identity: <img alt='image' src=images/".$cdr_colours."_s.png> ($identity_title)<br>"; 
            endif;?>   
            
            <table class='deckcardlist'>
                <tr class='deckcardlisthead'> 
                    <td class='deckcardlisthead1'>
                        <span class="noprint">Card</span>
                    </td>
                    <?php 
                    if(in_array($decktype,$commander_decktypes)): ?>    
                        <td class="deckcardlisthead3">
                            <span class="noprint">Cdr</span>
                        </td> <?php
                    endif;
                    ?>
                    <td class="deckcardlisthead3">
                        <span class="noprint">Del</span>
                    </td>
                    <?php
                    if($decktype != 'Wishlist'): ?>
                        <td class='deckcardlisthead3'>
                            <span class="noprint">Side</span>
                        </td> <?php 
                    endif;
                    if(!in_array($decktype,$commander_decktypes)): ?>    
                        <td class='deckcardlisthead3 deckcardlistright'>
                            <span class="noprint">- &nbsp;</span>
                        </td>
                        <td class='deckcardlisthead3'>
                            <span class="noprint">Qty</span>
                        </td>
                        <td class='deckcardlisthead3 deckcardlistleft'>
                            <span class="noprint">&nbsp;+</span>
                        </td> <?php
                    endif; ?>
                </tr> 
                <?php
                // Only show this row if the decktype is Commander style
                if(in_array($decktype,$commander_decktypes)): 
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, adding commander row",$logfile);
                    ?>
                    <tr>
                        <td colspan='4'>
                            <i><b>Commander</b></i>
                        </td>    
                    </tr>
                    <?php 
                    $textfile = "Commander\r\n\r\n";
                    $total    = 0;
                    $cmc[0]   = 0;
                    $cmc[1]   = 0;
                    $cmc[2]   = 0;
                    $cmc[3]   = 0;
                    $cmc[4]   = 0;
                    $cmc[5]   = 0;
                    $cmc[6]   = 0;
                    $cmctotal = 0;
                    if (mysqli_num_rows($result) > 0):
                        mysqli_data_seek($result, 0);
                        $commandercount = 0;
                        while ($row = $result->fetch_assoc()):
                            if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                                $row['name'] = $row['flavor_name'];
                            endif;
                            if ($row['commander'] == 1):
                                $cardname = $row["name"];
                                $rarity = $row["rarity"];
                                $quantity = $row["cardqty"];
                                $cardset = strtolower($row["setcode"]);
                                $cardref = str_replace('.','-',$row['cardsid']);
                                $cardid = $row['cardsid'];
                                $cardnumber = $row["number"];
                                if($deck_legality_list != ''):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for main deck card '$cardname'",$logfile);
                                    $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                    if ($index !== false):
                                        $card_legal = $deck_legality_list[$index]['legality'];
                                        if($card_legal === 'legal' OR $card_legal === NULL):
                                            $illegal_tag = '';
                                        else:
                                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                            $illegal_cards = TRUE;
                                        endif;
                                    else:
                                        $illegal_tag = '';
                                    endif;
                                else:
                                    $illegal_tag = '';
                                endif;
                                
                                $cardcmc = round($row["cmc"]);
                                $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                if ($cardcmc > 5):
                                    $cardcmc = 6;
                                endif;
                                $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; 
                                $commandername = $cardname;
                                ?>
                                <tr class='deckrow'>
                                <td class="deckcardname">
                                    <?php echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a>"; ?>
                                <script type="text/javascript">
                                    $('#<?php echo $cardref;?>-taphover').on('click',function(e) {
                                        'use strict'; //satisfy code inspectors
                                        var link = $(this); //preselect the link
                                        $('.deckcardimgdiv').hide("slow");
                                        e.preventDefault();
                                        $("<?php echo "#card-$cardref";?>").show("slow");
                                        return false; //extra, and to make sure the function has consistent return points
                                    });
                                </script>
                                <?php
                                echo "<td class='deckcardlistcenter noprint'>";
                                $validpartner = FALSE;
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, checking if $cardname is a valid partner or background",$logfile);
                                $i = 0;
                                while($i < count($second_commander_text)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$second_commander_text[$i]) == TRUE):
                                        $validpartner = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                if($validpartner == TRUE):
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Move to Partner"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;partner=yes'" 
                                        class='material-symbols-outlined'>
                                        south_east
                                    </span>
                                    <?php
                                else:
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Move to main deck"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;commander=no'" 
                                        class='material-symbols-outlined'>
                                        arrow_downward
                                    </span>
                                    <?php
                                endif;
                                echo "</td>";
                                echo "</td>";
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Delete"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deletemain=yes'" 
                                    class='material-symbols-outlined'>
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span 
                                    onmouseover=""  
                                    title="Move to sideboard"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;maintoside=yes'" 
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                                if(!in_array($decktype,$commander_decktypes)):
                                    echo "<td class='deckcardlistcenter'>";
                                    echo $quantity;
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                                $total = $total + $quantity;
                                $commandercount = $commandercount +1;
                                $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                            endif;
                        endwhile; 
                    endif; 
                    if(in_array($decktype,$commander_decktypes)):
                        ?>
                        <tr>
                            <td colspan='4'>
                                <i><b>Partner / Background</b></i>
                            </td>    
                        </tr>
                    <?php
                        if (mysqli_num_rows($result) > 0):
                            mysqli_data_seek($result, 0);
                            while ($row = $result->fetch_assoc()):
                                if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                                    $row['name'] = $row['flavor_name'];
                                endif;
                                if ($row['commander'] == 2):
                                    $cardname = $row["name"];
                                    $rarity = $row["rarity"];
                                    $quantity = $row["cardqty"];
                                    $cardset = strtolower($row["setcode"]);
                                    $cardref = str_replace('.','-',$row['cardsid']);
                                    $cardid = $row['cardsid'];
                                    $cardnumber = $row["number"];
                                    if($deck_legality_list != ''):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for main deck card '$cardname'",$logfile);
                                        $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                        if ($index !== false):
                                            $card_legal = $deck_legality_list[$index]['legality'];
                                            if($card_legal === 'legal' OR $card_legal === NULL):
                                                $illegal_tag = '';
                                            else:
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                                $illegal_cards = TRUE;
                                            endif;
                                        else:
                                            $illegal_tag = '';
                                        endif;
                                    else:
                                        $illegal_tag = '';
                                    endif;
                                    $cardcmc = round($row["cmc"]);
                                    $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                    if ($cardcmc > 5):
                                        $cardcmc = 6;
                                    endif;
                                    $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; 
                                    $secondcommandername = $cardname;
                                    $warnings = TRUE;
                                    ?>
                                    <tr class='deckrow'>
                                    <td class="deckcardname">
                                        <?php echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; ?>
                                    <script type="text/javascript">
                                        $('#<?php echo $cardref;?>-taphover').on('click',function(e) {
                                            'use strict'; //satisfy code inspectors
                                            var link = $(this); //preselect the link
                                            $('.deckcardimgdiv').hide("slow");
                                            e.preventDefault();
                                            $("<?php echo "#card-$cardref";?>").show("slow");
                                            return false; //extra, and to make sure the function has consistent return points
                                        });
                                    </script>
                                    <?php
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Move to main deck"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;commander=no'" 
                                        class='material-symbols-outlined'>
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Delete" 
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deletemain=yes'" 
                                        class='material-symbols-outlined'>
                                        delete_forever
                                    </span>
                                    <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                    <span 
                                        onmouseover=""  
                                        title="Move to sideboard"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;maintoside=yes'" 
                                        class='material-symbols-outlined'>
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                    if(!in_array($decktype,$commander_decktypes)):
                                        echo "<td class='deckcardlistcenter'>";
                                        echo $quantity;
                                        echo "</td>";
                                    endif;
                                    echo "</tr>";
                                    $total = $total + $quantity;
                                    $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                                endif;
                            endwhile; 
                        endif; 
                    endif;?>
                    <tr>
                        <td colspan='4'>
                            <i><b>Creatures (<?php echo $creatures; ?>)</b></i>
                        </td>    
                    </tr>
                    <?php 
                    $textfile = $textfile."\r\n\r\nCreatures\r\n\r\n";
                else:
                    ?>
                    <tr>
                        <?php 
                        if(in_array($decktype,$commander_decktypes)): ?>    
                            <td colspan='4'> <?php
                        elseif($decktype == 'Wishlist'): ?>
                            <td colspan='5'> <?php
                        else: ?>
                            <td colspan='6'> <?php
                        endif; ?>
                            <i><b>Creatures (<?php echo $creatures; ?>)</b></i>
                        </td>    
                    </tr>
                    <?php 
                    $textfile = "Creatures\r\n\r\n";
                    $total    = 0;
                    $cmc[0]   = 0;
                    $cmc[1]   = 0;
                    $cmc[2]   = 0;
                    $cmc[3]   = 0;
                    $cmc[4]   = 0;
                    $cmc[5]   = 0;
                    $cmc[6]   = 0;
                    $cmctotal = 0;
                endif;
                $deckcard_no = 1; // Initialise card count for random draw
                if (mysqli_num_rows($result) > 0):
                mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;
                        if (strpos($row['type'],' //') !== false):
                            $len = strpos($row['type'], ' //');
                            $row['type'] = substr($row['type'], 0, $len);
                        endif;
                        if ((strpos($row['type'],'Creature') !== false) AND ($row['commander'] < 1)):
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            while ($rowqty < $quantity):
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            if($deck_legality_list != ''):
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for main deck card '$cardname'",$logfile);
                                $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                if ($index !== false):
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if($card_legal === 'legal' OR $card_legal === NULL):
                                        $illegal_tag = '';
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                        $illegal_cards = TRUE;
                                    endif;
                                else:
                                    $illegal_tag = '';
                                endif;
                            else:
                                $illegal_tag = '';
                            endif;
                            if(in_array($decktype,$commander_decktypes) AND $illegal_tag == ''):
                                $colour_id = count_chars( str_replace(array('"','[',']',',',' '),'',$row['color_identity']),3);
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is $colour_id",$logfile);
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach($colour_id_array as $value):
                                    if(strpos($cdr_colours_raw,$value) == FALSE):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity not OK with Commander(s)",$logfile);
                                        $card_colour_mismatch = TRUE;
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity is OK with Commander(s)",$logfile);
                                    endif;
                                endforeach;
                                if($card_colour_mismatch == '' OR $colour_id == ''):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is OK with Commander(s)",$logfile);
                                    $wrong_colour_tag = '';
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity not OK with Commander(s)",$logfile);
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = TRUE;
                                endif;
                            endif;
                            $cardcmc = round($row["cmc"]);
                            $cardlegendary = $row["type"];
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5):
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php 
                                $i = 0;
                                $cdr_1_plus = FALSE;
                                while($i < count($commander_multiples)):
                                    if(isset($row['type']) AND str_contains($row['type'],$commander_multiples[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while($i < count($any_quantity)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$any_quantity[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                if(in_array($decktype,$commander_decktypes) AND $cdr_1_plus == TRUE):
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$quantity $cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                else:
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                endif;
                                ?>
                            <script type="text/javascript">
                                $('#<?php echo $cardref;?>-taphover').on('click',function(e) {
                                    'use strict'; //satisfy code inspectors
                                    var link = $(this); //preselect the link
                                    $('.deckcardimgdiv').hide("slow");
                                    e.preventDefault();
                                    $("<?php echo "#card-$cardref";?>").show("slow");
                                    return false; //extra, and to make sure the function has consistent return points
                                });
                            </script>
                            <?php
                            if(in_array($decktype,$commander_decktypes)):
                                $validcommander = FALSE;
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, checking if $cardname is a valid commander",$logfile);
                                if((strpos($cardlegendary, "Legendary") !== false) AND (strpos($cardlegendary, "Creature") !== false)):
                                    $validcommander = TRUE;
                                endif;
                                $i = 0;
                                while($i < count($valid_commander_text)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$valid_commander_text[$i]) == TRUE):
                                        $validcommander = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                echo "<td class='deckcardlistcenter noprint'>";
                                if($validcommander == TRUE):
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Move to Commander"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;commander=yes'" 
                                        class='material-symbols-outlined'>
                                        person
                                    </span>
                                    <?php
                                endif;
                                echo "</td>";
                            endif;
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span 
                                onmouseover="" 
                                title="Delete"
                                style="cursor: pointer;" 
                                onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deletemain=yes'" 
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if($decktype != 'Wishlist'):
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Move to sideboard"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;maintoside=yes'" 
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if(!in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Remove one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;minusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter'>";
                                echo $quantity;
                                echo "</td>";
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Add one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;plusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                            $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                        endif;
                    endwhile; 
                endif; ?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commander_decktypes)): ?>    
                        <td colspan='4'> <?php
                    elseif($decktype == 'Wishlist'): ?>
                        <td colspan='5'> <?php
                    else: ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>Instants and Sorceries (<?php echo $instantsorcery; ?>)</b></i>
                    </td>    
                </tr>
                <?php 
                $textfile = $textfile."\r\n\r\nInstants and Sorceries\r\n\r\n";
                if (mysqli_num_rows($result) > 0):
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;
                        if (strpos($row['type'],' //') !== false):
                            $len = strpos($row['type'], ' //');
                            $row['type'] = substr($row['type'], 0, $len);
                        endif;
                        if ((strpos($row['type'],'Sorcery') !== false) OR (strpos($row['type'],'Instant') !== false)):
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            while ($rowqty < $quantity):
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            if($deck_legality_list != ''):
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for main deck card '$cardname'",$logfile);
                                $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                if ($index !== false):
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if($card_legal === 'legal' OR $card_legal === NULL):
                                        $illegal_tag = '';
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                        $illegal_cards = TRUE;
                                    endif;
                                else:
                                    $illegal_tag = '';
                                endif;
                            else:
                                $illegal_tag = '';
                            endif;
                            if(in_array($decktype,$commander_decktypes) AND $illegal_tag == ''):
                                $colour_id = count_chars( str_replace(array('"','[',']',',',' '),'',$row['color_identity']),3);
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is $colour_id",$logfile);
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach($colour_id_array as $value):
                                    if(strpos($cdr_colours_raw,$value) == FALSE):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity not OK with Commander(s)",$logfile);
                                        $card_colour_mismatch = TRUE;
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity is OK with Commander(s)",$logfile);
                                    endif;
                                endforeach;
                                if($card_colour_mismatch == '' OR $colour_id == ''):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is OK with Commander(s)",$logfile);
                                    $wrong_colour_tag = '';
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity not OK with Commander(s)",$logfile);
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = TRUE;
                                endif;
                            endif;
                            $cardcmc = round($row["cmc"]);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5):
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php 
                                $i = 0;
                                $cdr_1_plus = FALSE;
                                while($i < count($commander_multiples)):
                                    if(isset($row['type']) AND str_contains($row['type'],$commander_multiples[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while($i < count($any_quantity)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$any_quantity[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                if(in_array($decktype,$commander_decktypes) AND $cdr_1_plus == TRUE):
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$quantity $cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                else:
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                endif;
                                ?>
                            <script type="text/javascript">
                                $('#<?php echo $cardref;?>-taphover').on('click',function(e) {
                                    'use strict'; //satisfy code inspectors
                                    var link = $(this); //preselect the link
                                    $('.deckcardimgdiv').hide("slow");
                                    e.preventDefault();
                                    $("<?php echo "#card-$cardref";?>").show("slow");
                                    return false; //extra, and to make sure the function has consistent return points
                                });
                            </script>
                            <?php
                            echo "</td>";
                            if(in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span 
                                onmouseover="" 
                                title="Delete"
                                style="cursor: pointer;" 
                                onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deletemain=yes'" 
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if($decktype != 'Wishlist'):
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Move to sideboard"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;maintoside=yes'" 
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if(!in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Remove one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;minusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter'>";
                                echo $quantity;
                                echo "</td>";
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Add one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;plusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity; 
                            $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                        endif;
                    endwhile; 
                endif; ?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commander_decktypes)): ?>    
                        <td colspan='4'> <?php
                    elseif($decktype == 'Wishlist'): ?>
                        <td colspan='5'> <?php
                    else: ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>Other (<?php echo $other; ?>)</b></i>
                    </td>    
                </tr>
                <?php 
                $textfile = $textfile."\r\n\r\nOther\r\n\r\n";
                if (mysqli_num_rows($result) > 0):
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;
                        if (strpos($row['type'],' //') !== false):
                            $len = strpos($row['type'], ' //');
                            $row['type'] = substr($row['type'], 0, $len);
                        endif;
                        if ((strpos($row['type'],'Sorcery') === false) AND (strpos($row['type'],'Instant') === false) AND (strpos($row['type'],'Creature') === false) AND (strpos($row['type'],'Land') === false) AND ($row['commander'] < 1)):
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            while ($rowqty < $quantity):
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            if($deck_legality_list != ''):
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for main deck card '$cardname'",$logfile);
                                $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                if ($index !== false):
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if($card_legal === 'legal' OR $card_legal === NULL):
                                        $illegal_tag = '';
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                        $illegal_cards = TRUE;
                                    endif;
                                else:
                                    $illegal_tag = '';
                                endif;
                            else:
                                $illegal_tag = '';
                            endif;
                            if(in_array($decktype,$commander_decktypes) AND $illegal_tag == ''):
                                $colour_id = count_chars( str_replace(array('"','[',']',',',' '),'',$row['color_identity']),3);
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is $colour_id",$logfile);
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach($colour_id_array as $value):
                                    if(strpos($cdr_colours_raw,$value) == FALSE):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity not OK with Commander(s)",$logfile);
                                        $card_colour_mismatch = TRUE;
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity is OK with Commander(s)",$logfile);
                                    endif;
                                endforeach;
                                if($card_colour_mismatch == '' OR $colour_id == ''):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is OK with Commander(s)",$logfile);
                                    $wrong_colour_tag = '';
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity not OK with Commander(s)",$logfile);
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = TRUE;
                                endif;
                            endif;
                            $cardcmc = round($row["cmc"]);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5):
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php 
                                $i = 0;
                                $cdr_1_plus = FALSE;
                                while($i < count($commander_multiples)):
                                    if(isset($row['type']) AND str_contains($row['type'],$commander_multiples[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while($i < count($any_quantity)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$any_quantity[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                if(in_array($decktype,$commander_decktypes) AND $cdr_1_plus == TRUE):
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$quantity $cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                else:
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                endif;
                                ?>
                            <script type="text/javascript">
                                $('#<?php echo $cardref;?>-taphover').on('click',function(e) {
                                    'use strict'; //satisfy code inspectors
                                    var link = $(this); //preselect the link
                                    $('.deckcardimgdiv').hide("slow");
                                    e.preventDefault();
                                    $("<?php echo "#card-$cardref";?>").show("slow");
                                    return false; //extra, and to make sure the function has consistent return points
                                });
                            </script>
                            <?php
                            echo "</td>";
                            if(in_array($decktype,$commander_decktypes)):
                                $validcommander = FALSE;
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, checking if $cardname is valid as a commander",$logfile);
                                $i = 0;
                                while($i < count($valid_commander_text)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$valid_commander_text[$i]) == TRUE):
                                        $validcommander = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                $secondcommander = FALSE;
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, checking if $cardname is valid as a 2nd commander",$logfile);
                                $i = 0;
                                while($i < count($second_commander_text)):
                                    if(isset($row['ability']) AND str_contains($row['ability'],$second_commander_text[$i]) == TRUE):
                                        $secondcommander = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                $secondcommanderonly = FALSE;
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, checking if $cardname is valid as a 2nd commander only",$logfile);
                                $i = 0;
                                while($i < count($second_commander_only_type)):
                                    if(isset($row['type']) AND str_contains($row['type'],$second_commander_only_type[$i]) == TRUE):
                                        $secondcommanderonly = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                echo "<td class='deckcardlistcenter noprint'>";
                                if($validcommander == TRUE):
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Move to Commander"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;commander=yes'" 
                                        class='material-symbols-outlined'>
                                        person
                                    </span>
                                    <?php
                                elseif($secondcommanderonly == TRUE):
                                    ?>
                                    <span 
                                        onmouseover="" 
                                        title="Move to Background"
                                        style="cursor: pointer;" 
                                        onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;partner=yes'" 
                                        class='material-symbols-outlined'>
                                        north_west
                                    </span>
                                    <?php
                                endif;
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span 
                                onmouseover="" 
                                title="Delete"
                                style="cursor: pointer;" 
                                onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deletemain=yes'" 
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if($decktype != 'Wishlist'):
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Move to sideboard"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;maintoside=yes'" 
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if(!in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Remove one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;minusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter'>";
                                echo $quantity;
                                echo "</td>";
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Add one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;plusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity; 
                            $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                        endif;
                    endwhile; 
                endif;
                ?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commander_decktypes)): ?>    
                        <td colspan='4'> <?php
                    elseif($decktype == 'Wishlist'): ?>
                        <td colspan='5'> <?php
                    else: ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>Lands (<?php echo $lands; ?>)</b></i>
                    </td>    
                </tr>
                <?php 
                $textfile = $textfile."\r\n\r\nLands\r\n\r\n";
                if (mysqli_num_rows($result) > 0):
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;
                        // Check if it's a land, unless it's a Land Creature (Dryad Arbor)
                        if (strpos($row['type'],' //') !== false):
                            $len = strpos($row['type'], ' //');
                            $row['type'] = substr($row['type'], 0, $len);
                        endif;
                        if ((strpos($row['type'],'Land') !== false) AND (strpos($row['type'],'Land Creature') === false)):
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            while ($rowqty < $quantity):
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"]; 
                            if($deck_legality_list != ''):
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for main deck card '$cardname'",$logfile);
                                $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                if ($index !== false):
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if($card_legal === 'legal' OR $card_legal === NULL):
                                        $illegal_tag = '';
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                        $illegal_cards = TRUE;
                                    endif;
                                else:
                                    $illegal_tag = '';
                                endif;
                            else:
                                $illegal_tag = '';
                            endif;
                            if(in_array($decktype,$commander_decktypes) AND $illegal_tag == ''):
                                $colour_id = count_chars( str_replace(array('"','[',']',',',' '),'',$row['color_identity']),3);
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is $colour_id",$logfile);
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach($colour_id_array as $value):
                                    if(strpos($cdr_colours_raw,$value) == FALSE):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity not OK with Commander(s)",$logfile);
                                        $card_colour_mismatch = TRUE;
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity is OK with Commander(s)",$logfile);
                                    endif;
                                endforeach;
                                if($card_colour_mismatch == '' OR $colour_id == ''):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is OK with Commander(s)",$logfile);
                                    $wrong_colour_tag = '';
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity not OK with Commander(s)",$logfile);
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = TRUE;
                                endif;
                            endif; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php 
                                $i = 0;
                                $cdr_1_plus = FALSE;
                                while($i < count($commander_multiples)):
                                    if(isset($row['type']) AND str_contains($row['type'],$commander_multiples[$i]) == TRUE):
                                        $cdr_1_plus = TRUE;
                                    endif;
                                    $i++;
                                endwhile;
                                if(in_array($decktype,$commander_decktypes) AND $cdr_1_plus == TRUE):
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$quantity $cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                else:
                                    echo "<a class='taphover' $illegal_tag id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                endif;
                                ?>
                            <script type="text/javascript">
                                $('#<?php echo $cardref;?>-taphover').on('click',function(e) {
                                    'use strict'; //satisfy code inspectors
                                    var link = $(this); //preselect the link
                                    $('.deckcardimgdiv').hide("slow");
                                    e.preventDefault();
                                    $("<?php echo "#card-$cardref";?>").show("slow");
                                    return false; //extra, and to make sure the function has consistent return points
                                });
                            </script>
                            <?php
                            echo "</td>";
                            if(in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span 
                                onmouseover="" 
                                title="Delete"
                                style="cursor: pointer;" 
                                onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deletemain=yes'" 
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if($decktype != 'Wishlist'):
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Move to sideboard"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;maintoside=yes'" 
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if(!in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Remove one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;minusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter'>";
                                echo $quantity;
                                echo "</td>";
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Add one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;plusmain=yes'" 
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity; 
                            $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                        endif;
                    endwhile; 
                endif;
                if($decktype != 'Wishlist'):?>
                    <tr>
                        <?php 
                        if(in_array($decktype,$commander_decktypes)):
                            ?>    
                            <td colspan="2">&nbsp;
                        <?php
                        else:
                        ?>
                            <td colspan='4'>&nbsp;
                        <?php
                        endif;?>
                        <i><b>Total</b></i>
                        </td>
                        <td colspan="1" class='deckcardlistcenter'>
                            <i><b><?php echo $total; ?></b></i>
                        </td>
                        <td colspan="1">&nbsp;</td>
                    </tr>
                    <tr>
                        <?php 
                        if(in_array($decktype,$commander_decktypes)):
                            ?>    
                            <td colspan="4">&nbsp;
                        <?php
                        else:
                        ?>
                            <td colspan='6'>&nbsp;
                        <?php
                        endif;?>
                        </td>
                    </tr>            
                    <tr>
                        <?php 
                        if(in_array($decktype,$commander_decktypes)):
                            ?>    
                            <td colspan='4'>
                        <?php
                        else:
                        ?>
                            <td colspan='6'>
                        <?php
                        endif;
// SIDEBOARD
                        ?>
                        <i><b>Sideboard</b></i>
                        </td>    
                    </tr>
                    <?php 
                    $textfile = $textfile."\r\n\r\nSideboard\r\n\r\n";
                    $sidetotal = 0;
                    if (mysqli_num_rows($sideresult) > 0):
                        mysqli_data_seek($sideresult, 0);
                        while ($row = $sideresult->fetch_assoc()):
                            if(isset($row['flavor_name']) AND !empty($row['flavor_name'])):
                                $row['name'] = $row['flavor_name'];
                            endif;
                            $illegal_tag = $red_font_tag;
                            $wrong_colour_tag = $firebrick_font_tag;
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $quantity = $row["sideqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            if($deck_legality_list != ''):
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Checking legality for sideboard card '$cardname'",$logfile);
                                $index = array_search("$cardid", array_column($deck_legality_list, 'id'));
                                if ($index !== false):
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if($card_legal === 'legal' OR $card_legal === NULL):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card legality is 'legal' or null",$logfile);
                                        $illegal_tag = '';
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card not legal in this format",$logfile);
                                        $illegal_cards = TRUE;
                                    endif;
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card legality is unknown",$logfile);
                                    $illegal_tag = '';
                                endif;
                            else:
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card legality is not needed",$logfile);
                                $illegal_tag = '';
                            endif;
                            if(in_array($decktype,$commander_decktypes) AND $illegal_tag == ''):
                                $colour_id = count_chars( str_replace(array('"','[',']',',',' '),'',$row['color_identity']),3);
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is $colour_id",$logfile);
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach($colour_id_array as $value):
                                    if(strpos($cdr_colours_raw,$value) == FALSE):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity not OK with Commander(s)",$logfile);
                                        $card_colour_mismatch = TRUE;
                                    else:
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Colour $value in card's colour identity is OK with Commander(s)",$logfile);
                                    endif;
                                endforeach;
                                if($card_colour_mismatch == '' OR $colour_id == ''):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity is OK with Commander(s)",$logfile);
                                    $wrong_colour_tag = '';
                                else:
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Card's colour identity not OK with Commander(s)",$logfile);
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = TRUE;
                                endif;
                            endif;
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid']; ?>
                            <tr class='deckrow'>
                                <td class="deckcardname">
                                    <?php 
                                    $i = 0;
                                    $cdr_1_plus = FALSE;
                                    while($i < count($commander_multiples)):
                                        if(isset($row['type']) AND str_contains($row['type'],$commander_multiples[$i]) == TRUE):
                                            $cdr_1_plus = TRUE;
                                        endif;
                                        $i++;
                                    endwhile;
                                    $i = 0;
                                    while($i < count($any_quantity)):
                                        if(isset($row['ability']) AND str_contains($row['ability'],$any_quantity[$i]) == TRUE):
                                            $cdr_1_plus = TRUE;
                                        endif;
                                        $i++;
                                    endwhile;
                                    if(in_array($decktype,$commander_decktypes) AND $cdr_1_plus == TRUE):
                                        echo "<a class='taphover' $illegal_tag id='side-$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$quantity $cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                    else:
                                        echo "<a class='taphover' $illegal_tag id='side-$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>"; 
                                    endif;
                                    ?>
                                <script type="text/javascript">
                                    $('#side-<?php echo $cardref;?>-taphover').on('click',function(e) {
                                        'use strict'; //satisfy code inspectors
                                        var link = $(this); //preselect the link
                                        $('.deckcardimgdiv').hide("slow");
                                        e.preventDefault();
                                        $("<?php echo "#side-$cardref";?>").show("slow");
                                        return false; //extra, and to make sure the function has consistent return points
                                    });
                                </script>
                                <?php
                                echo "</td>";
                            if(in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span 
                                onmouseover="" 
                                title="Delete"
                                style="cursor: pointer;" 
                                onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;deleteside=yes'" 
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span 
                                onmouseover="" 
                                title="Move to main deck"
                                style="cursor: pointer;" 
                                onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;sidetomain=yes'" 
                                class='material-symbols-outlined'>
                                arrow_upward
                            </span>
                            <?php
                            echo "</td>";
                            if(!in_array($decktype,$commander_decktypes)):
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Remove one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;minusside=yes'" 
                                    class='material-symbols-outlined'>
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter'>";
                                echo $quantity;
                                echo "</td>";
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span 
                                    onmouseover="" 
                                    title="Add one"
                                    style="cursor: pointer;" 
                                    onclick="window.location='deckdetail.php?deck=<?php echo $decknumber;?>&amp;card=<?php echo $cardid?>&amp;plusside=yes'" 
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $sidetotal = $sidetotal + $quantity;
                            $textfile = $textfile."$quantity $cardname [$cardset]"."\r\n";
                            endwhile; 
                    endif;?>
                    <tr>
                        <?php 
                        if(in_array($decktype,$commander_decktypes)):
                            ?>    
                            <td colspan="2">&nbsp;
                        <?php
                        else:
                        ?>
                            <td colspan='4'>&nbsp;
                        <?php
                        endif;?>
                            <i><b>Total sideboard</b></i>
                        </td>    

                        <td colspan="1" class='deckcardlistcenter'>
                            <i><b><?php echo $sidetotal; ?></b></i>
                        </td>
                        <td colspan="1">&nbsp;</td>
                    </tr> <?php
                else:
                    $sidetotal = 0;
                endif; ?>
            </table>
        </div>
        <div id="decknotesdiv">
            <?php
            if((in_array($decktype,$hundredcarddecks) AND $total < 100)):
                $warnings = TRUE;
                $hundred_not_enough = TRUE;
            endif;
            if((in_array($decktype,$sixtycarddecks) AND $total < 60)):
                $warnings = TRUE;
                $sixty_not_enough = TRUE;
            endif;
            if((in_array($decktype,$fiftycarddecks) AND $total < 50)):
                $warnings = TRUE;
                $fifty_not_enough = TRUE;
            endif;
            if($illegal_cards == TRUE):
                $warnings = TRUE;
            endif;
            if($deck_colour_mismatch == TRUE):
                $warnings = TRUE;
            endif;
            
            if(isset($warnings)):
                echo "<h4>&nbsp;Warnings</h4>";
                echo "<ul style='margin-right: 20px;'>";
                if(isset($secondcommandername)):
                    echo "<li>You have a second commander ('<i>$secondcommandername</i>') - check rules and validity with your primary commander</li>";
                endif;
                if(isset($hundred_not_enough)):
                    echo "<li>Your commander deck doesn't have enough cards for legal play</li>";
                endif;
                if(isset($sixty_not_enough)):
                    echo "<li>Your deck doesn't have enough cards for legal play</li>";
                endif;
                if(isset($fifty_not_enough)):
                    echo "<li>Your deck doesn't have enough cards for legal play</li>";
                endif;
                if(isset($illegal_cards) AND $illegal_cards == TRUE):
                    echo "<li>Your deck contains <span $red_font_tag>cards </span> not legal in this format</li>";
                endif;
                if(isset($deck_colour_mismatch) AND $deck_colour_mismatch == TRUE):
                    echo "<li>Your deck contains <span $firebrick_font_tag>cards </span> not in your Commander(s) colour identity</li>";
                endif;
                echo "</ul>";
            endif;
            ?>
            <form action="?" method="POST">
                <h4>&nbsp;Notes</h4>
                <textarea class='decknotes textinput' name='newnotes' rows='2' cols='40'><?php echo $notes; ?></textarea>
                <?php if ($decktype != 'Wishlist'):  ?>
                    <h4>&nbsp;Sideboard notes</h4>
                    <textarea class='decknotes textinput' name='newsidenotes' rows='2' cols='40'><?php echo $sidenotes; ?></textarea><br>
                <?php endif;  ?>
                <input type='hidden' name='updatenotes' value='yes'>
                <input type='hidden' name='deck' value='<?php echo $decknumber?>'>
                <input class='inline_button stdwidthbutton noprint' type="submit" value="UPDATE NOTES">
            </form>
            <hr id='deckline' class='hr324'>
            <?php
            if($total + $sidetotal > 0):
                ?>
                <h4>&nbsp;CMC</h4>
                <script type="text/javascript">
                  google.charts.load('current', {'packages':['bar']});
                  google.charts.setOnLoadCallback(drawChart);
                  function drawChart() {
                  var data = google.visualization.arrayToDataTable([
                      ['', 'Qty'],
                      ['0', <?php echo $cmc[0]; ?>],
                      ['1', <?php echo $cmc[1]; ?>],
                      ['2', <?php echo $cmc[2]; ?>],
                      ['3', <?php echo $cmc[3]; ?>],
                      ['4', <?php echo $cmc[4]; ?>],
                      ['5', <?php echo $cmc[5]; ?>],
                      ['6+', <?php echo $cmc[6]; ?>],
                    ]);

                    var options = {
                      bars: 'vertical',
                      axisTitlesPosition: 'none',
                      backgroundColor:{
                          fill:'#e8eaf6'
                      },
                      chartArea:{
                          left:0,
                          top:0,
                          backgroundColor:'#e8eaf6'
                      },
                      legend:{
                          position: 'none'
                      },
                      hAxis:{
                          textPosition: 'none'
                      },
                      vAxis:{
                          minValue: '0'
                      }
                    };
                    var chart = new google.charts.Bar(document.getElementById('barchart_material'));
                    chart.draw(data, google.charts.Bar.convertOptions(options));
                  }
                </script>
                <div id="barchart_material" style="width: 85%; height: 150px;"></div>
            <?php 
                if(($total - $lands) != 0):
                    $avgcmc = round(($cmctotal / ($total - $lands)),2);
                    echo "<br>Average CMC = $avgcmc" ;
                else:
                    echo "<br>Average CMC = N/A";
                endif;
                echo "<br>Total deck value (Fair Trade) = $".$deckvalue;
            endif; 
            if(isset($uniquecard_ref) AND count($uniquecard_ref) > 6): ?>
                <h4>Random draw</h4><?php 
                $a = array_rand($uniquecard_ref,7);
                echo "1: ".$uniquecard_ref[$a[0]]['name'].'<br>';
                echo "2: ".$uniquecard_ref[$a[1]]['name'].'<br>';
                echo "3: ".$uniquecard_ref[$a[2]]['name'].'<br>';
                echo "4: ".$uniquecard_ref[$a[3]]['name'].'<br>';
                echo "5: ".$uniquecard_ref[$a[4]]['name'].'<br>';
                echo "6: ".$uniquecard_ref[$a[5]]['name'].'<br>';
                echo "7: ".$uniquecard_ref[$a[6]]['name'];
            endif;
            ?>
        </div>
        <div id='deckfunctions'> 
            <?php
            if($total + $sidetotal > 0): ?>
                <h4>Export decklist</h4>
                <?php
                $textfile = $textfile."\r\n\r\nNotes\r\n\r\n$notes\r\n";
                $textfile = $textfile."\r\n\r\nSideboard notes\r\n\r\n$sidenotes";
                $textfile = htmlspecialchars($textfile,ENT_QUOTES);
                $filename = preg_replace('/[^\w]/', '', $deckname);
                ?>
                <form action="dltext.php" method="POST">
                    <input class='profilebutton' type="submit" value="EXPORT">
                    <?php echo "<input type='hidden' name='text' value='$textfile'>"; ?>
                    <?php echo "<input type='hidden' name='filename' value='$filename'>"; ?>
                </form>
                <?php
                if($missing == 'yes' AND $requiredlist != ''):
                    $requiredlist = htmlspecialchars($requiredlist,ENT_QUOTES);
                    $requiredbuy = htmlspecialchars($requiredbuy,ENT_QUOTES);
                    $filename_missing = preg_replace('/[^\w]/', '', $deckname.'_missing');?>
                    <script type="text/javascript">
                        document.body.style.cursor='default';
                    </script>
                    <h4>Export missing cards list</h4>
                    <form action="dltext.php" method="POST">
                        <input class='profilebutton' type="submit" value="EXPORT">
                        <?php echo "<input type='hidden' name='text' value='$requiredlist'>"; ?>
                        <?php echo "<input type='hidden' name='filename' value='$filename_missing'>"; ?>
                    </form> 
                    <br>
                    TCGPlayer: <a href="https://store.tcgplayer.com/list/selectproductmagic.aspx?partner=MTGCOLLECT&c=<?php echo $requiredbuy; ?>" target='_blank'>BUY</a>
                    <?php
                elseif($missing == 'yes' AND $requiredlist == ''): ?>
                    <h4>All cards in deck are in collection</h4>
                    <br>
                    <?php
                else:?>
                    <h4>Compare to collection for missing cards</h4>
                    This will check against all cards in your collection for a name match, so can take a considerable time for large collections. 
                    When it is complete, you will be returned to this page with two options:
                    <ol>
                        <li>
                            "EXPORT" to download a text file with missing cards
                        </li>
                        <li>
                            "TCGPlayer BUY" to link to TCGPlayer for the missing cards
                        </li>
                    </ol>
                    <form action="deckdetail.php" method="GET">
                    <input type='hidden' name='deck' value='<?php echo $decknumber ?>'>
                    <input type='hidden' name='missing' value='yes'>
                    <input class='profilebutton' type="submit" value="COMPARE" onclick='ComparePrep()'>
                    </form>
                <?php
                endif;
            endif;
            ?>
            <h4>Quick add</h4>
            Format: <i>"qty {optional} name {optional if set and number included} [set {optional unless number included} number {optional}]" </i><br>E.g.: 
            "Madame Vastra", "Madame Vastra [WHO]", "Madame Vastra [WHO 425]", "4 Madame Vastra [WHO]", "2 [WHO 425]"
            <br><br>
            <form action="deckdetail.php"  method="GET">
                <textarea class='textinput' rows="3" cols="47" name="quickadd"></textarea>
                <br>
                <input class='inline_button stdwidthbutton noprint' type="submit" value="ADD">
                <?php echo "<input type='hidden' name='deck' value='$decknumber'>"; ?>
            </form>
            <h4>Import</h4>
            Import text file, formatted per Quick add above. Note, large decks may take several minutes to import and fetch data and images.
            <br>Cards already in the deck will have quantity updated.
            <script type="text/javascript">
                $(document).ready(function(){
                    $("#importsubmit").attr('disabled',true);
                    $("#importfile").change(
                        function(){
                            if ($(this).val()){
                                $("#importsubmit").removeAttr('disabled'); 
                            }
                            else {
                                $("#importsubmit").attr('disabled',true);
                            }
                        });
                });
                $(document).ready(function(){
                    $("#photosubmit").attr('disabled',true);
                    $("#importphoto").change(
                        function(){
                            if ($(this).val()){
                                $("#photosubmit").removeAttr('disabled'); 
                            }
                            else {
                                $("#photosubmit").attr('disabled',true);
                            }
                        });
                });
                function deletePhoto() {
                    // Get the deck number
                    var deckNumber = $('input[name="decknumber"]').val();

                    // Create form data
                    var formData = new FormData();
                    formData.append('decknumber', deckNumber);
                    formData.append('delete', '');

                    // Perform AJAX request
                    $.ajax({
                        url: '/ajax/ajaxphoto.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        timeout: 5000,
                        success: function(response) {
                            if (response.success) {
                                $('#result').html(response.message);
                                $('#photo_div').hide();
                                $('#deletePhotoBtn').hide();
                                setTimeout(function() {
                                    $('#result').html('');
                                }, 5000);
                            } else {
                                $('#result').html('Error: ' + response.message);
                                setTimeout(function() {
                                    $('#result').html('');
                                }, 20000);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            $('#result').html('Error: ' + textStatus + ' - ' + errorThrown);
                            setTimeout(function() {
                                $('#result').html('');
                            }, 20000);
                        }
                    });
                };
                $(document).ready(function() {
                    $('#uploadForm').submit(function(e) {
                        e.preventDefault(); // Prevent the default form submission

                        // Get the deck number from the hidden input
                        var deckNumber = $('input[name="decknumber"]').val();

                        // Append the deck number to the form data
                        var formData = new FormData(this);
                        formData.append('decknumber', deckNumber);
                        formData.append('update', '');

                        $.ajax({
                            url: '/ajax/ajaxphoto.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json', // Expect JSON response
                            success: function(response) {
                                if (response.success) {
                                    $('#result').html(response.message);
                                    // Reload the image
                                    // var imageUrl = 'cardimg/deck_photos/<?php echo $decknumber; ?>.jpg';
                                    var imageUrl = 'deckimage.php?deck=<?php echo $decknumber; ?>';
                                    var timestamp = new Date().getTime();
                                    $('#deckPhoto').attr('src', imageUrl + '&' + timestamp);
                                    $('#photo_div').show();
                                    $('#deletePhotoBtn').show();
                                    $("#photosubmit").attr('disabled',true);
                                    setTimeout(function() {
                                        $('#result').html('');
                                    }, 5000);
                                } else {
                                    $('#result').html('Error: ' + response.message);
                                    setTimeout(function() {
                                        $('#result').html('');
                                    }, 20000);
                                }
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                $('#result').html('Error: ' + textStatus + ' - ' + errorThrown);
                                setTimeout(function() {
                                    $('#result').html('');
                                }, 20000);
                            }
                        });
                    });
                });
            </script>
            <script type="text/javascript"> 
                function ImportPrep()
                    {
                        document.body.style.cursor='wait';
                    }
            </script> 
            <br><br>
            <form enctype='multipart/form-data' action='?' method='post'>
                <label class='importlabel'>
                    <input id='importfile' type='file' name='filename'>
                    <span>SELECT</span>
                </label>
                <input class='profilebutton' id='importsubmit' type='submit' name='import' value='IMPORT' disabled onclick='ImportPrep()';>
                <input type='hidden' id='deck' name='deck' value="<?php echo $decknumber; ?>">
            </form> 
            <div id='photo_upload' style="padding-bottom:20px;">
                <h4>Photo</h4>
                You can upload a photo here to store with the deck. Large photos will be resized.<br><br>
                <?php
                $imageFilePath = $ImgLocation.'deck_photos/'.$decknumber.'.jpg';
                $existingImage = 'cardimg/deck_photos/'.$decknumber.'.jpg';
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Imagefilepath $imageFilePath, existingImage $existingImage",$logfile); ?>
                <form id="uploadForm">
                    <input type="hidden" name="decknumber" value="<?php echo $decknumber; ?>">
                    <label class='importlabel'>
                        <input id='importphoto' type='file' name='photo' accept='image/jpeg'>
                        <span>SELECT</span>
                    </label>
                    <input class='profilebutton' id='photosubmit' type='submit' value="UPLOAD">
                    <button class="profilebutton" id="deletePhotoBtn" type="button" onclick="deletePhoto()" <?php echo !file_exists($imageFilePath) ? 'style="display:none;"' : ''; ?> >DELETE</button>
                </form>
                <?php
                if (file_exists($imageFilePath)):?>
                    <div id='photo_div'>
                        <br>
                        <!-- <img id="deckPhoto" src="<?php // $time = time(); echo $existingImage.'?'.$time; ?>" style="max-width: 300px;" alt="Existing Photo"> -->
                        <img id="deckPhoto" src="deckimage.php?deck=<?php echo $decknumber; ?>" style="max-width: 300px;" alt="Existing Photo">
                    </div><?php
                else: ?>
                    <div id='photo_div' style="display: none;">
                        <br>
                        <img id="deckPhoto" src="" style="max-width: 300px;" alt="Existing Photo">
                    </div> <?php
                endif; ?>
                <div id="result"></div>
            </div>
        </div>
    </div>
</div>

<?php 
$obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Page complete",$logfile);
require('includes/footer.php'); ?>        
</body>
</html>
