<?php 
/* Version:     13.0
    Date:       25/03/23
    Name:       carddetail.php
    Purpose:    Card detail page
    Notes:       
    To do:      
    
    1.0
                Initial version
 *  2.0         
 *              Added legality 
 *  3.0  
 *              Added Admin replace image function
 *  4.0         
 *              Fixed Kamigawa flip bugs
 *  4.1     
 *              Added call to symbolreplace for # removal in flavor text (Ixalan)
 *  5.0
 *              Move image routines to Scryfall
 *  5.1
 *              Text edit - remove magiccards.info -> scryfall
 *  5.2
 *              Use Scryfall for legalities, fallback to DB if scryfall errors
 *  6.0
 *              Move from writelog to Message class
 *  7.0
 *              Extensive rewrite - mysqli, comments, logging, escaping, etc.
 *  8.0
 *              Fixes for PHP7.4 and MySQL8
 *  9.0         
 *              Moving price away from TCGPlayer partner API to use Scryfall-provided pricing
 * 10.0
 *              Refactoring for new database
 * 11.0
 *              Add extra card parts (related cards) handling, up to 7
 * 12.0
 *              Add Arena legalities
 * 13.0
 *              PHP 8.1 compatibility
*/

session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');    //Initialise and load error/logging file
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password
require ('includes/colour.php');

// If it's admin running the page, set variable
$admin = check_admin_control($adminip);

// Enable / disable deck functionality
$decks_on = 1;

// Pass data to this form by e.g. ?id=123456 
// GET is used from results page, POST is used for database update query.
if (isset($_GET["id"])):
    $cardid = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_SPECIAL_CHARS); 
elseif (isset($_POST["id"])):
    $cardid = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_SPECIAL_CHARS);     
