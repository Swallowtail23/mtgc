<?php
/* Version:     15.0
    Date:       03/06/23
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
*/

session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password
?> 

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title> MtG collection </title>
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
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
    <script type="text/javascript"> 
    function CloseMe( obj )
        {
            obj.style.display = 'none';
        }
    </script>  
</head>

<body class="body">
<?php
include_once("includes/analyticstracking.php");
require('includes/overlays.php');
require('includes/header.php'); 
require('includes/menu.php'); //mobile menu

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
else: ?>
    <div id='page'>
    <div class='staticpagecontent'>
    <h3>No decknumber given - returning to your deck list...</h3>
    <meta http-equiv='refresh' content='2;url=decks.php'>
    </div>
    </div> <?php
    require('includes/footer.php');
    exit();
endif;
$cardtoaction   = isset($_GET['card']) ? filter_input(INPUT_GET, 'card', FILTER_SANITIZE_SPECIAL_CHARS):'';
$deletemain     = isset($_GET['deletemain']) ? 'yes' : '';
$deleteside     = isset($_GET['deleteside']) ? 'yes' : '';
$maintoside     = isset($_GET['maintoside']) ? 'yes' : '';
$sidetomain     = isset($_GET['sidetomain']) ? 'yes' : '';
$plusmain       = isset($_GET['plusmain']) ? 'yes' : '';
$minusmain      = isset($_GET['minusmain']) ? 'yes' : '';
$plusside       = isset($_GET['plusside']) ? 'yes' : '';
$minusside      = isset($_GET['minusside']) ? 'yes' : '';
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
$token_layouts = ['double_faced_token','token','emblem']; // cannot be included
$validtypes = array('Commander','Normal','Tiny Leader');
$commandertypes = array('Commander','Tiny Leader');

// Check to see if the called deck belongs to the logged in user.
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
    $updateddata = array(
        'notes' => "$newnotes",
        'sidenotes' => "$newsidenotes"
    );
    if ($db->update('decks', $updateddata, "WHERE decknumber = $decknumber") === FALSE):
        trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
    endif;
endif;

//Update deck type if called before reading info
if (isset($updatetype)):
    if(in_array($updatetype,$validtypes)):
        $updatetypedata = array(
            'type' => "$updatetype"
        );
        if ($db->update('decks', $updatetypedata, "WHERE decknumber = $decknumber") === FALSE):
            trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
        else:
            if(!in_array($updatetype,$commandertypes)):
                $removecommander = array(
                    'commander' => '0'
                );
                if ($db->update('deckcards', $removecommander, "WHERE decknumber = $decknumber") === FALSE):
                    trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
                endif;
            endif;
        endif;
    else:
        trigger_error('[ERROR] deckdetail.php: Error: Invalid deck type', E_USER_ERROR);
    endif;
    if(in_array($updatetype,$commandertypes)):
        $query = 'UPDATE deckcards SET cardqty=? WHERE decknumber = ?';
        if ($db->execute_query($query, [1,$decknumber]) != TRUE):
            trigger_error("[ERROR] Class " .__METHOD__ . " ".__LINE__," - SQL failure: Error: " . $db->error, E_USER_ERROR);
        else:
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
        endif;
    endif;    
endif;

//Carry out quick add requests
if (isset($_GET["quickadd"])):
    $cardtoadd = quickadd($decknumber,$_GET["quickadd"]);
endif;

// Get deck details from database
if($deckinfo = $db->select_one('deckname,notes,sidenotes,type','decks',"WHERE decknumber = $decknumber")):
    $deckname   = $deckinfo['deckname'];
    $notes      = $deckinfo['notes'];
    $sidenotes  = $deckinfo['sidenotes'];
    $decktype   = $deckinfo['type'];
