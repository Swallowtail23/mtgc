<?php
/* Version:     11.0
    Date:       02/02/22
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

if (isset($_GET["deck"])):
    $decknumber     = filter_input(INPUT_GET, 'deck', FILTER_SANITIZE_STRING);
    if (isset($_GET["updatetype"])):
        $updatetype = $db->escape(filter_input(INPUT_GET, 'updatetype', FILTER_SANITIZE_STRING));
    endif;
elseif (isset($_POST["deck"])):
    $decknumber     = filter_input(INPUT_POST, 'deck', FILTER_SANITIZE_STRING);
    $updatenotes    = filter_input(INPUT_POST, 'updatenotes', FILTER_SANITIZE_STRING);
    $newnotes       = $db->escape(filter_input(INPUT_POST, 'newnotes', FILTER_SANITIZE_STRING));
    $newsidenotes   = $db->escape(filter_input(INPUT_POST, 'newsidenotes', FILTER_SANITIZE_STRING));
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
$cardtoaction   = isset($_GET['card']) ? filter_input(INPUT_GET, 'card', FILTER_SANITIZE_STRING):'';
$deletemain   = isset($_GET['deletemain']) ? filter_input(INPUT_GET, 'deletemain', FILTER_SANITIZE_STRING):'';
$deleteside   = isset($_GET['deleteside']) ? filter_input(INPUT_GET, 'deleteside', FILTER_SANITIZE_STRING):'';
$maintoside   = isset($_GET['maintoside']) ? filter_input(INPUT_GET, 'maintoside', FILTER_SANITIZE_STRING):'';
$sidetomain   = isset($_GET['sidetomain']) ? filter_input(INPUT_GET, 'sidetomain', FILTER_SANITIZE_STRING):'';
$plusmain   = isset($_GET['plusmain']) ? filter_input(INPUT_GET, 'plusmain', FILTER_SANITIZE_STRING):'';
$minusmain   = isset($_GET['minusmain']) ? filter_input(INPUT_GET, 'minusmain', FILTER_SANITIZE_STRING):'';
$plusside   = isset($_GET['plusside']) ? filter_input(INPUT_GET, 'plusside', FILTER_SANITIZE_STRING):'';
$minusside   = isset($_GET['minusside']) ? filter_input(INPUT_GET, 'minusside', FILTER_SANITIZE_STRING):'';
$commander   = isset($_GET['commander']) ? filter_input(INPUT_GET, 'commander', FILTER_SANITIZE_STRING):'';

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
    $validtypes = array('Commander','Normal','Tiny Leader');
    $commandertypes = array('Commander','Tiny Leader');
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
endif;

//Carry out quick add requests
if (isset($_GET["quickadd"])):
    $quickaddstring = filter_input(INPUT_GET, 'quickadd', FILTER_SANITIZE_STRING);
    
    //Quantity
    preg_match("~^(\d+)~", $quickaddstring,$qty);
    if (isset($qty[0])):
        $quickaddstring = ltrim(ltrim($quickaddstring,$qty[0]));
        $quickaddqty = $qty[0];
    else:
        $quickaddqty = 1;
    endif;
    
    //Set
    preg_match('#\((.*?)\)#', $quickaddstring, $settomatch);
    if (isset($settomatch[0])):
        $quickaddstring = rtrim(rtrim($quickaddstring,$settomatch[0]));
        $quickaddset = rtrim(ltrim(strtoupper($settomatch[0]),"("),")");
    else:
        $quickaddset = '';
    endif;
    //Card
    $quickaddcard = htmlspecialchars_decode($quickaddstring,ENT_QUOTES);
    $obj = new Message;
    $obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Quick add called with string '$quickaddstring', interpreted as: [$quickaddqty] x [$quickaddcard] [$quickaddset]",$logfile);
    $quickaddcard = $db->escape($quickaddcard);
    if($quickaddset == ''):
        if ($quickaddcardid = $db->query("SELECT id,setcode from cards_scry
                                     WHERE name = '$quickaddcard' ORDER BY release_date DESC LIMIT 1")):
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
        if ($quickaddcardid = $db->query("SELECT id,setcode from cards_scry
                                     WHERE name = '$quickaddcard' AND setcode = '$quickaddset' LIMIT 1")):
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
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Quick add - Card not found",$logfile);
    else:
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Quick add result: $cardtoadd",$logfile);
        adddeckcard($decknumber,$cardtoadd,"main","$quickaddqty");
    endif;
endif;

// Get deck details from database
if($deckinfo = $db->select_one('deckname,notes,sidenotes,type','decks',"WHERE decknumber = $decknumber")):
    $deckname   = $deckinfo['deckname'];
    $notes      = $deckinfo['notes'];
    $sidenotes  = $deckinfo['sidenotes'];
    $decktype   = $deckinfo['type'];
else:
    trigger_error('[ERROR] deckdetail.php: Error: '.$db->error, E_USER_ERROR);
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
    addcommander($decknumber,$cardtoaction);
elseif($commander == 'no'):
    delcommander($decknumber,$cardtoaction);
endif;

//Get card list
$result = $db->query("SELECT *,cards_scry.id AS cardsid 
                        FROM deckcards 
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id 
                    WHERE decknumber = $decknumber AND cardqty > 0 ORDER BY name");
$sideresult = $db->query("SELECT *,cards_scry.id AS cardsid 
                        FROM deckcards 
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id 
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id 
                    WHERE decknumber = $decknumber AND sideqty > 0 ORDER BY name");
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

    $shortqty = array_fill(0,$uniquecardscount,'0');
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
                $requiredbuy = $requiredbuy.$shortqty[$key]." ".$value." || ";
            endif;
        else:
            //
        endif;
    endforeach;
endif;

//This section builds hidden divs for each card with the image and a link,
// and increments type and value counters
// for main and side
mysqli_data_seek($result, 0);
while ($row = $result->fetch_assoc()):
    $cardset = strtolower($row["setcode"]);
    if ((strpos($row['type'],'Creature') !== false) AND ($row['commander'] == 0)):
        $creatures = $creatures + $row['cardqty'];
    elseif ((strpos($row['type'],'Sorcery') !== false) OR (strpos($row['type'],'Instant') !== false)):  
        $instantsorcery = $instantsorcery + $row['cardqty'];
    elseif ((strpos($row['type'],'Sorcery') === false) AND (strpos($row['type'],'Instant') === false) AND (strpos($row['type'],'Creature') === false) AND (strpos($row['type'],'Land') === false)):
        $other = $other + $row['cardqty'];
    elseif (strpos($row['type'],'Land') !== false):
        $lands = $lands + $row['cardqty'];
    endif;
    $imagefunction = getImageNew($cardset,$row['cardsid'],$ImgLocation,$row['layout']);
    if($imagefunction['front'] == 'error'):
        $imageurl = '/cardimg/back.jpg';
    else:
        $imageurl = $imagefunction['front'];
    endif;
    $deckcardname = str_replace("'",'&#39;',$row["name"]); 
    $deckvalue = $deckvalue + ($row['price'] * $row['cardqty']);
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
    $imagefunction = getImageNew($cardset,$row['cardsid'],$ImgLocation,$row['layout']);
    if($imagefunction['front'] == 'error'):
        $imageurl = '/cardimg/back.jpg';
    else:
        $imageurl = $imagefunction['front'];
    endif;
    $deckvalue = $deckvalue + ($row['price'] * $row['sideqty']);
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
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
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
                    <td class='deckcardlisthead3 deckcardlistright'>
                        <span class="noprint">- &nbsp;</span>
                    </td>
                    <td class='deckcardlisthead3'>
                        <span class="noprint">Qty</span>
                    </td>
                    <td class='deckcardlisthead3 deckcardlistleft'>
                        <span class="noprint">&nbsp;+</span>
                    </td>
                </tr> 
                <?php 
                // Only show this row if the decktype is Commander style
                if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"This is a '$decktype' deck, adding commander row",$logfile);
                    ?>
                    <tr>
                        <td colspan='7'>
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
                                echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;commander=no'><img class='delcard' src=images/bluearrow.png alt='commander'></a>";
                                echo "</td>";
                                echo "</td>";
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;deletemain=yes'><img class='delcard' src=images/delete.png alt='delete'></a>";
                                echo "</td>";
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;maintoside=yes'><img class='delcard' src=images/bluearrow.png alt='Add to sideboard'></a>";
                                echo "</td>";
                                echo "<td class='deckcardlistright noprint'>";
                                echo "</td>";
                                echo "<td class='deckcardlistcenter'>";
                                echo $quantity;
                                echo "</td>";
                                echo "<td class='deckcardlistleft noprint'>";
                                echo "</td>";
                                echo "</tr>";
                                $total = $total + $quantity;
                                $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                            endif;
                        endwhile; 
                    endif; 
                        ?>
                    <tr>
                        <td colspan='7'>
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
                        if ((strpos($row['type'],'Creature') !== false) AND ($row['commander'] != 1)):
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
                            if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                                echo "<td class='deckcardlistcenter noprint'>";
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"This is a '$decktype' deck, checking if $cardname is a valid commander",$logfile);
                                $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"$cardlegendary",$logfile);
                                if((strpos($cardlegendary, "Legendary") !== false) AND (strpos($cardlegendary, "Creature") !== false)):
                                    echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;commander=yes'><img class='delcard' src=images/arrowup.png alt='commander'></a>";
                                endif;
                                echo "</td>";
                            endif;
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;deletemain=yes'><img class='delcard' src=images/delete.png alt='delete'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;maintoside=yes'><img class='delcard' src=images/bluearrow.png alt='Add to sideboard'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistright noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;minusmain=yes'><img class='delcard' src=images/minus.png alt='Subtract'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter'>";
                            echo $quantity;
                            echo "</td>";
                            echo "<td class='deckcardlistleft noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;plusmain=yes'><img class='delcard' src=images/plus.png alt='Add'></a>";
                            echo "</td>";
                            echo "</tr>";
                            $total = $total + $quantity;
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif; ?>
                <tr>
                    <?php 
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan='7'>
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
                            if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;deletemain=yes'><img class='delcard' src=images/delete.png alt='delete'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;maintoside=yes'><img class='delcard' src=images/bluearrow.png alt='Add to sideboard'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistright noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;minusmain=yes'><img class='delcard' src=images/minus.png alt='Subtract'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter'>";
                            echo $quantity;
                            echo "</td>";
                            echo "<td class='deckcardlistleft noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;plusmain=yes'><img class='delcard' src=images/plus.png alt='Add'></a>";
                            echo "</td>";
                            echo "</tr>";
                            $total = $total + $quantity; 
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif; ?>
                <tr>
                    <?php 
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan='7'>
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
                        if ((strpos($row['type'],'Sorcery') === false) AND (strpos($row['type'],'Instant') === false) AND (strpos($row['type'],'Creature') === false) AND (strpos($row['type'],'Land') === false)):
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
                            if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;deletemain=yes'><img class='delcard' src=images/delete.png alt='delete'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;maintoside=yes'><img class='delcard' src=images/bluearrow.png alt='Add to sideboard'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistright noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;minusmain=yes'><img class='delcard' src=images/minus.png alt='Subtract'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter'>";
                            echo $quantity;
                            echo "</td>";
                            echo "<td class='deckcardlistleft noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;plusmain=yes'><img class='delcard' src=images/plus.png alt='Add'></a>";
                            echo "</td>";
                            echo "</tr>";
                            $total = $total + $quantity; 
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif;
                ?>
                <tr>
                    <?php 
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan='7'>
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
                            if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;deletemain=yes'><img class='delcard' src=images/delete.png alt='delete'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;maintoside=yes'><img class='delcard' src=images/bluearrow.png alt='Add to sideboard'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistright noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;minusmain=yes'><img class='delcard' src=images/minus.png alt='Subtract'></a>";
                            echo "</td>";
                            echo "<td class='deckcardlistcenter'>";
                            echo $quantity;
                            echo "</td>";
                            echo "<td class='deckcardlistleft noprint'>";
                            echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;plusmain=yes'><img class='delcard' src=images/plus.png alt='Add'></a>";
                            echo "</td>";
                            echo "</tr>";
                            $total = $total + $quantity; 
                            $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endif;
                    endwhile; 
                endif;?>
                <tr>
                    <?php 
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan="5">&nbsp;
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
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan="7">&nbsp;
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
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan='7'>
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
                        if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                        echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;deleteside=yes'><img class='delcard' src=images/delete.png alt='delete'></a>";
                        echo "</td>";
                        echo "<td class='deckcardlistcenter noprint'>";
                        echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;sidetomain=yes'><img class='delcard' src=images/arrowup.png alt='Add to mainboard'></a>";
                        echo "</td>";
                        echo "<td class='deckcardlistright noprint'>";
                        echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;minusside=yes'><img class='delcard' src=images/minus.png alt='Subtract'></a>";
                        echo "</td>";
                        echo "<td class='deckcardlistcenter'>";
                        echo $quantity;
                        echo "</td>";
                        echo "<td class='deckcardlistleft noprint'>";
                        echo "<a href='deckdetail.php?deck=$decknumber&amp;card=$cardid&amp;plusside=yes'><img class='delcard' src=images/plus.png alt='Add'></a>";
                        echo "</td>";
                        echo "</tr>";
                        $sidetotal = $sidetotal + $quantity;
                        $textfile = $textfile."$quantity x $cardname ($cardset)"."\r\n";
                        endwhile; 
                endif;?>
                <tr>
                    <?php 
                    if(($decktype == "Tiny Leader") OR ($decktype == "Commander")):
                        ?>    
                        <td colspan="5">&nbsp;
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
            <?php
            echo "<h4>Export decklist</h4>";
            $textfile = $textfile."\r\n\r\nNotes\r\n\r\n$notes\r\n";
            $textfile = $textfile."\r\n\r\nSideboard notes\r\n\r\n$sidenotes";
            $textfile = htmlspecialchars($textfile,ENT_QUOTES);
            $filename = preg_replace('/[^\w]/', '', $deckname);
            ?>
            <form action="dltext.php"  method="POST">
                <input class='profilebutton' type="submit" value="EXPORT">
                <?php echo "<input type='hidden' name='text' value='$textfile'>"; ?>
                <?php echo "<input type='hidden' name='filename' value='$filename'>"; ?>
            </form>
            <?php
            if ($requiredlist !== ''):
                echo "<h4>Missing cards</h4>";
                $requiredlist = htmlspecialchars($requiredlist,ENT_QUOTES);
                $requiredbuy = htmlspecialchars($requiredbuy,ENT_QUOTES);
                ?>
                <form action="dltext.php"  method="POST">
                    <input class='profilebutton' type="submit" value="LIST">
                    <?php echo "<input type='hidden' name='text' value='$requiredlist'>"; ?>
                    <?php echo "<input type='hidden' name='filename' value='{$filename}_needed'>"; ?>
                </form>
                <br>
                TCGPlayer: <a href="http://store.tcgplayer.com/list/selectproductmagic.aspx?partner=MTGCOLLECT&c=<?php echo $requiredbuy; ?>" target='_blank'>BUY</a>
            <?php
            endif;
            echo "<h4>Quick add</h4>";
            ?>
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