endif;
$decktoaddto = filter_input(INPUT_GET, 'decktoaddto', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$newdeckname = filter_input(INPUT_GET, 'newdeckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$deckqty = filter_input(INPUT_GET, 'deckqty', FILTER_SANITIZE_NUMBER_INT);
$refreshimage = isset($_GET['refreshimage']) ? 'REFRESH' : '';
?> 

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title>MtG collection card details</title>
<link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
<?php include('includes/googlefonts.php');?>
<script src="/js/jquery.js"></script>
<script type="text/javascript">
    jQuery(document).ready(function(){
        $("img").each(function(){
        $(this).attr("onerror","this.src=’/cardimg/back.jpg'");
        });
    });
</script>
<script type="text/javascript">   
    jQuery( function($) {
        $('#deckselect').change(function (event) {
            if($('#deckselect').val() === 'newdeck'){
                $('#newdeckname').removeAttr("disabled");
                $('#newdeckname').attr("placeholder", "New deck name");
            } else {
                $('#newdeckname').attr("disabled", "disabled");
                $('#newdeckname').attr("placeholder", "N/A");
            }
            if($('#deckselect').val() !== 'none'){
                $('#addtodeckbutton').removeAttr("disabled");
                $('#deckqty').removeAttr("disabled");
                $('#deckqty').attr("placeholder", "");
            } else {
                $('#addtodeckbutton').attr("disabled", "disabled"); 
                $('#deckqty').attr("disabled", "disabled"); 
                $('#deckqty').attr("placeholder", "N/A"); 
            }
        });
    });
</script>
<script type="text/javascript">   
    jQuery( function($) {
        $('#addtodeck').submit(function() {
            if(($('#deckqty').val() === '') || ($('#deckqty').val() === '0') || (($('#deckselect').val() === 'newdeck')  &&  ($('#newdeckname').val() ===''))){
                alert("You need to complete the form...")
                return false;
            }
        });
    });
</script>
<script type="text/javascript"> 
    function CloseMe( obj )
        {
            obj.style.display = 'none';
        }
</script>  
<!-- Following script is to ensure that card numbers entered are valid-->
<script type="text/javascript">
    function isInteger(x) {
        if(x<0)
        {
            return false;
        }
        else
        {
            return x % 1 === 0;
        }
    }
    $(function() { 
        $(".carddetailqtyinput").change(function(){
            var ths = this;
            var myqty = $(ths).val();
            if(myqty=='')
            {
                alert("Enter a number");
                $(ths).focus();
            }
            else if(!isInteger(myqty))
            {
                alert("Enter a valid quantity");
                $(ths).focus();
            }
        });
    });
</script>
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
</script>
<!-- following script rotates flipimage -->
<script type="text/javascript">
    function rotateImg() {
        if ( document.querySelector(".mainimg").style.transform == 'none' ){
            document.querySelector(".mainimg").style.transform = "rotate(180deg)";
        } 
        else if ( document.querySelector(".mainimg").style.transform == '' ){
            document.querySelector(".mainimg").style.transform = "rotate(180deg)";
        } else {
            document.querySelector(".mainimg").style.transform = "none";
        }
    }
</script>
<script type="text/javascript"> 
    jQuery( function($) {
        $(".mainimg").mousemove(function(e)
            {         
                if (  document.querySelector(".mainimg").style.transform == 'rotate(180deg)' &&  (window.innerWidth > 1208)  ) {
                    $(".imgfloat").show();         
                    $(".imgfloat").css(
                        {
                            top: (e.pageY - 170) + "px",
                            left: (e.pageX + 95) + "px",
                            transform: 'rotate(180deg)'
                        }
                    );     
                } else if (window.innerWidth > 1208) {
                    $(".imgfloat").show();         
                    $(".imgfloat").css(
                        {
                            top: (e.pageY - 170) + "px",
                            left: (e.pageX + 95) + "px",
                            transform: ''
                        }
                    );     
                }
            });
        $(".mainimg").mouseout(function(e)
            {
                $(".imgfloat").hide();
            }
        );
    });

</script>
<script type="text/javascript"> 
jQuery( function($) {
    $(".backimg").mousemove(function(e)
        {         
            $(".backimgfloat").show();         
            $(".backimgfloat").css(
                {
                    top: (e.pageY - 170) + "px",
                    left: (e.pageX + 95) + "px"
                }
            );     
        });     
    $(".backimg").mouseout(function(e)
        {
            $(".backimgfloat").hide();     
        }
    );
});
</script> 
<script type="text/javascript">
    function swapImage(img_id,card_id,imageurl,imagebackurl){
        var ImageId = document.getElementById(img_id);
        var FrontImg = card_id + ".jpg";
        var BackImg = card_id + "_b.jpg";
        if (ImageId.src.match(FrontImg))
        { 
            document.getElementById(img_id).src = imagebackurl; 
        } else if (ImageId.src.match(BackImg))
        {
            document.getElementById(img_id).src = imageurl; 
        }
    };
</script>
</head>

<body class="body">
<?php 
include_once("includes/analyticstracking.php");
// Start building the page here, so errors show in the website template
// Includes first - menu and header            
require('includes/overlays.php');
require('includes/header.php'); 
require('includes/menu.php'); //mobile menu
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons"
      rel="stylesheet">    
<div id="page">
    <div id="carddetail">
        <div id="printtitle" class="headername">
            <img src="images/white_m.png">MtG collection
        </div>
    <?php
    // Does the user have a collection table?
    $tablecheck = "SELECT * FROM $mytable";
    $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Checking if user has a collection table...",$logfile);
    if($db->query($tablecheck) === FALSE):
        $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": No existing collection table...",$logfile);
        $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
        $obj = new Message;$obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": ...copying collection template...: $query2",$logfile);
        if($db->query($query2) === TRUE):
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy successful",$logfile);
        else:
            $obj = new Message;$obj->MessageTxt('[NOTICE]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": Collection template copy failed",$logfile);
        endif;
    endif;
    
    // Check that we have an id before calling SQL query
    if(isset($_GET["id"]) OR isset($_POST["id"])) :
        $cardid = $db->escape($cardid,'str');
        $searchqry = 
               "SELECT 
                    cards_scry.id as cs_id,
                    oracle_id,
                    tcgplayer_id,
                    multiverse,
                    multiverse2,
                    name,
                    printed_name,
                    flavor_name,
                    lang,
                    release_date,
                    set_name as cs_setname,
                    setcode as cs_setcode,
                    set_id as cs_set_id,
                    game_types,
                    type,
                    power,
                    toughness,
                    loyalty,
                    manacost,
                    cmc,
                    artist,
                    flavor,
                    color,
                    color_identity,
                    generatedmana,
                    number,
                    number_import,
                    layout,
                    rarity,
                    ability,
                    keywords,
                    f1_name,
                    f1_manacost,
                    f1_type,
                    f1_ability,
                    f1_colour,
                    f1_artist,
                    f1_flavor,
                    f1_power,
                    f1_toughness,
                    f1_loyalty,
                    f1_cmc,
                    f1_printed_name,
                    f1_flavor_name,
                    f2_name,
                    f2_manacost,
                    f2_type,
                    f2_ability,
                    f2_colour,
                    f2_artist,
                    f2_flavor,
                    f2_power,
                    f2_toughness,
                    f2_loyalty,
                    f2_cmc,
                    f2_printed_name,
                    f2_flavor_name,
                    p1_id,
                    p1_component,
                    p1_name,
                    p1_type_line,
                    p1_uri,
                    p2_id,
                    p2_component,
                    p2_name,
                    p2_type_line,
                    p2_uri,
                    p3_id,
                    p3_component,
                    p3_name,
                    p3_type_line,
                    p3_uri,
                    p4_id,
                    p4_component,
                    p4_name,
                    p4_type_line,
                    p4_uri,
                    p5_id,
                    p5_component,
                    p5_name,
                    p5_type_line,
                    p5_uri,
                    p6_id,
                    p6_component,
                    p6_name,
                    p6_type_line,
                    p6_uri,
                    p7_id,
                    p7_component,
                    p7_name,
                    p7_type_line,
                    p7_uri,
                    reserved,
                    cards_scry.foil as cs_foil,
                    nonfoil as cs_normal,
                    oversized,
                    promo,
                    gatherer_uri,
                    image_uri,
                    api_uri,
                    scryfall_uri,
                    legalityblock,
                    legalitystandard,
                    legalitymodern,
                    legalitylegacy,
                    legalityvintage,
                    legalitypioneer,
                    legalityalchemy,
                    legalityhistoric,
                    updatetime,
                    price,
                    price_foil,
                    normal,
                    $mytable.foil,
                    notes
                FROM cards_scry
                LEFT JOIN `$mytable` ON cards_scry.id = `$mytable`.id
                WHERE cards_scry.id = '$cardid'
                LIMIT 1";
        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query is: $searchqry",$logfile);
        if($result = $db->query($searchqry)):
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
        else:
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        endif;
        $qtyresults = $result->num_rows;
        // If the result has a card:
        if (!$qtyresults == 0) :
            $row = $result->fetch_array(MYSQLI_BOTH);
            $setcode = $db->escape(strtolower($row['cs_setcode']),'str');
            $setcodeupper = strtoupper($setcode);
            $setname = stripslashes($db->escape($row['cs_setname'],'str'));
            $cardname = stripslashes($db->escape($row['name'],'str'));
            $id = $db->escape($row['cs_id'],'str');
            if($row['color'] !== null):
                $card_colour = colourfunction($row['color']);
            else:
                $card_colour = '';
            endif;
            if($row['f1_colour'] !== null):
                $f1_colour = colourfunction($row['f1_colour']);
            else:
                $f1_colour = '';
            endif;
            if($row['f2_colour'] !== null):
                $f2_colour = colourfunction($row['f2_colour']);
            else:
                $f2_colour = '';
            endif;
            if($card_colour !== ''):
                $colour = $card_colour;
            elseif($f1_colour !== ''):
                $colour = $f1_colour;
            elseif($f2_colour !== ''):
                $colour = $f2_colour;
            else:
                $colour = '';
            endif;
            if($row['f2_ability'] !== null):
                $flipability = $row['f2_ability'];
            endif;
            if (strpos($row['game_types'], 'paper') == false):
                $arenaonly = true;
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Arena only card",$logfile);
            else:
                $arenaonly = false;
            endif;
            $cardnumber = $db->escape($row['number'],'int');
            if(($row['p1_component'] === 'meld_result' AND $row['p1_name'] === $row['name']) 
                 OR ($row['p2_component'] === 'meld_result' AND $row['p2_name'] === $row['name']) 
                 OR ($row['p3_component'] === 'meld_result' AND $row['p3_name'] === $row['name'])
                 OR ($row['p4_component'] === 'meld_result' AND $row['p4_name'] === $row['name'])
                 OR ($row['p5_component'] === 'meld_result' AND $row['p5_name'] === $row['name'])
                 OR ($row['p6_component'] === 'meld_result' AND $row['p6_name'] === $row['name'])
                 OR ($row['p7_component'] === 'meld_result' AND $row['p7_name'] === $row['name'])):
                $meld = 'meld_result';
            elseif($row['p1_component'] === 'meld_part' 
                 OR $row['p2_component'] === 'meld_part' 
                 OR $row['p3_component'] === 'meld_part'
                 OR $row['p4_component'] === 'meld_part'
                 OR $row['p5_component'] === 'meld_part'
                 OR $row['p5_component'] === 'meld_part'
                 OR $row['p6_component'] === 'meld_part'
                 OR $row['p7_component'] === 'meld_part'):
                $meld = 'meld_part';
            else:
                $meld = '';
            endif;
            //Populate JSON data
            $tcg_buy_uri = scryfall($id);
            if(isset($row['layout']) AND $row['layout'] === "normal"):
                $scryfallimg = $row['image_uri'];
            else:
                $scryfallimg = null;
            endif;
            if($scryfallimg !== null):
                $scryfallimg = $db->escape($scryfallimg,'str');
            endif;
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Scryfall image location called by $useremail: $scryfallimg",$logfile);
            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Call for getimgname by $useremail with $setcode, $cardnumber, $cardname, $cardid",$logfile);
            $imgname = $cardid.".jpg";
            $imgname_2 = $cardid."_b.jpg";
            $obj = new Message;
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Call for getImageNew by $useremail with $setcode,$id,$ImgLocation, {$row['layout']}",$logfile);
            $imagefunction = getImageNew($setcode,$row['cs_id'],$ImgLocation,$row['layout']);
            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"getImageNew result: {$imagefunction['front']} / {$imagefunction['back']}",$logfile);
            if($imagefunction['front'] == 'error'):
                $imageurl = '/cardimg/back.jpg';
            else:
                $imageurl = $imagefunction['front'];
            endif;
            if(!is_null($imagefunction['back'])):
                if($imagefunction['back'] === '' OR $imagefunction['back'] === 'error' OR $imagefunction['back'] === 'empty'):
                    $imagebackurl = '/cardimg/back.jpg';
                else:
                    $imagebackurl = $imagefunction['back'];
                endif;
            endif;
            $settotal = 0;
            // If the current record has null fields set the variables to 0 so the update query works 
            if (empty($row['normal'])):
                $myqty = 0;
            else:
                $myqty = $db->escape($row['normal'],'int');
            endif;
            if (empty($row['foil'])):
                $myfoil = 0;
            else:
                $myfoil = $db->escape($row['foil'],'int');
            endif;
            $notes = $db->escape($row['notes'],'str');

            if (isset($_POST['update'])) :    
                if (isset($_POST['myqty'])):
                    $myqty = filter_input(INPUT_POST, 'myqty', FILTER_SANITIZE_NUMBER_INT);
                endif;
                if (isset($_POST['myfoil'])):
                    $myfoil = filter_input(INPUT_POST, 'myfoil', FILTER_SANITIZE_NUMBER_INT);
                endif;
                if (isset($_POST['notes'])):
                    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
                endif;
                $sqlmyqty = $db->escape($myqty,'int');
                $sqlmyfoil = $db->escape($myfoil,'int');
                $sqlnotes = $db->escape($notes,'str');
                if(isset($row['price']) AND (is_null($row['price']) OR $row['price'] == '' )):
                    $price = 0.00;
                elseif(isset($row['price'])):
                    $price = $row['price'];
                else:
                    $price = 0.00;
                endif;
                if(isset($row['price_foil']) AND (is_null($row['price_foil']) OR $row['price_foil'] == '' )):
                    $foilprice = 0.00;
                elseif(isset($row['price_foil'])):
                    $foilprice = $row['price_foil'];
                else:
                    $foilprice = 0.00;
                endif;
                if($myfoil == 0):
                    $topvalue = $price;
                else:
                    $topvalue = $foilprice;
                endif;
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,$myqty." ".$price." ".$myfoil." ".$foilprice,$logfile);
                $updatequery = "
                        INSERT INTO `$mytable` (normal,foil,notes,id,topvalue)
                        VALUES ($sqlmyqty,$sqlmyfoil,'$sqlnotes','$id',$topvalue)
                        ON DUPLICATE KEY UPDATE 
                        normal=$sqlmyqty, foil=$sqlmyfoil, notes='$sqlnotes', topvalue=$topvalue";
                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,$updatequery,$logfile);
                // write out collection record prior to update to log
                if($sqlbefore = $db->query("SELECT id,normal,foil,notes,topvalue FROM `$mytable` WHERE id = '$id'")):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded ",$logfile);
                else:
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                endif;
                $beforeresult = $sqlbefore->fetch_array(MYSQLI_ASSOC);
                $writerowforlog = "";
                foreach((array)$beforeresult as $key => $value): 
                    if (!is_int($key)):
                        $writerowforlog .= "index ".$key.", value ".$value. ": "; 
                    endif;
                endforeach;
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"User $useremail({$_SERVER['REMOTE_ADDR']}) initial values: $writerowforlog",$logfile);
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"User $useremail({$_SERVER['REMOTE_ADDR']}) update call: ".file_get_contents('php://input'),$logfile);
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"User $useremail({$_SERVER['REMOTE_ADDR']}) running update query: $updatequery",$logfile);
                // Run update
                if($sqlupdate = $db->query($updatequery)):
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL update query succeeded",$logfile);
                else:
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL update failure: " . $db->error, E_USER_ERROR);
                endif;
                // Retrieve new record to display
                if($sqlcheck = $db->select('*',"`$mytable`","WHERE id = '$id'")):
                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL check query succeeded",$logfile);
                else:
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL check failure: " . $db->error, E_USER_ERROR);
                endif;
                $checkresult = $sqlcheck->fetch_array(MYSQLI_ASSOC);
                $checkresult['normal'] = $db->escape($checkresult['normal'],'int');
                $checkresult['foil'] = $db->escape($checkresult['foil'],'int');
                $checkresult['notes'] = $db->escape($checkresult['notes'],'str');
                if (($sqlmyqty === $checkresult['normal']) AND ($sqlmyfoil === $checkresult['foil']) AND ($sqlnotes === $checkresult['notes'])): ?>
                    <div class="alert-box success" id="cardupdate">Updated</div>
                <?php else: ?>
                    <div class="alert-box error" id="cardupdate">Failed</div>
                <?php endif;
            endif; 
            //Process image change if it's been called by an admin.
            if (isset($_POST['import']) AND $admin == 1):
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Image upload called by $useremail",$logfile);
                if (is_uploaded_file($_FILES['filename']['tmp_name'])):
                    $handle = fopen($_FILES['filename']['tmp_name'], "r");
                    $info = getimagesize($_FILES['filename']['tmp_name']);
                    if (($info === FALSE) OR ($info[2] !== IMAGETYPE_JPEG)):
                        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Image upload failed - not an image or not a JPG",$logfile);
                        echo "<div class='alert-box error carddetailnewdeck' onclick='CloseMe(this)'><span>Error: </span>Not a JPG image";
                        echo "<img class='x' align='right' src='images/close.gif' alt='x'></div>";
                    else:
                        $upload_name = $ImgLocation.strtolower($setcode)."/".$imgname;
                        if(!move_uploaded_file( $_FILES['filename']['tmp_name'], $upload_name)):
                            echo "<div class='alert-box error carddetailnewdeck' onclick='CloseMe(this)'><span>Error: </span>Image write failed";
                            echo "<img class='x' align='right' src='images/close.gif' alt='x'></div>";
                            $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Image upload for $cardid by $useremail failed",$logfile);
                        else:
                            //Image upload successful. Set variable to load card page 'fresh' at completion (see end of script)
                            $ctrlf5 = 1;
                            $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Image upload for $cardid by $useremail ok",$logfile);
                        endif;
                    endif;
                endif;
            endif;
            if (isset($refreshimage) AND $refreshimage === 'REFRESH' AND $admin == 1):
                $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Image refresh called for $cardid by $useremail",$logfile);
                refresh_image($cardid);
            endif;
            $setcode = htmlentities($setcode,ENT_QUOTES,"UTF-8");
            $setname = htmlentities($setname,ENT_QUOTES,"UTF-8");
            $namehtml = $row['name'];
            $row['name'] = htmlentities($row['name'],ENT_QUOTES,"UTF-8");
            $row['number'] = htmlentities($row['number'],ENT_QUOTES,"UTF-8");
            $colour = (isset($colour)) ? htmlentities($colour,ENT_QUOTES,"UTF-8") : '';
            $row['type'] = (isset($row['type'])) ? htmlentities($row['type'],ENT_QUOTES,"UTF-8") : '';
            $row['manacost'] = (isset($row['manacost'])) ? htmlentities($row['manacost'],ENT_QUOTES,"UTF-8") : '';
            $row['cmc'] = (isset($row['cmc'])) ? htmlentities($row['cmc'],ENT_QUOTES,"UTF-8") : '';
            $row['power'] = (isset($row['power'])) ? htmlentities($row['power'],ENT_QUOTES,"UTF-8") : '';
            $row['toughness'] = (isset($row['toughness'])) ? htmlentities($row['toughness'],ENT_QUOTES,"UTF-8") : '';
            $row['loyalty'] = (isset($row['loyalty'])) ? htmlentities($row['loyalty'],ENT_QUOTES,"UTF-8") : '';
            $row['artist'] = (isset($row['artist'])) ? htmlentities($row['artist'],ENT_QUOTES,"UTF-8") : '';
            $card_normal = (isset($row['cs_normal'])) ? $row['cs_normal'] : '';
            $card_foil = (isset($row['cs_foil'])) ? $row['cs_foil'] : '';
            $myqty = (isset($myqty)) ? htmlentities($myqty,ENT_QUOTES,"UTF-8") : '';
            $myfoil = (isset($myfoil)) ? htmlentities($myfoil,ENT_QUOTES,"UTF-8") : '';
            $notes = (isset($notes)) ? htmlentities($notes,ENT_QUOTES,"UTF-8") : '';

            $flip_types = ['transform','art_series','modal_dfc','reversible_card','double_faced_token'];  // Set flip types which trigger a second (reverse) card section
            $token_layouts = ['double_faced_token','token','emblem'];
            ?>
                <div id="carddetailheader">
                    <table>
                        <tr>
                            <td class="h2pad" id='nameheading'>
                                <?php 
                                    if(isset($row['flavor_name']) AND $row['flavor_name'] !== ''):
                                        echo "{$row['flavor_name']} <i>({$row['name']})</i>";
                                    else:
                                        echo $row['name'];
                                    endif;
                                ?>
                            </td>
                            <td id="carddetailset">
                                <?php
                                echo "<a href='index.php?adv=yes&amp;sortBy=setdown&amp;set%5B%5D=$setcode'>$setname</a>&nbsp;"; ?>
                            </td>
                            <td id="carddetaillogo">
                                <?php 
                                echo "<img alt='image' src=images/".$colour."_s.png>"; ?>
                            </td>
                        </tr>
                        <tr>
                            <td  id="carddetailflavour" colspan="3">
                                <?php 
                                if($row['f1_flavor'] != '' AND $row['f2_flavor'] != ''):
                                    $mainflavour = htmlentities($row['f1_flavor'],ENT_QUOTES,"UTF-8")." // ".htmlentities($row['f2_flavor'],ENT_QUOTES,"UTF-8");
                                elseif($row['flavor'] != '' AND $row['f2_flavor'] != ''):
                                    $mainflavour = htmlentities($row['flavor'],ENT_QUOTES,"UTF-8")." // ".htmlentities($row['f2_flavor'],ENT_QUOTES,"UTF-8");
                                elseif($row['f1_flavor'] != '' AND $row['f2_flavor'] == ''):
                                    $mainflavour = htmlentities($row['f1_flavor'],ENT_QUOTES,"UTF-8");
                                elseif($row['f2_flavor'] != '' AND $row['f1_flavor'] == ''):
                                    $mainflavour = htmlentities($row['f2_flavor'],ENT_QUOTES,"UTF-8");
                                elseif($row['flavor'] != ''):
                                    $mainflavour = htmlentities($row['flavor'],ENT_QUOTES,"UTF-8");
                                else:
                                    $mainflavour = null;
                                endif;
                                if($mainflavour !== null):
                                    echo $mainflavour;
                                endif;
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div id="minicarddetailheader">
                    <?php
                        echo "<h2 class = 'h2pad'>".$row['name']."</h2>";
                    ?>
                    <?php 
                    echo "<a href='index.php?adv=yes&amp;sortBy=setdown&amp;set%5B%5D=$setcode'>$setname</a>"; ?>
                </div> 
                <div id="carddetailmain">
                    <div id="carddetailimage">
                  <?php if($row['layout'] === 'flip'): ?>
                            <div style="cursor: pointer;" class='fliprotate' onClick="rotateImg()">
                                <span class='material-icons md-24'>refresh</span>
                            </div>
                  <?php endif; 
                        $img_id = 'cardimg';
                        if (in_array($row['layout'],$flip_types)):
                            echo "<div style='cursor: pointer;' class='flipbuttondetail' onclick=swapImage(\"{$img_id}\",\"{$row['cs_id']}\",\"{$imageurl}\",\"{$imagebackurl}\")><span class='material-icons md-24'>refresh</span></div>";
                        endif; ?>
                        <table>
                            <tr> 
                                <td colspan="6">
                                    <?php 
                                    $lookupid = htmlentities($row['cs_id'],ENT_QUOTES,"UTF-8");
                                    //If page is being loaded by admin, don't cache the main image
                                    if(($admin == 1) AND ($imageurl !== '/cardimg/back.jpg')):
                                        $obj = new Message;
                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin loading, don't cache image",$logfile);
                                        $imgmodtime = filemtime($ImgLocation.strtolower($setcode)."/".$imgname);
                                        $imagelocation = $imageurl.'?='.$imgmodtime;
                                    else:
                                        $imagelocation = $imageurl;
                                    endif;
                                    $obj = new Message;
                                    $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Image location is ".$imagelocation,$logfile);
                                    // Set classes for hover image
                                    if($row['layout'] === 'split' OR $row['layout'] === 'planar'):
                                        $hoverclass = 'imgfloat splitfloat';
                                    else:
                                        $hoverclass = 'imgfloat';
                                    endif;
                                    ?>
                                        <div class='<?php echo $hoverclass; ?>' id='image-<?php echo $row['cs_id'];?>'>
                                            <img alt='<?php echo $imagelocation;?>' src='<?php echo $imagelocation;?>'>
                                        </div>
                                    <?php
                                    if(isset($row['multiverse'])):
                                        $multiverse_id = $row['multiverse'];
                                        echo "<a href='https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=".$multiverse_id."' target='_blank'><img alt='$lookupid' id='cardimg' class='mainimg' src=$imagelocation></a>"; 
                                    elseif(isset($row['scryfall_uri'])):
                                        echo "<a href='".$row['scryfall_uri']."' target='_blank'><img alt='$lookupid' id='cardimg' class='mainimg' src=$imagelocation></a>"; 
                                    else:
                                        echo "<a href='https://gatherer.wizards.com/' target='_blank'><img alt='$lookupid' id='cardimg' class='mainimg' src=$imagelocation></a>"; 
                                    endif;
                                        
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="previousbutton">
                                    <?php if ($row['number'] > 1):
                                        // Find the next number card's ID
                                        $prevcard = $cardnumber - 1;
                                        if($prevcardresult = $db->select_one('id','cards_scry',"WHERE setcode = '$setcode' AND number = $prevcard ORDER BY manacost DESC")):
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
                                            $prevcardid = $prevcardresult['id'];
                                        else:
                                            $prevcardid = "";
                                            //trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                        endif;
                                        if(!empty($prevcardid)): ?>
                                            <form action="?" method="get">
                                                <?php echo "<input type='hidden' name='setabbrv' value=".$row['cs_setcode'].">";
                                                echo "<input type='hidden' name='id' value=".$prevcardid.">";
                                                echo "<input type='hidden' name='number' value=".$prevcard.">"; ?>
                                                <input type="submit" style="cursor: pointer;" class="inline_button previousbutton" name="prevcard" value="PREVIOUS">
                                            </form>
                                            <?php
                                        endif;
                                    endif; ?>
                                </td>
                                <td colspan="3" class="nextbutton">
                                    <?php if (($row['number'] < $settotal) OR (empty($settotal))): 
                                        // Find the next number card's ID
                                        $nextcard = $cardnumber + 1;
                                        if($nextcardresult = $db->select_one('id','cards_scry',"WHERE setcode = '$setcode' AND number = $nextcard ORDER BY manacost DESC")):
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
                                            $nextcardid = $nextcardresult['id'];
                                        else:
                                            $nextcardid = "";
                                            //trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                        endif;
                                        if(!empty($nextcardid)): ?>
                                            <form action="?" method="get">
                                                <?php echo "<input type='hidden' name='setabbrv' value=".$row['cs_setcode'].">";
                                                echo "<input type='hidden' name='id' value=".$nextcardid.">";
                                                echo "<input type='hidden' name='number' value=".$nextcard.">"; ?>
                                                <input type="submit" style="cursor: pointer;" class="inline_button nextbutton" name="nextcard" value="NEXT">
                                            </form>
                                        <?php endif;
                                    endif; ?>
                                </td>
                            </tr>
                            <?php 

                            //If if's an admin, show controls to change/refresh the image(s) for the card.
                            if ($admin == 1):
                                // Form to change the image
                                ?>
                                <tr>
                                    <td colspan='4'>
                                        <form id="imgreplace" action = "?" method = "GET" enctype = "multipart/form-data">
                                            <input type='hidden' name='setabbrv' value="<?php echo $row['cs_setcode']; ?>">
                                            <input type='hidden' name='id' value="<?php echo $row[0]; ?>">
                                            <input type='hidden' name='number' value="<?php echo $row['number']; ?>">
                                            <table>
                                                <tr>
                                                    <td class='imgreplace'>
                                                        <label class='importlabel' style="cursor: pointer;" id='imgpick'>
                                                            <input class='importlabel' id='importfile' type='file' name='filename'>
                                                            <span>IMAGE</span>
                                                        </label>
                                                    </td>
                                                    <td class="imgreplace">
                                                        <button class='importlabel' style="cursor: pointer;" id='importsubmit' type='submit' name='import' value='REPLACE' disabled>REPLACE</button>
                                                    </td>
                                                    <td class="imgreplace">
                                                        <button class='importlabel' style="cursor: pointer;" id='refreshsubmit' type='submit' name='refreshimage' value='REFRESH'>REFRESH</button>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </td>
                                </tr>    
                                <?php
                            endif;
                            ?>
                        </table>
                    </div>
                    <div id="carddetailinfo">
                        <?php 
                        if ($arenaonly == true):
                            echo "<h3 class='shallowh3'>Details (MtG Arena)</h3>";
                        else:
                            echo "<h3 class='shallowh3'>Details</h3>";
                        endif;
                        
                        if(isset($admin) AND $admin == 1):
                            echo "<a href='admin/cards.php?cardtoedit=$lookupid' target='blank'><i>$setname ($setcodeupper) no. {$row['number_import']}</i></a><br>";
                        else:
                            echo "<i>$setname($setcodeupper) no. {$row['number_import']}</i><br>";
                        endif;
                        if($row["layout"] !== 'reversible_card' AND $row["layout"] !== 'double_faced_token'): // no details at card level for reversible cards
                            if(isset($row['type']) AND $row['type'] != ''):
                                echo "<b>Type: </b>".$row['type'];
                            endif;
                            if(isset($row['lang']) AND $row['lang'] != '' AND $row['lang'] != 'en'):
                                echo "<br><b>Lang: </b>".langreplace($row['lang']);
                            endif;
                            echo "<br>";
                            echo "<b>Rarity: </b>";
                            if (strpos($row['rarity'],"rare") !== false):
                                echo "Rare";
                            elseif (strpos($row['rarity'],"mythic") !== false):
                                echo "Mythic Rare";
                            elseif (strpos($row['rarity'],"uncommon") !== false):
                                echo "Uncommon";
                            else:
                                echo "Common";
                            endif;
                            echo "<br>";
                            if(validateTrueDecimal($row['cmc']) === false):
                                $row['cmc'] = round($row['cmc']);
                            endif;
                            if(!in_array($row['layout'],$token_layouts)):
                                echo "<b>CMC: </b>".$row['cmc'];
                                echo "<br>";
                            endif;
                        endif;
                        $layouts_double = array ('transform','modal_dfc','adventure','split','reversible_card','flip','double_faced_token');
                        if(in_array($row["layout"],$layouts_double)):
                            if(isset($row['f1_flavor_name']) AND $row['f1_flavor_name'] !== ''):
                                echo "<b>Name: </b>{$row['f1_flavor_name']} <i>({$row['f1_name']})</i>";
                            else:
                                echo "<b>Name: </b>".$row['f1_name'];
                            endif;
                            echo "<br>";
                            if($row['layout'] === 'reversible_card'):
                                if(validateTrueDecimal($row['f1_cmc']) === false):
                                    $row['f1_cmc'] = round($row['f1_cmc']);
                                endif;
                                echo "<b>CMC: </b>".$row['f1_cmc'];
                                echo "<br>";
                            endif;
                            $manacost = symbolreplace($row['f1_manacost']);
                            if($manacost !== ''):
                                echo "<b>Mana cost: </b>".$manacost;
                                echo "<br>";
                            endif;
                            if(isset($row['f1_type']) AND $row['f1_type'] != ''):
                                echo "<b>Type: </b>".$row['f1_type'];
                                echo "<br>";
                            endif;
                            if(isset($row['lang']) AND $row['lang'] != '' AND $row['lang'] != 'en'):
                                echo "<b>Lang: </b>".langreplace($row['lang']);
                                echo "<br>";
                            endif;
                            if($row['f1_ability'] != ''):
                                echo "<b>Abilities: </b>".symbolreplace($row['f1_ability']);
                                echo "<br>";
                            endif;
                            if (strpos($row['f1_type'],'reature') !== false):
                                echo "<b>Power / Toughness: </b>".$row['f1_power']."/".$row['f1_toughness']; 
                                echo "<br>";
                            elseif (strpos($row['f1_type'],'laneswalker') !== false):
                                echo "<b>Loyalty: </b>".$row['f1_loyalty'];
                                echo "<br>";
                            endif;
                        else:
                            $manacost = symbolreplace($row['manacost']);
                            if($manacost !== ''):
                                echo "<b>Mana cost: </b>".$manacost;
                                echo "<br>";
                            endif;
                            if($row['ability'] != ''):
                                echo "<b>Abilities: </b>".symbolreplace($row['ability']);
                                echo "<br>";
                            endif;
                            if (strpos($row['type'],'reature') !== false):
                                echo "<b>Power / Toughness: </b>".$row['power']."/".$row['toughness']; 
                                echo "<br>";
                            elseif (strpos($row['type'],'laneswalker') !== false):
                                echo "<b>Loyalty: </b>".$row['loyalty']; 
                                echo "<br>";
                            endif;
                        endif;
                        if($meld === 'meld_part'):
                            if($row['p1_component'] === 'meld_part' AND $row['p1_name'] !== $row['name']):
                                $meld_partner_id = $row['p1_id'];
                                $meld_partner_name = $row['p1_name'];
                            elseif($row['p2_component'] === 'meld_part' AND $row['p2_name'] !== $row['name']):
                                $meld_partner_id = $row['p2_id'];
                                $meld_partner_name = $row['p2_name'];
                            elseif($row['p3_component'] === 'meld_part' AND $row['p3_name'] !== $row['name']):
                                $meld_partner_id = $row['p3_id'];
                                $meld_partner_name = $row['p3_name'];
                            elseif($row['p4_component'] === 'meld_part' AND $row['p4_name'] !== $row['name']):
                                $meld_partner_id = $row['p4_id'];
                                $meld_partner_name = $row['p4_name'];
                            elseif($row['p5_component'] === 'meld_part' AND $row['p5_name'] !== $row['name']):
                                $meld_partner_id = $row['p5_id'];
                                $meld_partner_name = $row['p5_name'];
                            elseif($row['p6_component'] === 'meld_part' AND $row['p6_name'] !== $row['name']):
                                $meld_partner_id = $row['p6_id'];
                                $meld_partner_name = $row['p6_name'];
                            else:
                                $meld_partner_id = $row['p7_id'];
                                $meld_partner_name = $row['p7_name'];
                            endif;
                            echo "<b>Melds with:</b><br>";
                            echo "<a href='carddetail.php?id=$meld_partner_id'>$meld_partner_name</a>&nbsp;<br>";
                            echo "<b>to:</b><br>";
                            if($row['p1_component'] === 'meld_result'):
                                $meld_result_id = $row['p1_id'];
                                $meld_result_name = $row['p1_name'];
                            elseif($row['p2_component'] === 'meld_result'):
                                $meld_result_id = $row['p2_id'];
                                $meld_result_name = $row['p2_name'];
                            elseif($row['p3_component'] === 'meld_result'):
                                $meld_result_id = $row['p3_id'];
                                $meld_result_name = $row['p3_name'];
                            elseif($row['p4_component'] === 'meld_result'):
                                $meld_result_id = $row['p4_id'];
                                $meld_result_name = $row['p4_name'];
                            elseif($row['p5_component'] === 'meld_result'):
                                $meld_result_id = $row['p5_id'];
                                $meld_result_name = $row['p5_name'];
                            elseif($row['p6_component'] === 'meld_result'):
                                $meld_result_id = $row['p6_id'];
                                $meld_result_name = $row['p6_name'];
                            else:
                                $meld_result_id = $row['p7_id'];
                                $meld_result_name = $row['p7_name'];
                            endif;
                            echo "<a href='carddetail.php?id=$meld_result_id'>$meld_result_name</a>&nbsp;<br>";
                        elseif($meld === 'meld_result'):
                            echo "<b>Melds from:</b><br>";
                            if($row['p1_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p1_id']}'>{$row['p1_name']}</a>&nbsp;<br>";
                            endif;
                            if($row['p2_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p2_id']}'>{$row['p2_name']}</a>&nbsp;<br>";
                            endif;
                            if($row['p3_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p3_id']}'>{$row['p3_name']}</a>&nbsp;<br>";
                            endif;
                            if($row['p4_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p4_id']}'>{$row['p4_name']}</a>&nbsp;<br>";
                            endif;
                            if($row['p5_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p5_id']}'>{$row['p5_name']}</a>&nbsp;<br>";
                            endif;
                            if($row['p6_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p6_id']}'>{$row['p6_name']}</a>&nbsp;<br>";
                            endif;
                            if($row['p7_component'] === 'meld_part'):
                                echo "<a href='carddetail.php?id={$row['p7_id']}'>{$row['p7_name']}</a>&nbsp;<br>";
                            endif;
                        endif;
                        if($row['artist'] != ''):
                            echo "<b>Art by: </b>".$row['artist'];
                            echo "<br>";
                        endif;
                        if((substr($row['type'],0,6) != 'Plane ') AND $row['type'] != 'Phenomenon'):
                            echo "<b>Legal in: </b>";    
                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Getting legalities for $setcode, $cardname, $id",$logfile);
                            $legalitystring = '';
                            
                            if($row['legalitystandard'] == 'legal'):
                                $legalitystring = "Standard";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) !== "; "):
                                $legalitystring .= "; ";
                            endif;
                            
                            if($row['legalityalchemy'] == 'legal'):
                                $legalitystring .= "Alchemy";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) !== "; "):
                                $legalitystring .= "; ";
                            endif;
                            
                            if($row['legalityhistoric'] == 'legal'):
                                $legalitystring .= "Historic";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) !== "; "):
                                $legalitystring .= "; ";
                            endif;
                            
                            if($row['legalitypioneer'] == 'legal'):
                                $legalitystring .= "Pioneer";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) !== "; "):
                                $legalitystring .= "; ";
                            endif;
                            
                            if($row['legalitymodern'] == 'legal'):
                                $legalitystring .= "Modern";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) !== "; "):
                                $legalitystring .= "; ";
                            endif;
                            
                            if($row['legalityvintage'] == 'legal'):
                                $legalitystring .= "Vintage";
                            elseif($row['legalityvintage'] == 'restricted'):
                                $legalitystring .= "Vintage: restricted";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) !== "; "):
                                $legalitystring .= "; ";
                            endif;
                            
                            if($row['legalitylegacy'] == 'legal'):
                                $legalitystring .= "Legacy";
                            elseif($row['legalitylegacy'] == 'restricted'):
                                $legalitystring .= "Legacy: restricted";
                            endif;
                            
                            if($legalitystring !== '' AND substr($legalitystring,-2) === "; "):
                                $legalitystring = substr($legalitystring,0,-2);
                            endif;
                            
                            if($legalitystring === ''):
                                $legalitystring = 'None';
                            endif;
                            
                            echo $legalitystring."<br>";
                        endif;    
                        if($row['layout'] === 'adventure'):
                            echo "<h3>Adventure: </h3>";
                            echo "<b>Name: </b>".$row['f2_name'];
                            echo "<br>";
                            $flipmanacost = symbolreplace($row['f2_manacost']);
                            if($flipmanacost !== ''):
                                echo "<b>Mana cost: </b>".$flipmanacost;
                                echo "<br>";
                            endif;
                            if(isset($row['f2_type']) AND $row['f2_type'] != ''):
                                echo "<b>Type: </b>".$row['f2_type'];
                                echo "<br>";
                            endif;
                            if(isset($row['lang']) AND $row['lang'] != '' AND $row['lang'] != 'en'):
                                echo "<b>Lang: </b>".langreplace($row['lang']);
                                echo "<br>";
                            endif;
                            if(isset($flipability) AND $flipability != ''):
                                echo "<b>Abilities: </b>".$flipability;
                                echo "<br>";
                            endif;
                            if (strpos($row['f2_type'],'reature') !== false):
                                echo "<b>Power / Toughness: </b>".$row['f1_power']."/".$row['f2_toughness']."<br>"; 
                            elseif (strpos($row['f2_type'],'laneswalker') !== false):
                                echo "<b>Loyalty: </b>".$row['f2_loyalty']."<br>"; 
                            endif;
                        elseif($row['layout'] === 'split' OR $row['layout'] === 'flip'):
                            echo "<br><b>Name: </b>".$row['f2_name'];
                            echo "<br>";
                            $flipmanacost = symbolreplace($row['f2_manacost']);
                            if($flipmanacost !== ''):
                                echo "<b>Mana cost: </b>".$flipmanacost;
                                echo "<br>";
                            endif;
                            if(isset($row['f2_type']) AND $row['f2_type'] != ''):
                                echo "<b>Type: </b>".$row['f2_type'];
                                echo "<br>";
                            endif;
                            if(isset($row['lang']) AND $row['lang'] != '' AND $row['lang'] != 'en'):
                                echo "<b>Lang: </b>".langreplace($row['lang']);
                                echo "<br>";
                            endif;
                            if(isset($flipability) AND $flipability != ''):
                                $flipability = symbolreplace($flipability);
                                echo "<b>Abilities: </b>".$flipability;
                                echo "<br>";
                            endif;
                            if (strpos($row['f2_type'],'reature') !== false):
                                echo "<b>Power / Toughness: </b>".$row['f1_power']."/".$row['f2_toughness']."<br>"; 
                            elseif (strpos($row['f2_type'],'laneswalker') !== false):
                                echo "<b>Loyalty: </b>".$row['f2_loyalty']."<br>"; 
                            endif;
                        endif;
                        if(isset($row['scryfall_uri']) AND $row['scryfall_uri'] !== "" AND $arenaonly === true):
                            echo "<a href='".$row['scryfall_uri']."' target='_blank'>Card on Scryfall</a></br>";
                            echo "<a href='index.php?name=".$row['name']."&amp;exact=yes'>All printings </a>";
                        elseif($arenaonly === true):
                            $namehtml = str_replace("//","",$namehtml);
                            $namehtml = str_replace("  ","%20",$namehtml);
                            $namehtml = str_replace(" ","%20",$namehtml);
                            echo "<a href='https://magiccards.info/query?q=".$namehtml."' target='_blank'>Search Scryfall</a>";
                        endif;
                        ?>                
                    </div><?php 
                    if(($meld !== 'meld_result') AND ($arenaonly !== true )): ?>
                            <div id="carddetailupdate">
                                <form action="?" method="POST">
                                <h3 class="shallowh3">My collection</h3>
                                <?php
                                if ((int)$card_foil + (int)$card_normal === 1): ?>
                                    <table>
                                        <tr>
                                            <td>
                                                Quantity
                                            </td>
                                        </tr>
                                        <?php
                                        if((int)$card_normal === 1 AND (int)$card_foil !== 1): // No foils
                                            if (isset($_POST["update"])) :
                                                $checkresult['normal'] = htmlentities($checkresult['normal'],ENT_QUOTES,"UTF-8");           
                                                $checkresult['foil'] = htmlentities($checkresult['foil'],ENT_QUOTES,"UTF-8");
                                                $checkresult['notes'] = htmlentities($checkresult['notes'],ENT_QUOTES,"UTF-8");
                                                echo "<tr><td><input class='carddetailqtyinput textinput' type='number' name='myqty' min='0' value=".$checkresult['normal']."></td>";
                                                echo "<tr><td>My notes</td></tr>";
                                                echo "<tr><td><textarea class='textinput' name='notes' rows='2' cols='40'>{$checkresult['notes']}</textarea></td></tr>";
                                            else: 
                                                echo "<tr><td><input class='carddetailqtyinput textinput' type='number' name='myqty' min='0' value=".$myqty."></td>";
                                                echo "<tr><td>My notes</td></tr>";
                                                echo "<tr><td><textarea class='textinput' id='cardnotes' name='notes' rows='2' cols='40'>$notes</textarea></td></tr>";
                                            endif; 
                                        elseif((int)$card_normal !== 1 AND (int)$card_foil === 1): // No foils
                                            if (isset($_POST["update"])) :
                                                $checkresult['normal'] = htmlentities($checkresult['normal'],ENT_QUOTES,"UTF-8");           
                                                $checkresult['foil'] = htmlentities($checkresult['foil'],ENT_QUOTES,"UTF-8");
                                                $checkresult['notes'] = htmlentities($checkresult['notes'],ENT_QUOTES,"UTF-8");
                                                echo "<tr><td><input class='carddetailqtyinput textinput' type='number' name='myfoil' min='0' value=".$checkresult['foil']."></td>";
                                                echo "<tr><td>My notes</td></tr>";
                                                echo "<tr><td><textarea class='textinput' name='notes' rows='2' cols='40'>{$checkresult['notes']}</textarea></td></tr>";
                                            else: 
                                                echo "<tr><td><input class='carddetailqtyinput textinput' type='number' name='myfoil' min='0' value=".$myfoil."></td>";
                                                echo "<tr><td>My notes</td></tr>";
                                                echo "<tr><td><textarea class='textinput' id='cardnotes' name='notes' rows='2' cols='40'>$notes</textarea></td></tr>";
                                            endif; 
                                        endif;
                                        ?>
                                    </table>
                          <?php else: ?>
                                    <table>
                                        <tr>
                                            <td>
                                                Normal
                                            </td>
                                            <td>
                                                Foil
                                            </td>
                                        </tr>
                                        <?php if (isset($_POST["update"])) :
                                            echo "<tr><td><input class='carddetailqtyinput textinput' type='number' name='myqty' min='0' value=".$checkresult['normal']."></td>";
                                            echo "<td><input class='carddetailqtyinput textinput' type='number' name='myfoil' min='0' value=".$checkresult['foil']."></td></tr>";
                                            echo "<tr><td colspan='2'>My notes</td></tr>";
                                            echo "<tr><td colspan='2'><textarea class='textinput' name='notes' rows='2' cols='40'>{$checkresult['notes']}</textarea></td></tr>";
                                        else: 
                                            echo "<tr><td><input class='carddetailqtyinput textinput' type='number' name='myqty' min='0' value=$myqty></td>";
                                            echo "<td><input class='carddetailqtyinput textinput' type='number' name='myfoil' min='0' value=$myfoil></td></tr>";
                                            echo "<tr><td colspan='2'>My notes</td></tr>";
                                            echo "<tr><td colspan='2'><textarea class='textinput' id='cardnotes' name='notes' rows='2' cols='40'>$notes</textarea></td></tr>";
                                        endif; ?>
                                    </table>
                                <?php 
                                endif;
                                echo "<input type='hidden' name='id' value=".$lookupid.">";
                                echo "<input type='hidden' name='update' value='yes'>";?>
                                <input class='inline_button stdwidthbutton updatebutton' style="cursor: pointer;" type="submit" value="UPDATE">
                                </form>
                                <hr class='hr324'>
                                <?php 

                                // Price section
                                echo "<b>Price and links</b>";
                                if(isset($tcg_buy_uri) AND $tcg_buy_uri !== ""):
                                    $tcgdirectlink = $tcg_buy_uri;
                                else:
                                    $tcgdirectlink = null;
                                endif;?>
                                <table id='tcgplayer' width="100%">
                          <?php if((isset($row["price"]) AND $row["price"] !== "")):?>
                                    <tr>
                                        <td class="buycellleft">
                                            Normal
                                        </td>
                                        <td class="buycell mid">
                                            <?php echo $row["price"]; ?>
                                        </td>
                                    </tr>
                          <?php endif;      
                                if((isset($row["price_foil"]) AND $row["price_foil"] !== "")):?>
                                    <tr>
                                        <td class="buycellleft">
                                            Foil
                                        </td>
                                        <td class="buycell mid">
                                            <?php echo $row["price_foil"]; ?>
                                        </td>
                                    </tr>
                          <?php endif; ?>
                                    <tr>
                                        <td colspan=2 class="buycellleft">
                                            <?php
                                            if(isset($row['scryfall_uri']) AND $row['scryfall_uri'] !== ""):
                                                echo "<a href='".$row['scryfall_uri']."' target='_blank'>Card on Scryfall</a>";
                                            else:
                                                $namehtml = str_replace("//","",$namehtml);
                                                $namehtml = str_replace("  ","%20",$namehtml);
                                                $namehtml = str_replace(" ","%20",$namehtml);
                                                echo "<a href='https://magiccards.info/query?q=".$namehtml."' target='_blank'>Search Scryfall</a>";
                                            endif;
                                            ?>
                                        </td>
                                    </tr>
                          <?php if(isset($tcgdirectlink) AND $tcgdirectlink !== null): ?>
                                    <tr>
                                        <td colspan=2 class="buycellleft">
                                        <?php
                                        echo "<a href='".$tcgdirectlink."' target='_blank'>Card on TCGPlayer</a>";
                                        ?>
                                        </td>
                                    </tr>
                                    <?php 
                                endif; ?>
                                    <tr>
                                        <td colspan=2 class="buycellleft">
                                            <?php echo "<a href='index.php?name=".$row['name']."&amp;exact=yes'>All printings </a>"; ?>
                                        </td>
                                    </tr>
                                </table>
                                <hr class='hr324'>
                                <?php
                                    
                                // Others with this card section 
                                echo "<b>Others with this card</b><br>";
                                if($usergrprow = $db->select_one('grpinout,groupid','users',"WHERE usernumber = $user")):
                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
                                else:
                                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                endif;
                                if ($usergrprow['grpinout'] == 1):
                                    if($sqluserqry = $db->query('SELECT usernumber, username, status FROM users')):
                                        $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
                                    else:
                                        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                    endif;
                                    $others = 0;
                                    while ($userrow = $sqluserqry->fetch_array(MYSQLI_ASSOC)):
                                        if($userrow['status'] !== 'disabled'):
                                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Scanning ".$userrow['username']."'s cards",$logfile);
                                            if ($_SESSION["user"] !== $userrow['usernumber']):
                                                $usertable = $userrow['usernumber'].'collection';
                                                if($sqlqtyqry = $db->query("SELECT id,normal,foil,notes,topvalue FROM `$usertable` WHERE id = '$row[0]'")):
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL query succeeded",$logfile);
                                                else:
                                                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                                endif;
                                                if($sqlqtyqry->num_rows !== 0):
                                                    $userqtyresult = $sqlqtyqry->fetch_array(MYSQLI_ASSOC);
                                                    if (($userqtyresult['normal'] > 0) OR ($userqtyresult['foil'] > 0)):
                                                        if (empty($userqtyresult['normal'])):
                                                            $userqtyresult['normal'] = 0;
                                                        endif;
                                                        if (empty($userqtyresult['foil'])):
                                                            $userqtyresult['foil'] = 0;
                                                        endif;
                                                        $others = 1;
                                                        $userrow['username'] = htmlentities($userrow['username'],ENT_QUOTES,"UTF-8");
                                                        $userqtyresult['normal'] = htmlentities($userqtyresult['normal'],ENT_QUOTES,"UTF-8");
                                                        $userqtyresult['foil'] = htmlentities($userqtyresult['foil'],ENT_QUOTES,"UTF-8");
                                                        echo ucfirst($userrow['username']),": &nbsp;<i>Normal:</i> ",$userqtyresult['normal']," &nbsp;&nbsp;<i>Foil:</i> ",$userqtyresult['foil'],"<br>";
                                                    endif;
                                                endif;
                                            endif;
                                        endif;
                                    endwhile;
                                    if ($others == 0):
                                        echo "N/A<br>";
                                    endif;
                                else:
                                    echo "Opt in for groups in Profile";
                                endif;
                                ?>
                                <hr class='hr324'>
                                <?php
                                $obj = new Message;
                                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Decks enabled: $decks_on",$logfile);
                                    if(in_array($row['layout'],$token_layouts)):
                                        $decks_on = 0;
                                    endif;
                                    if($decks_on === 1):
                                        echo "<div id='deckadd'>";
                                    
                                    // Add to decks
                                    
                                
                                    
                                    
                                
                                    // Logic on handling input from submitted form
                                    // $decktoaddto is the number of the deck (decks.decknumber), or 'newdeck'
                                    // $newdeckname is the deck name if $decktoaddto is 'newdeck'
                                    // $deckqty is the quantity to add

                                    if (isset($decktoaddto)):
                                        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Received request to add $deckqty x card $cardid to deck $decktoaddto $newdeckname",$logfile);
                                        // If the deck is new, is the new name unique? If yes, create it.
                                        $decksuccess = 0;
                                        if($decktoaddto == "newdeck"):
                                            $newdeckname = $db->escape($newdeckname,'str');
                                            $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Asked to create new deck $newdeckname",$logfile);
                                            if($result = $db->select_one('decknumber','decks',"WHERE owner = $user AND deckname = '$newdeckname'")):
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"New deck name already exists",$logfile);
                                                ?>
                                                <div class="alert-box error carddetailnewdeck" onclick='CloseMe(this)'><span>error: </span>Deck name exists
                                                    <img class = "x" align="right" src="images/close.gif" alt="x"></div>
                                                <?php
                                                $decksuccess = 10; //set flag so we know to break.
                                            else:
                                                //Create new deck
                                                $data = array(
                                                    'owner' => $user,
                                                    'deckname' => "$newdeckname"
                                                );
                                                if($runsql = $db->insert('decks',$data) === TRUE):
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL deck insert succeeded: ".$db->insert_id,$logfile);
                                                else:
                                                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                                endif;
                                                $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Running confirm SQL query",$logfile);
                                                $checksql = "SELECT decknumber FROM decks
                                                                WHERE owner = $user AND deckname = '$newdeckname' LIMIT 1";
                                                if($runquery = $db->select_one('decknumber','decks',"WHERE owner = $user AND deckname = '$newdeckname'")):
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Confirmed existence of deck: $newdeckname",$logfile);
                                                    ?>
                                                    <div class="alert-box success carddetailnewdeck" onclick='CloseMe(this)'>
                                                        <span>Success: </span>Deck created
                                                        <img class = "x" align="right" src="images/close.gif" alt="x">
                                                    </div>
                                                    <?php 
                                                    $decksuccess = 1; //set flag so we know we don't need to check for cards in deck.
                                                    $decktoaddto = $runquery['decknumber'];
                                                else:    
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Failed - deck: $newdeckname not created",$logfile);
                                                    ?>
                                                    <div class="alert-box error carddetailnewdeck" onclick='CloseMe(this)'>
                                                        <span>error: </span>Deck creation failed
                                                        <img class = "x" align="right" src="images/close.gif" alt="x">
                                                    </div>
                                                    <?php
                                                    $decksuccess = 10; //set flag so we know to break.
                                                endif;
                                            endif;
                                        else:
                                            $decktoaddto = $db->escape($decktoaddto,'str');
                                            // Check that the proposed deck exists and belongs to owner.
                                            if (deckownercheck($decktoaddto,$user) == FALSE): ?>
                                                <div class="alert-box error carddetailnewdeck" onclick='CloseMe(this)'><span>error: </span>You don't have that deck
                                                    <img class = "x" align="right" src="images/close.gif" alt="x"></div> 
                                                <?php
                                                $decksuccess = 10;
                                            else:
                                                $decksuccess = 2;
                                            endif;
                                        endif;
                                        // Here we either have successfully created a new deck (1), failed to create (10), or confirmed ownership and existence (2)
                                        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Decksuccess code is $decksuccess",$logfile);
                                        if ($decksuccess !== 10):  //I.e. the deck now exists and belongs to the caller
                                            if ($decksuccess === 2): //Not a new deck, run card check
                                                $obj = new Message;
                                                $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Running SQL to see if $cardid is already in deck $decktoaddto",$logfile);
                                                if($resultchk = $db->select_one('cardnumber','deckcards',"WHERE decknumber = $decktoaddto AND cardnumber = '$cardid'
                                                                        AND ((cardqty IS NOT NULL) OR (sideqty IS NOT NULL))")):
                                                    $obj = new Message;
                                                    $obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"{$resultchk['cardnumber']} is already in that deck",$logfile);
                                                    ?>
                                                    <div class="alert-box error carddetailnewdeck" onclick='CloseMe(this)'>
                                                        <span>error: </span>Card is already in deck
                                                        <img class = "x" align="right" src="images/close.gif" alt="x">
                                                    </div>
                                                    <?php
                                                    $cardchecksuccess = 0;
                                                else:    
                                                    $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Card is not in the deck, proceeding to write",$logfile);
                                                    $cardchecksuccess = 1;
                                                endif;
                                            elseif ($decksuccess = 1):
                                                $cardchecksuccess = 2;
                                            endif;
                                            //Insert card to deck
                                            if (($cardchecksuccess === 1) OR ($cardchecksuccess === 2)):
                                                $deckqty = $db->escape($deckqty,'int');
                                                adddeckcard($decktoaddto,$cardid,'main',$deckqty);
                                                if($resultchkins = $db->select_one('cardnumber, cardqty','deckcards',"WHERE decknumber = $decktoaddto AND cardnumber = '$cardid' AND cardqty = '$deckqty'")):
                                                    $obj = new Message;$obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"SQL deck insert succeeded: ".$db->insert_id,$logfile);
                                                    if(($resultchkins['cardnumber'] == $cardid) AND ($resultchkins['cardqty'] == $deckqty)):
                                                        ?>
                                                        <div class="alert-box success carddetailnewdeck" onclick='CloseMe(this)'><span>Success: </span>Card added
                                                            <img class = "x" align="right" src="images/close.gif" alt="x"></div>
                                                        <?php
                                                        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Card $cardid added to deck $decktoaddto",$logfile);
                                                    else:?>
                                                        <div class="alert-box error carddetailnewdeck" onclick='CloseMe(this)'><span>error: </span>Card add failed
                                                            <img class = "x" align="right" src="images/close.gif" alt="x"></div>
                                                        <?php 
                                                        $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Card $cardid was not added to deck $decktoaddto",$logfile);
                                                    endif;
                                                else:
                                                    ?>
                                                    <div class="alert-box error carddetailnewdeck" onclick='CloseMe(this)'><span>error: </span>Card add failed
                                                        <img class = "x" align="right" src="images/close.gif" alt="x"></div>
                                                    <?php 
                                                    $obj = new Message;$obj->MessageTxt('[ERROR]',basename(__FILE__)." ".__LINE__,"Card $cardid was not added to deck $decktoaddto",$logfile);
                                                endif;
                                            endif;
                                        endif;
                                    endif; ?>

                                    <!-- Display Add to Deck form -->

                                    <b>Add card to my decks</b><br>

                                    <form id="addtodeck" action="<?php echo basename(__FILE__); ?>#deck" method="GET">
                                        <?php echo "<input type='hidden' name='setabbrv' value=".$row['cs_setcode'].">";
                                        echo "<input type='hidden' name='number' value=".$row['number'].">";
                                        echo "<input type='hidden' name='id' value=".$row[0].">"; ?>
                                        <select id='deckselect' name='decktoaddto'>
                                            <option value='none'>Select</option>
                                            <option value='newdeck'>Add to new deck...</option>
                                            <?php 
                                            $decklist = $db->select('decknumber, deckname','decks',"WHERE owner = $user ORDER BY deckname ASC");
                                            while ($dlrow = $decklist->fetch_array(MYSQLI_NUM)):
                                                $dlrow[0] = htmlentities($dlrow[0],ENT_QUOTES,"UTF-8");
                                                $dlrow[1] = htmlentities($dlrow[1],ENT_QUOTES,"UTF-8");
                                                echo "<option value='{$dlrow[0]}'>$dlrow[1]</option>";
                                            endwhile;
                                            ?>
                                        </select>
                                        Quantity <input class='textinput' id='deckqty' type='number' min='0' disabled placeholder='N/A' name='deckqty' value="">
                                        <br><br>
                                        <input class='textinput' id='newdeckname' disabled type='text' name='newdeckname' placeholder='N/A' size='19'/>
                                        <input class='inline_button addtodeckbutton' id="addtodeckbutton" disabled type="submit" value="ADD TO DECK">
                                    </form>
                                    </div>
                                <?php 
                                endif; ?>
                            </div>
                    <?php 
                    endif; ?>
                </div>

                <!-- Rulings -->

                <div id="carddetailrulings">
                    <?php 
                    $ruling_sql = "SELECT source,published_at,comment FROM rulings_scry WHERE oracle_id = ?";
                    $stmt = $db->prepare($ruling_sql);
                    $ruling = '';
                    if ($stmt === false):
                        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": Preparing SQL: " . $db->error, E_USER_ERROR);
                    endif;
                    $bind = $stmt->bind_param('s', $row['oracle_id']);
                    if ($bind === false):
                        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": Binding SQL: " . $db->error, E_USER_ERROR);
                    endif;
                    $exec = $stmt->execute();
                    if($exec === false):
                        trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": Executing SQL: " . $db->error, E_USER_ERROR);
                    else:     
                        $result = $stmt->get_result();
                        $obj = new Message;$obj->MessageTxt('[NOTICE]',basename(__FILE__)." ".__LINE__,"Rulings: {$result->num_rows} ({$row['oracle_id']})",$logfile);
                        if (($result->num_rows === 0) AND !in_array($row['layout'],$flip_types)):
                            // no rulings ?>
                            <div>
                            <h3 class='shallowh3'>Rulings</h3>&nbsp;
                            None
                            </div> <?php
                        elseif ($result->num_rows === 0):
                            // no rulings
                        else:
                            echo("<div>");
                            while($rulingrow = $result->fetch_array(MYSQLI_ASSOC)):
                                $olddateparts = explode('-', $rulingrow['published_at']); //Converting yyyy/mm/dd to dd/mm/yyyy
                                $newdate = "<b>".$olddateparts[2].'-'.$olddateparts[1].'-'.$olddateparts[0]."</b>";
                                if($rulingrow['source'] === 'wotc'):
                                    $source = 'WOTC';
                                elseif($rulingrow['source'] === 'scryfall'):
                                    $source = 'Scryfall';
                                else:
                                    $source = $rulingrow['source'];
                                endif;
                                $ruling = $ruling.$newdate.": ".symbolreplace($rulingrow['comment'])." (".$source.")<br>";
                            endwhile;
                            $ruling = autolink($ruling, array("target"=>"_blank","rel"=>"nofollow"));
                            if (!in_array($row['layout'],$flip_types)):
                                echo "<h3 class='shallowh3'>Rulings:</h3> ".$ruling."&nbsp;";
                            endif;
                            echo("</div>");
                        endif;
                    endif; ?>
                </div>
                <!-- Flip card -->
                <?php 
                if (in_array($row['layout'],$flip_types)): ?>
                    <div id="carddetailflip">
                        <div id="carddetailflipimg">    
                            <table>
                               <tr> 
                                    <td colspan="2">
                                        <?php 
                                        $lookupid = htmlentities($row['cs_id'],ENT_QUOTES,"UTF-8");
                                        //If page is being loaded by admin, don't cache the main image
                                        if(($admin == 1) AND ($imagebackurl !== '/cardimg/back.jpg')):
                                            $obj = new Message;
                                            $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Admin loading, don't cache image",$logfile);
                                            $imgmodtime = filemtime($ImgLocation.strtolower($setcode)."/".$imgname_2);
                                            $imagelocationback = $imagebackurl.'?='.$imgmodtime;
                                        else:
                                            $imagelocationback = $imagebackurl;
                                        endif;
                                        
                                        $obj = new Message;
                                        $obj->MessageTxt('[DEBUG]',basename(__FILE__)." ".__LINE__,"Image location is ".$imagelocationback,$logfile);
                                        ?>
                                            <div class='backimgfloat' id='image-<?php echo $row['cs_id'];?>'>
                                                <img alt='<?php echo $imagelocationback;?>' src='<?php echo $imagelocationback;?>'>
                                            </div>
                                        <?php
                                        if(isset($row['multiverse2'])):
                                            $multiverse_id_2 = $row['multiverse2'];
                                            echo "<a href='https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=".$multiverse_id_2."' target='_blank'><img alt='$lookupid' id='cardimg' class='backimg' src=$imagelocationback></a>"; 
                                        elseif(isset($row['scryfall_uri']) AND $row['scryfall_uri'] !== ""):
                                            echo "<a href='".$row['scryfall_uri']."' target='_blank'><img alt='$lookupid' id='cardimg' class='backimg' src=$imagelocationback></a>"; 
                                        else:
                                            echo "<a href='https://gatherer.wizards.com/' target='_blank'><img alt='$lookupid' id='cardimg' class='backimg' src=$imagelocationback></a>"; 
                                        endif;
                                        
                                        ?>      

                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div id="carddetailflipinfo">
                            <h3 class="shallowh3">Flip details</h3>
                            <?php 
                            if(isset($row['f2_flavor_name']) AND $row['f2_flavor_name'] !== ''):
                                echo "<b>Name: </b>{$row['f2_flavor_name']} <i>({$row['f2_name']})</i>";
                            else:
                                echo "<b>Name: </b>".$row['f2_name'];
                            endif;
                            echo "<br>";
                            if(isset($row['f2_cmc']) AND validateTrueDecimal($row['f2_cmc']) === false):
                                $row['cmc'] = round($row['cmc']);
                                echo "<b>CMC: </b>".$row['cmc'];
                                echo "<br>";
                            elseif(isset($row['f2_cmc']) AND validateTrueDecimal($row['f2_cmc']) === true):
                                echo "<b>CMC: </b>".$row['cmc'];
                                echo "<br>";
                            endif;
                            $flipmanacost = symbolreplace($row['f2_manacost']);
                            if($flipmanacost !== ''):
                                echo "<b>Mana cost: </b>".$flipmanacost;
                                echo "<br>";
                            endif;
                            if(isset($row['f2_type']) AND $row['f2_type'] != ''):
                                echo "<b>Type: </b>".$row['f2_type'];
                                echo "<br>";
                            endif;
                            if(isset($row['lang']) AND $row['lang'] != '' AND $row['lang'] != 'en'):
                                echo "<b>Lang: </b>".langreplace($row['lang']);
                                echo "<br>";
                            endif;
                            if(isset($flipability) AND $flipability != ''):
                                $flipability = symbolreplace($flipability);
                                echo "<b>Abilities: </b>".$flipability;
                                echo "<br>";
                            endif;
                            if (strpos($row['f2_type'],'reature') !== false):
                                echo "<b>Power / Toughness: </b>".$row['f1_power']."/".$row['f2_toughness'];
                                echo "<br>";
                            elseif (strpos($row['f2_type'],'laneswalker') !== false):
                                echo "<b>Loyalty: </b>".$row['f2_loyalty'];
                                echo "<br>";
                            endif;
                            if($row['f2_artist'] != ''):
                                echo "<b>Art by: </b>".$row['f2_artist']."<br>";
                            endif;
                            ?>                
                        </div>
                    </div>
                    <div id="flipcarddetailrulings">
                        <?php 
                        if($ruling === ''):
                            echo "<h3 class='shallowh3'>Rulings</h3>";
                            echo "None";
                        else:
                            echo "<h3 class='shallowh3'>Rulings</h3> ".$ruling."&nbsp;";
                        endif; ?>
                    </div> <?php
                endif; 
                ?>
        <!-- Disqus -->
        <?php
                    $page_url = strtok(get_full_url(),'?')."?id=".$cardid;
                    if (strpos($page_url,'obelix') !== false):
                        $disqus_site = 'https://mtgdev.disqus.com/embed.js';
                    else:
                        $disqus_site = 'https://mtgcollection.disqus.com/embed.js';
                    endif;
                    ?>
                    <div id="disqus_thread"></div>
                        <script>
                            var disqus_config = function () {
                            this.page.url = '<?php echo $page_url;?>';
                            this.page.identifier = '<?php echo $page_url;?>';
                            this.page.title = '<?php echo $row['name']." - ".$setname;?>';
                            };
                            (function() {
                            var d = document, s = d.createElement('script');
                            s.src = '<?php echo $disqus_site;?>';
                            s.setAttribute('data-timestamp', +new Date());
                            (d.head || d.body).appendChild(s);
                            })();
                        </script>
                        <noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
            <?php
        else :
            echo 'No such card, check the details.';
        endif;
    else:
        echo '<h3>Error</h3>Card ID not supplied';
    endif; 
    ?>
    </div>
</div>
<?php 
if (isset($ctrlf5)):
    echo "<meta http-equiv='refresh' content='0;url=carddetail.php?id=$cardid'>";
endif;
require('includes/footer.php'); ?>    
</body>
</html>