else:
    trigger_error('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ".$db->error, E_USER_ERROR);
endif;

// Add / delete, before calling the deck list
if($deletemain == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,"main","all");
elseif($deleteside == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,"side","all");
elseif($maintoside == 'yes'):
    if (subtractdeckcard($decknumber,$cardtoaction,'main','1') != "-error"):
        adddeckcard($decknumber,$cardtoaction,"side","1");
    endif;
elseif($sidetomain == 'yes'):
    if (subtractdeckcard($decknumber,$cardtoaction,'side','1') != "-error"):
        adddeckcard($decknumber,$cardtoaction,"main","1");
    endif;
elseif($plusmain == 'yes'):
    adddeckcard($decknumber,$cardtoaction,"main","1");
elseif($minusmain == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,'main','1');
elseif($plusside == 'yes'):
    adddeckcard($decknumber,$cardtoaction,"side","1");
elseif($minusside == 'yes'):
    subtractdeckcard($decknumber,$cardtoaction,'side','1');
elseif($commander == 'yes'):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Adding Commander to deck $decknumber: $cardtoaction",$logfile);
    addcommander($decknumber,$cardtoaction);
elseif($partner == 'yes'):
    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Moving Commander to Partner for deck $decknumber: $cardtoaction",$logfile);
    addpartner($decknumber,$cardtoaction);
elseif($commander == 'no'):
    delcommander($decknumber,$cardtoaction);
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
else:
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
endif;

$sidequery = ("SELECT *,cards_scry.id AS cardsid 
                        FROM deckcards 
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id 
                    WHERE decknumber = ? AND sideqty > 0 ORDER BY name");
$sideresult = $db->execute_query($sidequery, [$decknumber]);
if ($sideresult != TRUE):
    trigger_error("[ERROR] Line ".__LINE__." - SQL failure: Error: " . $db->error, E_USER_ERROR);
else:
    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Function ".__FUNCTION__.": ...sql result: {$db->info}",$logfile);
endif;

//Initialise variables to 0
$cdr = $creatures = $instantsorcery = $other = $lands = $deckvalue = 0;

//This section works out which cards the user DOES NOT have, for later linking
// in a text file to download
$resultnames = array();
while ($row = $result->fetch_assoc()):
    if(!in_array($row['name'], $resultnames)):
        $resultnames[] = $row['name'];
    endif;
endwhile;
while ($row = $sideresult->fetch_assoc()):
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
        $key = array_search($row['name'], $resultnames);
        $resultqty[$key] = $resultqty[$key] + $qty;
    endwhile;
    while ($row = $sideresult->fetch_assoc()):
        $qty = $row['cardqty'] + $row['sideqty'];
        $key = array_search($row['name'], $resultnames);
        $resultqty[$key] = $resultqty[$key] + $qty;
    endwhile;

    if($missing == 'yes'):
        $shortqty = array_fill(0,$uniquecardscount,'0'); //create an array the right size, all '0'
        foreach($resultnames as $key=>$value):
            $searchname = $db->escape($value);
            $query = "SELECT SUM(IFNULL(`$mytable`.foil, 0)) + SUM(IFNULL(`$mytable`.normal, 0)) as allcopies from cards_scry LEFT JOIN $mytable ON cards_scry.id = $mytable.id WHERE name = '$searchname'";
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
mysqli_data_seek($result, 0);
while ($row = $result->fetch_assoc()):
    $cardset = strtolower($row['setcode']);
    if ((strpos($row['type'],'Creature') !== false) AND ($row['commander'] == 0)):
        $creatures = $creatures + $row['cardqty'];
    elseif ((strpos($row['type'],'Sorcery') !== false) OR (strpos($row['type'],'Instant') !== false)):  
        $instantsorcery = $instantsorcery + $row['cardqty'];
    elseif ((strpos($row['type'],'Sorcery') === false) AND (strpos($row['type'],'Instant') === false) AND (strpos($row['type'],'Creature') === false) AND (strpos($row['type'],'Land') === false) AND ($row['commander'] == 0)):
        $other = $other + $row['cardqty'];
    elseif (strpos($row['type'],'Land') !== false):
        $lands = $lands + $row['cardqty'];
    endif;
    $imagefunction = getImageNew($cardset,$row['cardsid'],$ImgLocation,$row['layout'],$two_card_detail_sections);
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
mysqli_data_seek($sideresult, 0);
while ($row = $sideresult->fetch_assoc()):
    $cardset = strtolower($row["setcode"]);
    $imagefunction = getImageNew($cardset,$row['cardsid'],$ImgLocation,$row['layout'],$two_card_detail_sections);
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
if(isset($cardtoadd) AND $cardtoadd == 'cardnotfound'): ?>
    <div class="msg-new error-new" onclick='CloseMe(this)'><span>Quick add failed</span>
        <br>
        <br>
        Check card name
        <br>
        <br>
        <span id='dismiss'>CLICK TO DISMISS</span>
    </div>
<?php
endif;
?>
<div id="page">
    <div class="staticpagecontent">
        <div id="decklist">
            <span id="printtitle" class="headername">
                <img src="images/white_m.png"> MtG collection
            </span>
            <?php
            echo "<h2 class='h2pad'>$deckname</h2>";
                echo "Deck type:"; ?>
                <form>
                    <select class='dropdown' size="1" name="updatetype" onchange='this.form.submit()'>
                        <option <?php if($decktype==''):echo "selected='selected'";endif;?>disabled='disabled'>Pick one</option>
                        <option <?php if($decktype=='Commander'):echo "selected='selected'";endif;?>>Commander</option>
                        <option <?php if($decktype=='Tiny Leader'):echo "selected='selected'";endif ;?>>Tiny Leader</option>
                        <option <?php if($decktype=='Normal'):echo "selected='selected'";endif ;?>>Normal</option>
                    </select>    
                    <input type="hidden"name="deck" value="<?php echo $decknumber;?>" />
                </form>
            <table class='deckcardlist'>
                <tr class='deckcardlisthead'> 
                    <td class='deckcardlisthead1'>
                        <span class="noprint">Card</span>
                    </td>
                    <?php 
                    if(in_array($decktype,$commandertypes)):
                        ?>    
                        <td class="deckcardlisthead3">
                            <span class="noprint">Cdr</span>
                        </td>
                        <?php
                    endif;
                    ?>
                    <td class="deckcardlisthead3">
                        <span class="noprint">Del</span>
                    </td>
                    <td class='deckcardlisthead3'>
                        <span class="noprint">Side</span>
                    </td>
                    <?php 
                    if(!in_array($decktype,$commandertypes)): ?>    
                        <td class='deckcardlisthead3 deckcardlistright'>
                            <span class="noprint">- &nbsp;</span>
                        </td>
                        <td class='deckcardlisthead3'>
                            <span class="noprint">Qty</span>
                        </td>
                        <td class='deckcardlisthead3 deckcardlistleft'>
                            <span class="noprint">&nbsp;+</span>
                        </td>
                        <?php
                    endif; ?>
                </tr> 
                <?php 
                // Only show this row if the decktype is Commander style
                if(in_array($decktype,$commandertypes)): 
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
                            if ($row['commander'] == 1):
                                $cardname = $row["name"];
                                $quantity = $row["cardqty"];
                                $cardset = strtolower($row["setcode"]);
                                $cardref = str_replace('.','-',$row['cardsid']);
                                $cardid = $row['cardsid'];
                                $cardnumber = $row["number"];
                                $cardcmc = round($row["cmc"]);
                                $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                if ($cardcmc > 5):
                                    $cardcmc = 6;
                                endif;
                                $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                                <tr class='deckrow'>
                                <td class="deckcardname">
                                    <?php echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; ?>
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
                                if(!in_array($decktype,$commandertypes)):
                                    echo "<td class='deckcardlistcenter'>";
                                    echo $quantity;
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                                $total = $total + $quantity;
                                $commandercount = $commandercount +1;
                                $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                            endif;
                        endwhile; 
                    endif; 
                    if(in_array($decktype,$commandertypes)):
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
                                if ($row['commander'] == 2):
                                    $cardname = $row["name"];
                                    $quantity = $row["cardqty"];
                                    $cardset = strtolower($row["setcode"]);
                                    $cardref = str_replace('.','-',$row['cardsid']);
                                    $cardid = $row['cardsid'];
                                    $cardnumber = $row["number"];
                                    $cardcmc = round($row["cmc"]);
                                    $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                    if ($cardcmc > 5):
                                        $cardcmc = 6;
                                    endif;
                                    $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                                    <tr class='deckrow'>
                                    <td class="deckcardname">
                                        <?php echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; ?>
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
                                    if(!in_array($decktype,$commandertypes)):
                                        echo "<td class='deckcardlistcenter'>";
                                        echo $quantity;
                                        echo "</td>";
                                    endif;
                                    echo "</tr>";
                                    $total = $total + $quantity;
                                    $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
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
                        <td colspan='6'>
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
                if (mysqli_num_rows($result) > 0):
                mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if ((strpos($row['type'],'Creature') !== false) AND ($row['commander'] < 1)):
                            $cardname = $row["name"];
                            $quantity = $row["cardqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            $cardcmc = round($row["cmc"]);
                            $cardlegendary = $row["type"];
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5):
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; ?>
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
                            if(in_array($decktype,$commandertypes)):
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
                            if(!in_array($decktype,$commandertypes)):
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
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif; ?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commandertypes)):
                        ?>    
                        <td colspan='4'>
                    <?php
                    else:
                    ?>
                        <td colspan='6'>
                    <?php
                    endif;
                    ?>
                    <i><b>Instants and Sorceries (<?php echo $instantsorcery; ?>)</b></i>
                    </td>    
                </tr>
                <?php 
                $textfile = $textfile."\r\n\r\nInstants and Sorceries\r\n\r\n";
                if (mysqli_num_rows($result) > 0):
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if ((strpos($row['type'],'Sorcery') !== false) OR (strpos($row['type'],'Instant') !== false)):
                            $cardname = $row["name"];
                            $quantity = $row["cardqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            $cardcmc = round($row["cmc"]);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5):
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; ?>
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
                            if(in_array($decktype,$commandertypes)):
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
                            if(!in_array($decktype,$commandertypes)):
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
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif; ?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commandertypes)):
                        ?>    
                        <td colspan='4'>
                    <?php
                    else:
                    ?>
                        <td colspan='6'>
                    <?php
                    endif;
                    ?>
                    <i><b>Other (<?php echo $other; ?>)</b></i>
                    </td>    
                </tr>
                <?php 
                $textfile = $textfile."\r\n\r\nOther\r\n\r\n";
                if (mysqli_num_rows($result) > 0):
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        if ((strpos($row['type'],'Sorcery') === false) AND (strpos($row['type'],'Instant') === false) AND (strpos($row['type'],'Creature') === false) AND (strpos($row['type'],'Land') === false) AND ($row['commander'] != 1)):
                            $cardname = $row["name"];
                            $quantity = $row["cardqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"];
                            $cardcmc = round($row["cmc"]);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5):
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; ?>
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
                            if(in_array($decktype,$commandertypes)):
                                $validcommander = FALSE;
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"This is a '$decktype' deck, checking if $cardname is a valid commander",$logfile);
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
                            if(!in_array($decktype,$commandertypes)):
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
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif;
                ?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commandertypes)):
                        ?>    
                        <td colspan='4'>
                    <?php
                    else:
                    ?>
                        <td colspan='6'>
                    <?php
                    endif;
                    ?>
                    <i><b>Lands (<?php echo $lands; ?>)</b></i>
                    </td>    
                </tr>
                <?php 
                $textfile = $textfile."\r\n\r\nLands\r\n\r\n";
                if (mysqli_num_rows($result) > 0):
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()):
                        // Check if it's a land, unless it's a Land Creature (Dryad Arbor)
                        if ((strpos($row['type'],'Land') !== false) AND (strpos($row['type'],'Land Creature') === false)):
                            $cardname = $row["name"];
                            $quantity = $row["cardqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.','-',$row['cardsid']);
                            $cardid = $row['cardsid'];
                            $cardnumber = $row["number"]; ?>
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
                                if(in_array($decktype,$commandertypes) AND $cdr_1_plus == TRUE):
                                    echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$quantity x $cardname ($cardset)</a>"; 
                                else:
                                    echo "<a class='taphover' id='$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; 
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
                            if(in_array($decktype,$commandertypes)):
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
                            if(!in_array($decktype,$commandertypes)):
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
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif;?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commandertypes)):
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
                    if(in_array($decktype,$commandertypes)):
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
                    if(in_array($decktype,$commandertypes)):
                        ?>    
                        <td colspan='4'>
                    <?php
                    else:
                    ?>
                        <td colspan='6'>
                    <?php
                    endif;
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
                        $cardname = $row["name"];
                        $quantity = $row["sideqty"];
                        $cardset = strtolower($row["setcode"]);
                        $cardnumber = $row["number"];
                        $cardref = str_replace('.','-',$row['cardsid']);
                        $cardid = $row['cardsid']; ?>
                        <tr class='deckrow'>
                            <td class="deckcardname">
                                <?php echo "<a class='taphover' id='side-$cardref-taphover' href='carddetail.php?setabbrv={$row['setcode']}&amp;number={$row['number']}&amp;id={$row['cardsid']}' target='_blank'>$cardname ($cardset)</a>"; ?>
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
                        if(in_array($decktype,$commandertypes)):
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
                        if(!in_array($decktype,$commandertypes)):
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
                        $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endwhile; 
                endif;?>
                <tr>
                    <?php 
                    if(in_array($decktype,$commandertypes)):
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
                </tr>
            </table>
        </div>
        <div id="decknotesdiv">
            <form action="?" method="POST">
                <h4>&nbsp;Notes</h4>
                <textarea class='decknotes textinput' name='newnotes' rows='2' cols='40'><?php echo $notes; ?></textarea>
                <h4>&nbsp;Sideboard notes</h4>
                <textarea class='decknotes textinput' name='newsidenotes' rows='2' cols='40'><?php echo $sidenotes; ?></textarea><br>
                <input type='hidden' name='updatenotes' value='yes'>
                <input type='hidden' name='deck' value='<?php echo $decknumber?>'>
                <input class='inline_button stdwidthbutton noprint' type="submit" value="UPDATE NOTES">
            </form>
            <hr id='deckline' class='hr324'>
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
                <?php
                if($total + $sidetotal > 0):
                    ?>
                    <div id="barchart_material" style="width: 85%; height: 150px;"></div>
                <?php 
                else:
                    echo 'N/A<br>';
                endif;
            if(($total - $lands) != 0):
                $avgcmc = round(($cmctotal / ($total - $lands)),2);
                echo "<br>Average CMC = $avgcmc" ;
            else:
                echo "<br>Average CMC = N/A";
            endif;
            echo "<br>Total deck value (Fair Trade) = $".$deckvalue;?>
            </div>
            <div id='deckfunctions'>
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
                <form action="deckdetail.php" method="GET">
                <input type='hidden' name='deck' value='<?php echo $decknumber ?>'>
                <input type='hidden' name='missing' value='yes'>
                <input class='profilebutton' type="submit" value="COMPARE">
                </form>
                <br>
            <?php
            endif;
            ?>
            <h4>Quick add</h4>
                Format: {qty[optional]}{cardname}{(set)[optional]}<br>
                E.g. 1 Murder; Pacifism (m11)
            <form action="deckdetail.php"  method="GET">
                <input class='decknotes textinput' type='text' name='quickadd' size='30'>
                <input class='inline_button stdwidthbutton noprint' type="submit" value="ADD">
                <?php echo "<input type='hidden' name='deck' value='$decknumber'>"; ?>
            </form>
            <br>
        </div>
    </div>
</div>

<?php require('includes/footer.php'); ?>        
</body>
</html>
