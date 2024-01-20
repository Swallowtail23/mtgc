<?php 
/* Version:     19.1
    Date:       20/01/24
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
 * 14.0
 *              Add flip capability for battle cards
 * 15.0
 *              Add check for decks with card
 *              Move to auto-update for card quantity changes
 *              Error messaging update to modern design
 * 16.0
 *              Show thick card promo type for Commander proxy cards
 *              Review and improve price handling routine to ensure latest price more reliably shown
 * 16.1
 *              Show serialised promo type
 *
 * 17.0         27/11/23
 *              Added display of secondary currency to prices
 * 
 * 18.0         10/12/23
 *              SQL parameterised query fixes
 * 
 * 19.0         02/01/24
 *              Correctly interpret language codes to 'pretty' descriptions
 *
 * 19.1         20/01/24
 *              Move session.name to include and use logMessage
*/

if (file_exists('includes/sessionname.php')):
    require('includes/sessionname.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');     //Initialise and load error/logging file
require ('includes/functions.php');          //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
forcechgpwd();                               //Check if user is disabled or needs to change password
require ('includes/colour.php');

$msg = new Message($logfile);

// Is admin running the page
$msg->logMessage('[DEBUG]',"Admin is $admin");

// Enable / disable deck functionality
$decks_on = 1;

// Pass data to this form by e.g. ?id=123456 
// GET is used from results page, POST is used for database update query.
if (isset($_GET["id"])):
    $cardid = valid_uuid($_GET["id"]);
elseif (isset($_POST["id"])):
    $cardid = valid_uuid($_POST["id"]); 
endif;

$decktoaddto = filter_input(INPUT_GET, 'decktoaddto', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$newdeckname = filter_input(INPUT_GET, 'newdeckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
if(filter_input(INPUT_GET, 'deckqty', FILTER_SANITIZE_NUMBER_INT) == ''):
    $deckqty = 1;
else:
    $deckqty = filter_input(INPUT_GET, 'deckqty', FILTER_SANITIZE_NUMBER_INT);
endif;

$refreshimage = isset($_GET['refreshimage']) ? 'REFRESH' : '';
?> 

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitle;?> - card details</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
    <script src="/js/ajaxUpdate.js"></script>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var button = document.getElementById('addtodeckbutton');
            button.value = 'ADD'; // Replace 'New Button Text' with the text you want to display
        });
        
        $(function() {  // On document ready
            $("img").on("error", function() {
                $(this).attr("src", "/cardimg/back.jpg");
            });

            $("#importsubmit").prop('disabled', true);
            $("#importfile").change(function() {
                $("#importsubmit").prop('disabled', !$(this).val());
            });

            $('#deckselect').change(function (event) {
                if($(this).val() === 'newdeck'){
                    $('#deckqtyspan').attr("style", "display: inline");
                    $('#deckqty').removeAttr("disabled");
                    $('#deckqty').attr("placeholder", "1");
                    $('#newdecknamespan').attr("style", "display: block");
                    $('#newdeckname').removeAttr("disabled");
                    $('#newdeckname').attr("placeholder", "New deck name");
                    $('#addtodecksubmitspan').attr("style", "display: block");
                    $('#addtodeckbutton').removeAttr("disabled");
                } else if($('#deckselect').val() === 'none'){
                    $('#deckqtyspan').attr("style", "display: none");
                    $('#deckqty').attr("disabled", "disabled");
                    $('#deckqty').attr("placeholder", "N/A");
                    $('#newdecknamespan').attr("style", "display: none");
                    $('#newdeckname').attr("disabled", "disabled");
                    $('#newdeckname').attr("placeholder", "N/A");
                    $('#addtodecksubmitspan').attr("style", "display: none");
                    $('#addtodeckbutton').attr("disabled", "disabled");
                } else {
                    $('#deckqtyspan').attr("style", "display: inline");
                    $('#deckqty').attr("placeholder", "1");
                    $('#deckqty').removeAttr("disabled");
                    $('#newdecknamespan').attr("style", "display: none");
                    $('#newdeckname').attr("disabled", "disabled");
                    $('#addtodecksubmitspan').attr("style", "display: block");
                    $('#addtodeckbutton').removeAttr("disabled");
                }
            });

            $('#addtodeck').submit(function() {
                if(($('#deckselect').val() === 'newdeck')  &&  ($('#newdeckname').val() ==='')){
                    alert("You need to complete the form...")
                    return false;
                }
            });

            var mainImg = $(".mainimg");
            var imgFloat = $(".imgfloat");
            var backImg = $(".backimg");
            var backImgFloat = $(".backimgfloat");
            mainImg.mousemove(function(e) {         
                var transform = mainImg.css('transform');
                if (transform === 'rotate(180deg)' && window.innerWidth > 1208) {
                    imgFloat.show();         
                    imgFloat.css({
                        top: (e.pageY - 170) + "px",
                        left: (e.pageX + 95) + "px",
                        transform: 'rotate(180deg)'
                    });     
                } else if (window.innerWidth > 1208) {
                    imgFloat.show();         
                    imgFloat.css({
                        top: (e.pageY - 170) + "px",
                        left: (e.pageX + 95) + "px",
                        transform: ''
                    });     
                }
            }).mouseout(function(e) {
                imgFloat.hide();
            });

            backImg.mousemove(function(e){         
                backImgFloat.show();         
                backImgFloat.css(
                    {
                        top: (e.pageY - 170) + "px",
                        left: (e.pageX + 95) + "px"
                    }
                );     
            }).mouseout(function(e)
                {
                    backImgFloat.hide();
                }
            );

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

            var textarea = $('#cardnotes');
            var saveButton = $('.save_icon');
            var initialValue = textarea.val();
            textarea.on('input', function() {
                saveButton.prop('disabled', textarea.val() === initialValue);
            });
        });

        // Other js functions
        function CloseMe( obj )
        {
            obj.style.display = 'none';
            window.location.href="carddetail.php?id=<?php echo $cardid;?>";
        };

        function isInteger(x) {
            if(x<0)
            {
                return false;
            }
            else
            {
                return x % 1 === 0;
            }
        };

        function rotateImg() {
            var mainImg = document.querySelector(".mainimg");
            mainImg.style.transform = mainImg.style.transform === 'rotate(180deg)' ? 'none' : 'rotate(180deg)';
        };

        function swapImage(img_id,card_id,imageurl,imagebackurl){
            var ImageId = document.getElementById(img_id);
            var FrontImg = card_id + ".jpg";
            var BackImg = card_id + "_b.jpg";

            if (ImageId.src.match(FrontImg))
            { 
                ImageId.classList.add('flipped');
                setTimeout(function () {
                    ImageId.src = imagebackurl;
                }, 80);
            } else {
                ImageId.classList.remove('flipped');
                setTimeout(function () {
                    ImageId.src = imageurl;
                }, 80);
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
    <div id="carddetail"> <?php
        if($cardid === false):
            echo "<h2 class='h2pad'>Invalid card UUID</h2>";
            exit;
        endif; ?>
        <div id="printtitle" class="headername">
            <img src="images/white_m.png"><?php echo $siteTitle;?>
        </div>
    <?php
    // Does the user have a collection table?
    $tableExistsQuery = "SHOW TABLES LIKE '$mytable'";
    $msg->logMessage('[DEBUG]', "Checking if user has a collection table...");

    $result = $db->query($tableExistsQuery);
    if ($result->num_rows == 0):
        $msg->logMessage('[NOTICE]', "No existing collection table...");
        $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
        $msg->logMessage('[DEBUG]', "Copying collection template...: $query2");

        if ($db->query($query2) === TRUE):
            $msg->logMessage('[NOTICE]', "Collection template copy successful");
        else:
            $msg->logMessage('[NOTICE]', "Collection template copy failed: " . $db->error);
        endif;
    else:
        $msg->logMessage('[DEBUG]', "Collection table exists");
    endif;
    
    // Check that we have an id before calling SQL query
    if(isset($_GET["id"]) OR isset($_POST["id"])) :
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
                    primary_card,
                    release_date,
                    set_name as cs_setname,
                    setcode as cs_setcode,
                    set_id as cs_set_id,
                    game_types,
                    finishes,
                    promo_types,
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
                    legalitycommander,
                    updatetime,
                    price,
                    price_foil,
                    price_etched,
                    normal,
                    $mytable.foil,
                    $mytable.etched,
                    notes
                FROM cards_scry
                LEFT JOIN `$mytable` ON cards_scry.id = `$mytable`.id
                WHERE cards_scry.id = ?
                LIMIT 1";
        $params = [$cardid];
        
        $msg->logMessage('[DEBUG]',"SQL query is: $searchqry");
        if($result = $db->execute_query($searchqry, $params)):
            $msg->logMessage('[DEBUG]',"SQL query succeeded");
        else:
            trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
        endif;
        $qtyresults = $result->num_rows;
        // If the result has a card:
        if (!$qtyresults == 0) :
            $row = $result->fetch_array(MYSQLI_BOTH);
            $setcode = strtolower($row['cs_setcode']);
            $setcodeupper = strtoupper($setcode);
            $setname = stripslashes($row['cs_setname']);
            $cardname = stripslashes($row['name']);
            $id = $row['cs_id'];
            $card_lang = $row['lang'];
            $card_lang_uc = strtoupper($card_lang);
            $card_primary = $row['primary_card'];
                        
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
                $not_paper = true;
                $msg->logMessage('[DEBUG]',"Arena/Online only card");
            else:
                $not_paper = false;
            endif;
            $thick = $serialised = false;
            if (isset($row['promo_types']) AND $row['promo_types'] !== null):
                $promo = json_decode($row['promo_types']);
                $msg->logMessage('[DEBUG]',"Card has a promo_type set: {$row['promo_types']}");
                $full_promo_text = '';
                foreach($promo as $value):
                    $promo_description = promo_lookup($value);
                    if($promo_description !== 'skip'):
                        if($full_promo_text === ''):
                            $full_promo_text = $full_promo_text . "$promo_description";
                        else:
                            $full_promo_text = $full_promo_text . ", $promo_description";
                        endif;
                    endif;
                endforeach;
            endif;
            $cardnumber = $row['number'];
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
            if(isset($row['price'])):
                $price_log = $row['price'];
            else:
                $price_log = NULL;
            endif;
            if(isset($row['price_foil'])):
                $price_foil_log = $row['price_foil'];
            else:
                $price_foil_log = NULL;
            endif;
            if(isset($row['price_etched'])):
                $price_etched_log = $row['price_etched'];
            else:
                $price_etched_log = NULL;
            endif;
            $msg->logMessage('[DEBUG]',"Recorded price from database is: $price_log/$price_foil_log/$price_etched_log");
            //Populate JSON data
            $obj = new PriceManager($db,$logfile,$useremail);
            $scryfallresult = $obj->scryfall($id);
            $msg->logMessage('[DEBUG]',"Scryfall run, returned action '{$scryfallresult["action"]}'");
            $tcg_buy_uri = $scryfallresult["tcg_uri"];
            if(isset($row['layout']) AND $row['layout'] === "normal"):
                $scryfallimg = $row['image_uri'];
            else:
                $scryfallimg = null;
            endif;

            $msg->logMessage('[DEBUG]',"Scryfall image location called by $useremail: $scryfallimg");
            $imgname = $cardid.".jpg";
            $imgname_2 = $cardid."_b.jpg";
            $msg->logMessage('[DEBUG]',"Call for getImage by $useremail with $setcode,$id,$ImgLocation, {$row['layout']}");
            $imageManager = new ImageManager($db, $logfile, $serveremail, $adminemail);
            $imagefunction = $imageManager->getImage($setcode,$row['cs_id'],$ImgLocation,$row['layout'],$two_card_detail_sections);
            $msg->logMessage('[DEBUG]',"getImage result: {$imagefunction['front']} / {$imagefunction['back']}");
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
                $myqty = $row['normal'];
            endif;
            if (empty($row['foil'])):
                $myfoil = 0;
            else:
                $myfoil = $row['foil'];
            endif;
            if (empty($row['etched'])):
                $myetch = 0;
            else:
                $myetch = $row['etched'];
            endif;
            if (empty($row['notes'])):
                $oldnotes = '';
            else:
                $oldnotes = $row['notes'];
            endif;

            if (isset($_POST['update']) && isset($_POST['notes'])) :  
                
                //New notes are to be:
                $newnotes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
                if ($newnotes === ''):
                    $newnotes = null;
                endif;
                
                //Get 'Before' notes for comparison
                $sqlbeforeqry = "SELECT id,notes FROM `$mytable` WHERE id = ? LIMIT 1";
                $beforeparams = [$id];
                if($sqlbefore = $db->execute_query($sqlbeforeqry,$beforeparams)):
                    $msg->logMessage('[DEBUG]',"Before SQL query succeeded ");
                else:
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                endif;
                $beforeresult = $sqlbefore->fetch_array(MYSQLI_ASSOC);
                $writerowforlog = "";
                foreach((array)$beforeresult as $key => $value): 
                    if (!is_int($key)):
                        $writerowforlog .= "index: '$key', value: '$value' "; 
                    endif;
                endforeach;
                $msg->logMessage('[DEBUG]',"User $useremail({$_SERVER['REMOTE_ADDR']}) Before values: $writerowforlog");
                
                //Write new notes
                $updatequery = "
                        INSERT INTO `$mytable` (notes,id)
                        VALUES (?,?)
                        ON DUPLICATE KEY UPDATE notes = ? ";
                $updateparams = [$newnotes,$id,$newnotes];
                $msg->logMessage('[NOTICE]',"User $useremail({$_SERVER['REMOTE_ADDR']}) running update query: $updatequery");
                if($sqlupdate = $db->execute_query($updatequery,$updateparams)):
                    $msg->logMessage('[DEBUG]',"SQL update query succeeded");
                else:
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL update failure: " . $db->error, E_USER_ERROR);
                endif;
                
                // Retrieve new record to display
                $sqlafterqry = "SELECT id,notes FROM `$mytable` WHERE id = ? LIMIT 1";
                $afterparams = [$id];
                if($sqlafter = $db->execute_query($sqlafterqry,$afterparams)):
                    $msg->logMessage('[DEBUG]',"After SQL query succeeded ");
                else:
                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                endif;
                $afterresult = $sqlafter->fetch_array(MYSQLI_ASSOC);
                $writerowforlog = "";
                foreach((array)$afterresult as $key => $value): 
                    if (!is_int($key)):
                        $writerowforlog .= "index: '$key', value: '$value' "; 
                    endif;
                endforeach;
                $msg->logMessage('[DEBUG]',"User $useremail({$_SERVER['REMOTE_ADDR']}) After values: $writerowforlog");
                
                //Compare 
                $afternotes = $afterresult['notes'];
                if ($newnotes === $afternotes):
                    $msg->logMessage('[DEBUG]',"User $useremail({$_SERVER['REMOTE_ADDR']}) New notes record matches input"); ?>
                    <div class="msg-new success-new" onclick='CloseMe(this)'><span>Notes updated</span>
                        <br>
                        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                    </div>
                <?php else: 
                    $msg->logMessage('[DEBUG]',"User $useremail({$_SERVER['REMOTE_ADDR']}) New notes record does not match input"); ?>?>
                    <div class="msg-new error-new" onclick='CloseMe(this)'><span>Update failed</span>
                        <br>
                        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                    </div>
                <?php endif;
            else:
                $afternotes = '';
                $newnotes = '';
            endif; 
            
            //Process image change if it's been called by an admin.
            if (isset($_POST['import']) AND $admin == 1):
                $msg->logMessage('[NOTICE]',"Image upload called by $useremail");
                if (is_uploaded_file($_FILES['filename']['tmp_name'])):
                    $handle = fopen($_FILES['filename']['tmp_name'], "r");
                    $info = getimagesize($_FILES['filename']['tmp_name']);
                    if (($info === FALSE) OR ($info[2] !== IMAGETYPE_JPEG)):
                        $msg->logMessage('[NOTICE]',"Image upload failed - not an image or not a JPG"); ?>
                        <div class="msg-new error-new" onclick='CloseMe(this)'><span>Not a JPG image</span>
                            <br>
                            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                        </div> <?php
                    else:
                        $upload_name = $ImgLocation.strtolower($setcode)."/".$imgname;
                        if(!move_uploaded_file( $_FILES['filename']['tmp_name'], $upload_name)): ?>
                        <div class="msg-new error-new" onclick='CloseMe(this)'><span>Image write failed</span>
                            <br>
                            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                        </div> <?php
                            $msg->logMessage('[ERROR]',"Image upload for $cardid by $useremail failed");
                        else:
                            //Image upload successful. Set variable to load card page 'fresh' at completion (see end of script)
                            $ctrlf5 = 1;
                            $msg->logMessage('[NOTICE]',"Image upload for $cardid by $useremail ok");
                        endif;
                    endif;
                endif;
            endif;
            if (isset($refreshimage) AND $refreshimage === 'REFRESH'):
                $msg->logMessage('[NOTICE]',"Image refresh called for $cardid by $useremail");
                $obj = new ImageManager($db, $logfile, $serveremail, $adminemail);
                $obj->refreshImage($cardid);
                echo "<meta http-equiv='refresh' content='0;url=carddetail.php?id=$cardid'>";
                exit;
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
            $myetch = (isset($myetch)) ? htmlentities($myetch,ENT_QUOTES,"UTF-8") : '';
            
            //Set card types
            if(isset($row['finishes'])):
                $finishes = json_decode($row['finishes'], TRUE);
                $cardtypes = cardtypes($finishes);
            else:
                $finishes = null;
                $cardtypes = 'none';
            endif;
            $msg->logMessage('[DEBUG]',"Current card: {$row['cs_id']} is $cardtypes");
            ?>
                <div id="carddetailheader">
                    <table>
                        <tr>
                            <td class="h2pad" id='nameheading'>
                                <?php 
                                    if(isset($row['flavor_name']) AND $row['flavor_name'] !== ''):
                                        echo "{$row['flavor_name']} <i>({$row['name']})</i>";
                                    elseif ($card_lang === 'ph'):
                                        echo $row['name'];
                                    elseif ($row['printed_name'] != '' AND $row['printed_name'] != $row['name']):
                                        echo "{$row['printed_name']} <i>({$row['name']})</i>";
                                    else:
                                        echo $row['name'];
                                    endif;
                                ?>
                            </td>
                            <td id="carddetailset">
                                <?php
                                if ($card_primary === 1):
                                    echo "<a href='index.php?complex=yes&amp;sortBy=setdown&amp;set%5B%5D=$setcode'>$setname</a>&nbsp;";
                                else:
                                    echo "<a href='index.php?complex=yes&amp;sortBy=setdown&amp;set%5B%5D=$setcode&amp;lang=$card_lang'>$setname ($card_lang_uc)</a>&nbsp;";
                                endif; ?>
                            </td>
                            <td id="carddetaillogo">
                                <?php 
                                echo "<img style='height: 25px' alt='image' src=images/".$colour."_s.png>"; ?>
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
                                else:
                                    echo "&nbsp;";
                                endif;
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div id="minicarddetailheader"> <?php
                    echo "<h2 class = 'h2pad'>";
                    if(isset($row['flavor_name']) AND $row['flavor_name'] !== ''):
                        echo "{$row['flavor_name']} <i>({$row['name']})</i>";
                    elseif ($row['printed_name'] != '' AND $row['printed_name'] != $row['name']):
                        echo "{$row['printed_name']} <i>({$row['name']})</i>";
                    else:
                        echo $row['name'];
                    endif;
                    echo "</h2>";
                    if ($card_primary === 1):
                        echo "<a href='index.php?complex=yes&amp;sortBy=setdown&amp;set%5B%5D=$setcode'>$setname</a>&nbsp;";
                    else:
                        echo "<a href='index.php?complex=yes&amp;sortBy=setdown&amp;set%5B%5D=$setcode&amp;lang=$card_lang'>$setname ($card_lang_uc)</a>";
                    endif; ?>
                </div> 
                <div id="carddetailmain">
                    <div id="carddetailimage"><?php 
                        if($row['layout'] === 'flip'): ?>
                            <div style="cursor: pointer;" class='fliprotate' onClick="rotateImg()">
                                <span class='material-symbols-outlined refresh'>refresh</span>
                            </div> <?php 
                        endif; 
                        $img_id = 'cardimg';
                        if (in_array($row['layout'],$two_card_detail_sections)):
                            echo "<div style='cursor: pointer;' class='flipbuttondetail' onclick=swapImage(\"{$img_id}\",\"{$row['cs_id']}\",\"{$imageurl}\",\"{$imagebackurl}\")><span class='material-symbols-outlined refresh'>refresh</span></div>";
                        endif; 
                        // Find the prev number card's ID
                        $msg->logMessage('[DEBUG]',"Finding previous and next cards");
                        // Get the current card's language and primary_card status
                        $query = "SELECT id FROM cards_scry
                                    WHERE setcode = ? 
                                    AND lang = ? 
                                    AND primary_card = ?
                                    ORDER BY number ASC, release_date ASC, COALESCE(flavor_name, name) ASC, id ASC";
                        $stmt = $db->prepare($query);
                        $stmt->bind_param('ssi', $row['cs_setcode'], $card_lang, $card_primary);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $results = $result->fetch_all(MYSQLI_ASSOC);
                        $currentCardIndex = array_search($row['cs_id'], array_column($results, 'id'));
                        $msg->logMessage('[DEBUG]',"Current card is index number $currentCardIndex in setcode {$row['cs_setcode']}");
                        if ($currentCardIndex !== false) :
                            $prevCardIndex = $currentCardIndex - 1;
                            if (isset($results[$prevCardIndex])) :
                                // Retrieve the next card details
                                $prevCard = $results[$prevCardIndex];
                                $prevcardid = $prevCard['id'];
                                $msg->logMessage('[DEBUG]',"Previous card is $prevcardid");
                            else :
                                $prevcardid = '';
                            endif;
                            $nextCardIndex = $currentCardIndex + 1;
                            if (isset($results[$nextCardIndex])) :
                                // Retrieve the next card details
                                $nextCard = $results[$nextCardIndex];
                                $nextcardid = $nextCard['id'];
                                $msg->logMessage('[DEBUG]',"Next card is $nextcardid");
                            else :
                                $nextcardid = '';
                            endif; 
                        else:
                            $prevcardid = '';
                            $nextcardid = '';
                        endif;?>
                        <table>
                            <tr> 
                                <td colspan="6">
                                    <?php 
                                    $lookupid = htmlentities($row['cs_id'],ENT_QUOTES,"UTF-8");
                                    //If page is being loaded by admin, don't cache the main image
                                    if(($admin == 1) AND ($imageurl !== '/cardimg/back.jpg')):
                                        $msg->logMessage('[DEBUG]',"Admin loading, don't cache image");
                                        $imgmodtime = filemtime($ImgLocation.strtolower($setcode)."/".$imgname);
                                        $imagelocation = $imageurl.'?='.$imgmodtime;
                                    else:
                                        $imagelocation = $imageurl;
                                    endif;
                                    $msg->logMessage('[DEBUG]',"Image location is ".$imagelocation);
                                    // Set classes for hover image
                                    if(in_array($row['layout'],$image90rotate) OR in_array($row['f1_type'],$image90rotate)):
                                        $hoverclass = 'imgfloat splitfloat';
                                    else:
                                        $hoverclass = 'imgfloat';
                                    endif; ?>
                                        <div class='<?php echo $hoverclass; ?>' id='image-<?php echo $row['cs_id'];?>'>
                                            <img alt='<?php echo $imagelocation;?>' src='<?php echo $imagelocation;?>'>
                                        </div> <?php
                                    if(isset($row['multiverse'])):
                                        $multiverse_id = $row['multiverse'];
                                        echo "<a href='https://gatherer.wizards.com/Pages/Card/Details.aspx?multiverseid=".$multiverse_id."' target='_blank'><img alt='$lookupid' id='cardimg' class='mainimg' src=$imagelocation></a>"; 
                                    elseif(isset($row['scryfall_uri'])):
                                        echo "<a href='".$row['scryfall_uri']."' target='_blank'><img alt='$lookupid' id='cardimg' class='mainimg' src=$imagelocation></a>"; 
                                    else:
                                        echo "<a href='https://gatherer.wizards.com/' target='_blank'><img alt='$lookupid' id='cardimg' class='mainimg' src=$imagelocation></a>"; 
                                    endif; ?>
                                </td>
                            </tr> <?php
                            if (!empty($prevcardid) && !empty($nextcardid)): ?>
                                <script>
                                    document.addEventListener('keydown', function(event) {
                                        if (event.key === 'ArrowLeft') {
                                            moveLeft();
                                        } else if (event.key === 'ArrowRight') {
                                            moveRight();
                                        }
                                    });

                                    function moveLeft() {
                                        document.getElementById('prev_card').submit();
                                    }

                                    function moveRight() {
                                        document.getElementById('next_card').submit();
                                    }
                                </script>
                                <tr>
                                    <td colspan="3" class="previousbutton" style="cursor: pointer;"
                                        onclick="document.getElementById('prev_card').submit();">
                                        <?php if (!empty($prevcardid)): 
                                            $msg->logMessage('[DEBUG]',"Previous card ('$prevcardid')");?>
                                            <form action="?" method="get" id="prev_card">
                                                <?php echo "<input type='hidden' name='id' value=" . $prevcardid . ">"; ?>
                                                <label style="cursor: pointer;">
                                                    <span
                                                        onclick="document.getElementById('prev_card').submit();"
                                                        title="Previous card in set"
                                                        onmouseover=""
                                                        style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                        class='material-symbols-outlined'>
                                                        navigate_before
                                                    </span>
                                                </label>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td colspan="3" class="nextbutton" style="cursor: pointer;"
                                        onclick="document.getElementById('next_card').submit();">
                                        <?php if (!empty($nextcardid)): 
                                            $msg->logMessage('[DEBUG]',"Next card ('$nextcardid')");?>
                                            <form action="?" method="get" id="next_card">
                                                <?php echo "<input type='hidden' name='id' value=" . $nextcardid . ">"; ?>
                                                <label style="cursor: pointer;">
                                                    <span
                                                        onclick="document.getElementById('next_card').submit();"
                                                        title="Next card in set"
                                                        onmouseover=""
                                                        style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                        class='material-symbols-outlined'>
                                                        navigate_next
                                                    </span>
                                                </label>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr> <?php 
                            elseif (!empty($nextcardid)): ?>
                                <script>
                                    document.addEventListener('keydown', function(event) {
                                        if (event.key === 'ArrowRight') {
                                            moveRight();
                                        }
                                    });

                                    function moveRight() {
                                        document.getElementById('next_card').submit();
                                    }
                                </script>
                                <?php
                                $msg->logMessage('[DEBUG]',"Next card ('$nextcardid')");
                                ?>
                                <tr>
                                    <td colspan="3" class="previousbutton" style="cursor: pointer;">&nbsp;</td>
                                    <td colspan="3" class="nextbutton" style="cursor: pointer;"
                                        onclick="document.getElementById('next_card').submit();">
                                        <?php if (!empty($nextcardid)): ?>
                                            <form action="?" method="get" id="next_card">
                                                <?php echo "<input type='hidden' name='id' value=" . $nextcardid . ">"; ?>
                                                <label style="cursor: pointer;">
                                                    <span
                                                        onclick="document.getElementById('next_card').submit();"
                                                        title="Next card in set"
                                                        onmouseover=""
                                                        style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                        class='material-symbols-outlined'>
                                                        navigate_next
                                                    </span>
                                                </label>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr> <?php 
                            elseif (!empty($prevcardid)): ?>
                                <script>
                                    document.addEventListener('keydown', function(event) {
                                        if (event.key === 'ArrowLeft') {
                                            moveLeft();
                                        }
                                    });

                                    function moveLeft() {
                                        document.getElementById('prev_card').submit();
                                    }
                                </script>
                                <?php
                                $msg->logMessage('[DEBUG]',"Previous card ('$prevcardid')");
                                ?>
                                <tr>
                                    <td colspan="3" class="previousbutton" style="cursor: pointer;"
                                        onclick="document.getElementById('prev_card').submit();">
                                        <?php if (!empty($prevcardid)): ?>
                                            <form action="?" method="get" id="prev_card">
                                                <?php echo "<input type='hidden' name='id' value=" . $prevcardid . ">"; ?>
                                                <label style="cursor: pointer;">
                                                    <span
                                                        onclick="document.getElementById('prev_card').submit();"
                                                        title="Previous card in set"
                                                        onmouseover=""
                                                        style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                        class='material-symbols-outlined'>
                                                        navigate_before
                                                    </span>
                                                </label>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td colspan="3" class="nextbutton" style="cursor: pointer;">&nbsp;</td>
                                </tr><?php 
                            endif;

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
                                                            <span
                                                                title="New image"
                                                                onmouseover=""
                                                                style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                                class='material-symbols-outlined card_detail'>
                                                                image
                                                            </span>
                                                        </label>
                                                    </td>
                                                    <td class="imgreplace">
                                                        <button class='importlabel' style="cursor: pointer;" id='importsubmit' type='submit' name='import' value='REPLACE' disabled>
                                                            <span
                                                                title="Replace image"
                                                                onmouseover=""
                                                                style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                                class='material-symbols-outlined card_detail'>
                                                                done
                                                            </span>
                                                        </button>
                                                    </td>
                                                    <td class="imgreplace">
                                                        <button class='importlabel' style="cursor: pointer;" id='refreshsubmit' type='submit' name='refreshimage' value='REFRESH'>
                                                            <span
                                                                title="Refresh image"
                                                                onmouseover=""
                                                                style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                                class='material-symbols-outlined card_detail'>
                                                                refresh
                                                            </span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </td>
                                </tr> <?php
                            //else just show control to refresh the image(s) for the card
                            else: ?>
                                <tr>
                                    <td colspan='4'>
                                        <form id="imgreplace" action = "?" method = "GET" enctype = "multipart/form-data">
                                            <input type='hidden' name='setabbrv' value="<?php echo $row['cs_setcode']; ?>">
                                            <input type='hidden' name='id' value="<?php echo $row[0]; ?>">
                                            <input type='hidden' name='number' value="<?php echo $row['number']; ?>">
                                            <table>
                                                <tr>
                                                    <td class="imgreplace">
                                                        <button class='importlabel' style="cursor: pointer;" id='refreshsubmit' type='submit' name='refreshimage' value='REFRESH'>
                                                            <span
                                                                title="Refresh image"
                                                                onmouseover=""
                                                                style="cursor: pointer; display:block; text-align:center; margin:0 auto;"
                                                                class='material-symbols-outlined'>
                                                                refresh
                                                            </span>
                                                        </button>
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
                        echo "<h3 class='shallowh3'>Details</h3>";
                        
                        if(isset($admin) AND $admin == 1):
                            echo "<a href='admin/cards.php?cardtoedit=$lookupid'><i><i class='ss ss-$setcode ss-{$row['rarity']} ss-grad ss-2x'></i>&nbsp;$setname ($setcodeupper) no. {$row['number_import']}</i></a><br>";
                        else:
                            echo "<i><i class='ss ss-$setcode ss-{$row['rarity']} ss-grad ss-2x'></i>&nbsp;$setname ($setcodeupper) no. {$row['number_import']}</i><br>";
                        endif;
                        
                        $gametypestring = '';
                        if(str_contains($row['game_types'],'paper')):
                            $gametypestring = "Paper";
                        endif;
                        if($gametypestring !== '' AND substr($gametypestring,-2) !== "; "):
                            $gametypestring .= "; ";
                        endif;
                        if(str_contains($row['game_types'],'arena')):
                            $gametypestring .= "MtG Arena";
                        endif;
                        if($gametypestring !== '' AND substr($gametypestring,-2) !== "; "):
                            $gametypestring .= "; ";
                        endif;
                        if(str_contains($row['game_types'],'mtgo')):
                            $gametypestring .= "MtG Online";
                        endif;
                        if($gametypestring !== '' AND substr($gametypestring,-2) !== "; "):
                            $gametypestring .= "; ";
                        endif;
                        if($gametypestring !== '' AND substr($gametypestring,-2) === "; "):
                            $gametypestring = substr($gametypestring,0,-2);
                        endif;
                        if($gametypestring === ''):
                            $gametypestring = 'None';
                        endif;
                        echo "<b>Game types: </b>$gametypestring<br>";
                        if(isset($full_promo_text) AND $full_promo_text !== ''):
                            echo "<b>Promo type: </b>$full_promo_text<br>";
                        endif;
                        if($row["layout"] !== 'reversible_card' AND $row["layout"] !== 'double_faced_token'): // no details at card level for reversible cards
                            if(isset($row['type']) AND $row['type'] != ''):
                                echo "<b>Type: </b>".$row['type'];
                            endif;
                            if (isset($card_lang) AND $card_lang != '' AND $card_lang != 'en' AND $row['primary_card'] === 1):
                                echo "<br><b>Language: </b>".langreplace($card_lang)." (primary print)";
                            elseif (isset($card_lang) AND $card_lang != '' AND $card_lang != 'en'):
                                echo "<br><b>Language: </b>".langreplace($card_lang);
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
                                $msg->logMessage('[DEBUG]',"Trying to round cmc {$row['cmc']}");
                                $row['cmc'] = round($row['cmc']);
                            endif;
                            if(!in_array($row['layout'],$token_layouts)):
                                echo "<b>Mana value: </b>".$row['cmc'];
                                echo "<br>";
                            endif;
                        endif;
                        if(in_array($row["layout"],$layouts_double)):
                            if(isset($row['f1_flavor_name']) AND $row['f1_flavor_name'] !== ''):
                                echo "<b>Name: </b>{$row['f1_flavor_name']} <i>({$row['f1_name']})</i>";
                            else:
                                echo "<b>Name: </b>".$row['f1_name'];
                            endif;
                            echo "<br>";
                            if($row['layout'] === 'reversible_card'):
                                if(validateTrueDecimal($row['f1_cmc']) === false):
                                    $msg->logMessage('[DEBUG]',"Trying to round f1_cmc {$row['f1_cmc']}");
                                    $row['f1_cmc'] = round($row['f1_cmc']);
                                endif;
                                echo "<b>Mana value: </b>".$row['f1_cmc'];
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
                            if(isset($card_lang) AND $card_lang != '' AND $card_lang != 'en'):
                                echo "<b>Lang: </b>".langreplace($card_lang);
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
                            $msg->logMessage('[DEBUG]',"Getting legalities for $setcode, $cardname, $id");
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
                                                        
                            if($row['legalitycommander'] == 'legal'):
                                $legalitystring .= "Commander";
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
                            if(isset($card_lang) AND $card_lang != '' AND $card_lang != 'en'):
                                echo "<b>Lang: </b>".langreplace($card_lang);
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
                            if(isset($card_lang) AND $card_lang != '' AND $card_lang != 'en'):
                                echo "<b>Lang: </b>".langreplace($card_lang);
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
                        ?>                
                    </div><?php 
                    if(($meld !== 'meld_result') AND ($not_paper !== true ) AND ($cardtypes != 'none' )): ?>
                        <div id="carddetailupdate">
                            <form id="updatenotesform" action="?" method="POST">
                            <h3 class="shallowh3">My collection</h3>
                            <?php
                            $msg->logMessage('[DEBUG]',"Card types: $cardtypes");
                            $cellid = "cell".$id;
                            $cellid_one = $cellid.'_one';
                            $cellid_two = $cellid.'_two';
                            $cellid_three = $cellid.'_three';
                            $cellid_one_flash = $cellid_one;
                            $cellid_two_flash = $cellid_two;
                            $cellid_three_flash = $cellid_three; ?>
                            <table>
                                <tr class='bulksubmitrowsmall'>
                                    <td class='bulksubmittd' id="<?php echo $cellid."td_one"; ?>">
                                        <?php
                                        if($meld === 'meld_result'):
                                            echo "Meld card";
                                        elseif ($not_paper == true):
                                            echo "<i>MtG Arena/Online</i>";
                                        elseif ($cardtypes === 'foilonly'):
                                            $poststring = 'newfoil';
                                            echo "Foil: <input class='bulkinputsmall foil' id='$cellid_one' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$id\",\"$cellid_one\",\"$myfoil\",\"$cellid_one_flash\",\"$poststring\");'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        elseif ($cardtypes === 'etchedonly'):
                                            $poststring = 'newetch';
                                            echo "Etch: <input class='bulkinputsmall etch' id='$cellid_one' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$id\",\"$cellid_one\",\"$myetch\",\"$cellid_one_flash\",\"$poststring\");'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        else:
                                            $poststring = 'newqty';
                                            echo "Normal: <input class='bulkinputsmall normal' id='$cellid_one' type='number' step='1' min='0' name='myqty' value='$myqty' onchange='ajaxUpdate(\"$id\",\"$cellid_one\",\"$myqty\",\"$cellid_one_flash\",\"$poststring\");'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        endif;?>
                                    </td>
                                    <td class='bulksubmittdsmall' id="<?php echo $cellid."td_two"; ?>">
                                        <?php
                                        if($meld === 'meld_result'):
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'foilonly'):
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'normalonly'):
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'etchedonly'):
                                            echo "&nbsp;";
                                        elseif ($cardtypes === 'normaletched'):
                                            $poststring = 'newetch';
                                            echo "Etch: <input class='bulkinputsmall etch' id='$cellid_two' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$id\",\"$cellid_two\",\"$myetch\",\"$cellid_two_flash\",\"$poststring\");'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        else:
                                            $poststring = 'newfoil';
                                            echo "Foil: <input class='bulkinputsmall foil' id='$cellid_two' type='number' step='1' min='0' name='myfoil' value='$myfoil' onchange='ajaxUpdate(\"$id\",\"$cellid_two\",\"$myfoil\",\"$cellid_two_flash\",\"$poststring\");'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        endif;?>
                                    </td>
                                    <td class='bulksubmittdsmall' id="<?php echo $cellid."td_three"; ?>">
                                        <?php
                                        if ($cardtypes === 'normalfoiletched'):
                                            $poststring = 'newetch';
                                            echo "Etch: <input class='bulkinputsmall etch' id='$cellid_three' type='number' step='1' min='0' name='myetch' value='$myetch' onchange='ajaxUpdate(\"$id\",\"$cellid_three\",\"$myetch\",\"$cellid_three_flash\",\"$poststring\");'>";
                                            echo "<input class='card' type='hidden' name='card' value='$id'>";
                                        else:
                                            echo "&nbsp;";
                                        endif;?>
                                    </td>
                                </tr>
                            </table>
                            <table style="margin-top:10px"><?php
                                if ($afternotes === ''):
                                    $displaynotes = $oldnotes;
                                else:
                                    $displaynotes = $afternotes;
                                endif;
                                echo "<tr><td><textarea class='textinput' id='cardnotes' name='notes' rows='2' cols='40' placeholder='My notes'>$displaynotes</textarea></td></tr>"; ?>
                            </table> <?php
                            echo "<input type='hidden' name='id' value=".$lookupid.">";
                            echo "<input type='hidden' name='update' value='yes'>";?>
                            <input class='inline_button stdwidthbutton updatebutton' style="cursor: pointer;" type="hidden" id="hiddenSubmitValue" value="UPDATE NOTES">
                            <button class='inline_button save_icon' type="button" onclick="submitForm()" title="Save" disabled><span class="material-symbols-outlined">save</span></button>
                            </form>
                            <script>
                                function submitForm() {
                                    document.getElementById('hiddenSubmitValue').value = 'UPDATE NOTES';
                                    document.getElementById('updatenotesform').submit();
                                }
                            </script>
                            <script>
                                $(document).ready(function() {
                                    let id = '<?php echo $id; ?>';
                                    let cardtypes = '<?php echo $cardtypes; ?>';
                                    let poststring = '<?php echo $poststring; ?>';
                                    let validkeysArray = [];

                                    if (cardtypes === 'normalonly') {
                                            validkeysArray.push("n");
                                            cellidnormal = document.getElementById("<?php echo $cellid_one; ?>");
                                            newqty = <?php echo $myqty; ?>;
                                    } else if (cardtypes === 'foilonly') {
                                            validkeysArray.push("f");
                                            cellidfoil = document.getElementById("<?php echo $cellid_one; ?>");
                                            newfoil = <?php echo $myfoil; ?>;
                                    } else if (cardtypes === 'etchedonly') {
                                            validkeysArray.push("e");
                                            cellidetch = document.getElementById("<?php echo $cellid_one; ?>");
                                            newetch = <?php echo $myetch; ?>;
                                    } else if (cardtypes === 'normaletched') {
                                            validkeysArray.push("n","e");
                                            cellidnormal = document.getElementById("<?php echo $cellid_one; ?>");
                                            newqty = <?php echo $myqty; ?>;
                                            cellidetch = document.getElementById("<?php echo $cellid_two; ?>");
                                            newetch = <?php echo $myetch; ?>;
                                    } else if (cardtypes === 'normalfoiletched') {
                                            validkeysArray.push("n","f","e");
                                            cellidnormal = document.getElementById("<?php echo $cellid_one; ?>");
                                            newqty = <?php echo $myqty; ?>;
                                            cellidfoil = document.getElementById("<?php echo $cellid_two; ?>");
                                            newfoil = <?php echo $myfoil; ?>;
                                            cellidetch = document.getElementById("<?php echo $cellid_three; ?>");
                                            newetch = <?php echo $myetch; ?>;
                                    } else  {
                                            validkeysArray.push("n","f");
                                            cellidnormal = document.getElementById("<?php echo $cellid_one; ?>");
                                            newqty = <?php echo $myqty; ?>;
                                            cellidfoil = document.getElementById("<?php echo $cellid_two; ?>");
                                            newfoil = <?php echo $myfoil; ?>;
                                    }

                                    let operation = '';
                                    let ajaxTrigger = false;
                                    $(document).on('keydown', function(f) {
                                        const pressedKey = f.key;
                                        if (pressedKey === '+') {
                                            operation = 'add';
                                        } else if (pressedKey === '-') {
                                            operation = 'subtract';
                                        } else if (pressedKey === 'Escape' && (operation === 'add' || operation === 'subtract')) {
                                            operation = 'None';
                                        } else if (validkeysArray.includes(pressedKey)) {
                                            if (operation === 'add' && pressedKey === 'n') {
                                                newqty = parseInt(newqty, 10) + 1;
                                                ajaxTrigger = true;
                                            } else if (operation === 'add' && pressedKey === 'f') {
                                                newfoil = parseInt(newfoil, 10) + 1;
                                                ajaxTrigger = true;
                                            } else if (operation === 'add' && pressedKey === 'e') {
                                                newetch = parseInt(newetch, 10) + 1;
                                                ajaxTrigger = true;
                                            } else if (operation === 'subtract' && pressedKey === 'n') {
                                                newqty = Math.max(0, parseInt(newqty, 10) - 1);
                                                ajaxTrigger = true;
                                            } else if (operation === 'subtract' && pressedKey === 'f') {
                                                newfoil = Math.max(0, parseInt(newfoil, 10) - 1);
                                                ajaxTrigger = true;
                                            } else if (operation === 'subtract' && pressedKey === 'e') {
                                                newetch = Math.max(0, parseInt(newetch, 10) - 1);
                                                ajaxTrigger = true;
                                            }
                                        }    
                                    });
                                    $(document).on('keyup', function(e) {
                                        const pressedKey = e.key;
                                        if (validkeysArray.includes(pressedKey)) {
                                            e.preventDefault();
                                            if (ajaxTrigger === true) {
                                                if (pressedKey === 'n') {
                                                    console.log('Normal', newqty);
                                                    cellidnormal.value = newqty;
                                                } else if (pressedKey === 'f') {
                                                    console.log('Foil', newfoil);
                                                    cellidfoil.value = newfoil;
                                                } else if (pressedKey === 'e') {
                                                    console.log('Etch', newetch);
                                                    cellidetch.value = newetch;
                                                }
                                                ajaxTrigger = false;
                                                var event = new Event('change');
                                                if (pressedKey === 'n') cellidnormal.dispatchEvent(event);
                                                else if (pressedKey === 'f') cellidfoil.dispatchEvent(event);
                                                else if (pressedKey === 'e') cellidetch.dispatchEvent(event);
                                            }
                                        }    
                                    });
                                });
                            </script>
                            <hr class='hr324'>
                            <?php 

                            // Price section
                            if((isset($scryfallresult["price"]) AND $scryfallresult["price"] !== "" AND $scryfallresult["price"] != 0.00 AND $scryfallresult["price"] !== NULL AND str_contains($cardtypes,'normal'))):
                                $msg->logMessage('[DEBUG]',"Using Scryfall normal price");
                                $normalprice = number_format($scryfallresult['price'],2); 
                                $localnormal = number_format(($scryfallresult["price"] * $rate), 2, '.', ',');
                            elseif((isset($row["price"]) AND $row["price"] !== "" AND $row["price"] != 0.00  AND str_contains($cardtypes,'normal'))):
                                $msg->logMessage('[DEBUG]',"Using database normal price");
                                $normalprice = number_format($row['price'],2); 
                                $localnormal = number_format(($row["price"] * $rate), 2, '.', ',');
                            else:
                                $msg->logMessage('[DEBUG]',"No normal price");
                                $normalprice = FALSE;
                            endif;      
                            if((isset($scryfallresult["price_foil"]) AND $scryfallresult["price_foil"] !== "" AND $scryfallresult["price_foil"] != 0.00 AND $scryfallresult["price_foil"] !== NULL AND str_contains($cardtypes,'foil'))):
                                $msg->logMessage('[DEBUG]',"Using Scryfall foil price");
                                $foilprice = number_format($scryfallresult['price_foil'],2); ;
                                $localfoil = number_format(($scryfallresult["price_foil"] * $rate), 2, '.', ',');
                            elseif((isset($row["price_foil"]) AND $row["price_foil"] !== "" AND $row["price_foil"] != 0.00  AND str_contains($cardtypes,'foil'))):
                                $msg->logMessage('[DEBUG]',"Using database foil price");
                                $foilprice = number_format($row['price_foil'],2);
                                $localfoil = number_format(($row["price_foil"] * $rate), 2, '.', ',');
                            else:
                                $msg->logMessage('[DEBUG]',"No foil price");
                                $foilprice = FALSE;
                            endif;
                            if((isset($scryfallresult["price_etched"]) AND $scryfallresult["price_etched"] !== "" AND $scryfallresult["price_etched"] != 0.00 AND $scryfallresult["price_etched"] !== NULL AND str_contains($cardtypes,'etch'))):
                                $msg->logMessage('[DEBUG]',"Using Scryfall etched price");
                                $etchprice = number_format($scryfallresult['price_etched'],2);
                                $localetched = number_format(($scryfallresult["price_etched"] * $rate), 2, '.', ',');
                            elseif((isset($row["price_etched"]) AND $row["price_etched"] !== "" AND $row["price_etched"] != 0.00  AND str_contains($cardtypes,'etch'))):
                                $msg->logMessage('[DEBUG]',"Using database etched price");
                                $etchprice = number_format($row['price_etched'],2);
                                $localetched = number_format(($row["price_etched"] * $rate), 2, '.', ',');
                            else:
                                $msg->logMessage('[DEBUG]',"No etched price");
                                $etchprice = FALSE;
                            endif;
                            
                            if ($normalprice == FALSE AND $foilprice == FALSE AND $etchprice == FALSE): ?>
                                <table id='tcgplayer' width="100%">
                                    <tr>
                                        <td colspan="2" class="buycellleft">
                                            No prices available <br>
                                        </td>
                                    </tr>
                                </table> <?php 
                            else: ?>                          
                                <table id='tcgplayer' width="100%">
                                    <tr>
                                        <td>
                                            <b>Price</b>
                                        </td>
                                        <td>
                                            <b>USD <?php if($fx === TRUE):echo "($targetCurrency)";endif; ?></b>
                                        </td>
                                    </tr> <?php 

                                    if($normalprice !== FALSE): ?>
                                    <tr>
                                        <td class="buycellleft">
                                            Normal
                                        </td>
                                        <td class="buycell mid">
                                            <?= ($fx === TRUE) ? $normalprice . " ($localnormal)" : $normalprice; ?>
                                        </td>
                                    </tr> <?php
                                    endif;

                                    if($foilprice !== FALSE): ?>
                                    <tr>
                                        <td class="buycellleft">
                                            Foil
                                        </td>
                                        <td class="buycell mid">
                                            <?= ($fx === TRUE) ? $foilprice . " ($localfoil)" : $foilprice; ?>
                                        </td>
                                    </tr> <?php 
                                    endif;

                                    if($etchprice !== FALSE):?>
                                    <tr>
                                        <td class="buycellleft">
                                            Etched
                                        </td>
                                        <td class="buycell mid">
                                            <?= ($fx === TRUE) ? $etchprice . " ($localetched)" : $etchprice; ?>
                                        </td>
                                    </tr> <?php
                                    endif; ?>                          
                                </table> <?php
                            endif;
                            if(isset($tcg_buy_uri) AND $tcg_buy_uri !== ""):
                                $tcgdirectlink = $tcg_buy_uri;
                            else:
                                $tcgdirectlink = null;
                            endif; ?>
                            
                            <hr class='hr324'>
                            <b>Printings & links</b>
                            <table width="100%">
                                <tr>
                                    <td class="buycellleft">
                                        <?php echo "<a href='index.php?name=".$row['name']."&amp;exact=yes'>Primary language </a>"; ?>
                                    </td>
                                    <td class="buycellleft">
                                        <?php echo "<a href='index.php?name=".$row['name']."&amp;allprintings=yes'>All languages </a>"; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="buycellleft">
                                        <?php
                                        if(isset($row['scryfall_uri']) AND $row['scryfall_uri'] !== ""):
                                            echo "<a href='".$row['scryfall_uri']."' target='_blank'>Scryfall</a>";
                                        else:
                                            $namehtml = str_replace("//","",$namehtml);
                                            $namehtml = str_replace("  ","%20",$namehtml);
                                            $namehtml = str_replace(" ","%20",$namehtml);
                                            echo "<a href='https://magiccards.info/query?q=".$namehtml."' target='_blank'>Search Scryfall</a>";
                                        endif;?>
                                    </td>
                                    <td class="buycellleft"> <?php
                                        if(isset($tcgdirectlink) AND $tcgdirectlink !== null):
                                            echo "<a href='".$tcgdirectlink."' target='_blank'>TCGPlayer</a>"; 
                                        endif; ?>
                                    </td>
                                </tr> 
                            </table>
                            
                            
                            <?php

                            // Others with this card section 
                            $usergrprowqry = "SELECT grpinout,groupid FROM users WHERE usernumber = ? LIMIT 1";
                            $usergrprowparams = [$user];
                            if($sqlusergrp = $db->execute_query($usergrprowqry,$usergrprowparams)):
                                $msg->logMessage('[DEBUG]',"SQL query succeeded");
                            else:
                                trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                            endif;
                            $usergrprow = $sqlusergrp->fetch_array(MYSQLI_ASSOC);
                            if ($usergrprow['grpinout'] == 1):
                                $usergroup = $usergrprow['groupid'];
                                $msg->logMessage('[DEBUG]',"Groups are active, group ID = $usergroup");
                                $grpquery = "SELECT usernumber, username, status, groupid, groupname, owner FROM users LEFT JOIN `groups` ON users.groupid = groups.groupnumber WHERE groupid = ? AND usernumber <> ?";
                                $grpparams = [$usergroup,$_SESSION["user"]];
                                if($sqluserqry = $db->execute_query($grpquery,$grpparams)):
                                    $msg->logMessage('[DEBUG]',"SQL query succeeded");
                                else:
                                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                endif;
                                $others = 0;
                                $q = 0;
                                $first = TRUE;
                                
                                while ($userrow = $sqluserqry->fetch_array(MYSQLI_ASSOC)):
                                    if($userrow['status'] !== 'disabled'):
                                        $msg->logMessage('[DEBUG]',"Scanning ".$userrow['username']."'s cards");
                                        $grpuser[$q]['id'] = $userrow['usernumber'];
                                        $grpuser[$q]['name'] = $userrow['username'];
                                        $q = $q + 1;
                                        $usertable = $userrow['usernumber'].'collection';
                                        $sqlqry = "SELECT id,normal,foil,etched,notes,topvalue FROM `$usertable` WHERE id = ?";
                                        $sqlparams = [$id];
                                        if($sqlqtyqry = $db->execute_query($sqlqry,$sqlparams)):
                                            $msg->logMessage('[DEBUG]',"SQL query succeeded for {$userrow['username']}, $row[0]");
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
                                                if (empty($userqtyresult['etched'])):
                                                    $userqtyresult['etched'] = 0;
                                                endif;
                                                $others = 1;
                                                $userrow['username'] = htmlentities($userrow['username'],ENT_QUOTES,"UTF-8");
                                                $userqtyresult['normal'] = htmlentities($userqtyresult['normal'],ENT_QUOTES,"UTF-8");
                                                $userqtyresult['foil'] = htmlentities($userqtyresult['foil'],ENT_QUOTES,"UTF-8");
                                                $userqtyresult['etched'] = htmlentities($userqtyresult['etched'],ENT_QUOTES,"UTF-8");
                                                if($first === TRUE):
                                                    echo "<hr class='hr324'>";
                                                    echo "<b>Others with this card</b><br>";
                                                    $first = FALSE;
                                                endif;
                                                echo ucfirst($userrow['username']),": &nbsp;<i>Normal:</i> {$userqtyresult['normal']} &nbsp;&nbsp;<i>Foil:</i> {$userqtyresult['foil']} &nbsp;&nbsp;<i>Etch:</i> {$userqtyresult['etched']}<br>";
                                            endif;
                                        endif;
                                    endif;
                                endwhile;
                                if ($others == 0):
                                    // echo "N/A<br>";
                                endif;
                            else:
                                // echo "<b>Others with this card</b><br>";
                                // echo "Opt in for groups in Profile";
                            endif;
                            ?>
                            <hr class='hr324'>
                            <?php
                            $msg->logMessage('[NOTICE]',"Decks enabled: $decks_on");
                                if(in_array($row['layout'],$token_layouts)):
                                    $decks_on = 0;
                                endif;
                                if($decks_on === 1):
                                    echo "<div id='deckadd'>";
                                    if (isset($decktoaddto)):
                                        $msg->logMessage('[NOTICE]',"Received request to add $deckqty x card $cardid to deck: '$decktoaddto'; Newdeck: '$newdeckname'");
                                        // If the deck is new, is the new name unique? If yes, create it.
                                        if($decktoaddto == "newdeck"):
                                            $msg->logMessage('[NOTICE]',"Calling Deckmanager->addDeck: '$user/$newdeckname'");
                                            $obj = new DeckManager($db, $logfile);
                                            $decksuccess = $obj->addDeck($user,$newdeckname); //returns array with success flag, and if success flag is 1, the deck number (otherwise NULL)
                                            if($decksuccess['flag'] === 1):
                                                $decktoaddto = $decksuccess['decknumber'];
                                            else:
                                                $decktoaddto = NULL;
                                            endif;
                                        else:
                                            // Check that the proposed deck exists and belongs to owner.
                                            $obj = new DeckManager($db, $logfile);
                                            if($obj->deckOwnerCheck($decktoaddto,$user) == FALSE): ?>
                                                <div class="msg-new error-new" onclick='CloseMe(this)'><span>You don't have that deck</span>
                                                    <br>
                                                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                </div>
                                                <?php
                                                $decksuccess = [
                                                    'decknumber' => NULL,
                                                    'flag' => 10
                                                ];
                                            else:
                                                $decksuccess = [
                                                    'decknumber' => NULL,
                                                    'flag' => 2
                                                ];
                                            endif;
                                        endif;
                                        // Here we either have successfully created a new deck (1), failed to create (10), or confirmed ownership and existence (2)
                                        $msg->logMessage('[NOTICE]',"Decksuccess code is {$decksuccess['flag']}");
                                        if ($decksuccess['flag'] !== 10):  //I.e. the deck now exists and belongs to the caller
                                            if ($decksuccess['flag'] === 2): //Not a new deck, run card check
                                                $msg->logMessage('[NOTICE]',"Running SQL to see if $cardid is already in deck $decktoaddto");
                                                
                                                $sql = "SELECT cardnumber FROM deckcards WHERE decknumber = ? AND cardnumber = ? AND ((cardqty IS NOT NULL) OR (sideqty IS NOT NULL))";
                                                $params = [$decktoaddto,$cardid];
                                                $resultchk = $db->execute_query($sql,$params);
                                                if($resultchk !== false && $resultchk->num_rows === 1):
                                                    $cardcheckrow = $resultchk->fetch_assoc();
                                                    $msg->logMessage('[NOTICE]',"{$cardcheckrow['cardnumber']} is already in that deck");
                                                    ?>
                                                    <div class="msg-new error-new" onclick='CloseMe(this)'><span>Card already in deck</span>
                                                        <br>
                                                        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                    </div>
                                                    <?php
                                                    $cardchecksuccess = 0;
                                                elseif($resultchk !== false && $resultchk->num_rows === 0):   
                                                    $msg->logMessage('[NOTICE]',"Card is not in the deck, proceeding to write");
                                                    $cardchecksuccess = 1;
                                                else:
                                                    trigger_error("[ERROR]".basename(__FILE__)." ".__LINE__.": SQL failure: " . $db->error, E_USER_ERROR);
                                                endif;
                                            elseif ($decksuccess['flag'] === 1):
                                                $cardchecksuccess = 2;
                                            endif;
                                            //Insert card to deck
                                            if (in_array($cardchecksuccess, [1, 2])):
                                                $deckqty = (int)$deckqty;
                                            
                                                //Call add card function
                                                $obj = new DeckManager($db,$logfile);
                                                $obj->addDeckCard($decktoaddto,$cardid,'main',$deckqty);
                                                
                                                //Check it's added
                                                $sql = "SELECT cardnumber,cardqty FROM deckcards WHERE decknumber = ? AND cardnumber = ? AND cardqty = ? LIMIT 1";
                                                $params = [$decktoaddto,$cardid,$deckqty];
                                                $resultchksql = $db->execute_query($sql,$params);
                                                if($resultchksql !== false && $resultchksql->num_rows === 1):
                                                    $msg->logMessage('[DEBUG]',"SQL select for card succeeded");
                                                    $resultchkins = $resultchksql->fetch_assoc();
                                                    if(($resultchkins['cardnumber'] == $cardid) AND ($resultchkins['cardqty'] == $deckqty)):
                                                        ?>
                                                        <div class="msg-new success-new" onclick='CloseMe(this)'><span>Card added</span>
                                                            <br>
                                                            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                        </div>
                                                        <?php
                                                        $msg->logMessage('[NOTICE]',"Card $cardid added to deck $decktoaddto");
                                                    else:?>
                                                        <div class="msg-new warning-new" onclick='CloseMe(this)'><span>Card in deck, but quantity mismatch</span>
                                                            <br>
                                                            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                        </div>
                                                        <?php 
                                                        $msg->logMessage('[NOTICE]',"Card $cardid in deck $decktoaddto, but quantity mismatch");
                                                    endif;
                                                else:
                                                    ?>
                                                    <div class="msg-new error-new" onclick='CloseMe(this)'><span>Card not added</span>
                                                        <br>
                                                        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                                                    </div>
                                                    <?php 
                                                    $msg->logMessage('[ERROR]',"Card $cardid was not added to deck $decktoaddto");
                                                endif;
                                            endif;
                                        endif;
                                    endif; 
                                    $msg->logMessage('[NOTICE]',"Checking to see if $cardid is in any owned decks");
                                    $obj = new DeckManager($db,$logfile);
                                    $inmydecks = $obj->deckCardCheck($cardid,$user);
                                    echo "<b>Decks</b><br>";
                                    if (!empty($inmydecks)):
                                        foreach ($inmydecks as $decksrow):
                                            if($decksrow['qty'] != ''):
                                                echo "<a href='/deckdetail.php?deck={$decksrow['decknumber']}'>{$decksrow['deckname']}</a> (main x{$decksrow['qty']}) <br>";
                                            else:
                                                echo "<a href='/deckdetail.php?deck={$decksrow['decknumber']}'>{$decksrow['deckname']}</a> (sideboard x{$decksrow['sideqty']}) <br>";
                                            endif;
                                        endforeach;
                                    endif;
                                    $t = 0;
                                    $grpdecks = array();
                                    if(isset($grpuser)):
                                        foreach ($grpuser as $decksgrprow):
                                            $grpuserid = $grpuser[$t]['id'];
                                            $grpusername = ucfirst($grpuser[$t]['name']);
                                            $msg->logMessage('[DEBUG]',"Checking user $grpusername for $cardid");
                                            $obj = new DeckManager($db,$logfile);
                                            $ingrpdecks = $obj->deckCardCheck($cardid,$grpuserid);
                                            $t = $t + 1;
                                            if (!empty($ingrpdecks)):
                                                foreach ($ingrpdecks as $decksgrprow):
                                                    if($decksgrprow['qty'] != ''):
                                                        echo "<i>Group:</i> $grpusername: {$decksgrprow['deckname']} (main x{$decksgrprow['qty']}) <br>";
                                                    else:
                                                        echo "<i>Group:</i> $grpusername: {$decksgrprow['deckname']} (sideboard x{$decksgrprow['sideqty']}) <br>";
                                                    endif;
                                                endforeach;
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>

                                    <!-- Display Add to Deck form -->

                                    <form id="addtodeck" action="<?php echo basename(__FILE__); ?>#deck" method="GET">
                                        <?php echo "<input type='hidden' name='setabbrv' value=".$row['cs_setcode'].">";
                                        echo "<input type='hidden' name='number' value=".$row['number'].">";
                                        echo "<input type='hidden' name='id' value=".$row[0].">"; ?>
                                        <select id='deckselect' name='decktoaddto'>
                                            <option value='none'>Add...</option>
                                            <option value='newdeck'>Add to new deck...</option>
                                            <?php 
                                            
                                            $sql = "SELECT decknumber,deckname FROM decks WHERE owner = ? ORDER BY deckname ASC";
                                            $params = [$user];
                                            $decklistsql = $db->execute_query($sql,$params);
                                            
                                            if($decklistsql !== false):
                                                $msg->logMessage('[DEBUG]',"SQL select for card succeeded");
                                                while ($dlrow = $decklistsql->fetch_assoc()):
                                                    $dlrow['decknumber'] = htmlentities($dlrow['decknumber'],ENT_QUOTES,"UTF-8");
                                                    $dlrow['deckname'] = htmlentities($dlrow['deckname'],ENT_QUOTES,"UTF-8");
                                                    echo "<option value='{$dlrow['decknumber']}'>{$dlrow['deckname']}</option>";
                                                endwhile;
                                            endif;
                                            ?>
                                        </select>
                                        <span id="deckqtyspan" style="display: none">
                                            &nbsp;Qty <input class='textinput' id='deckqty' type='number' min='0' disabled placeholder='N/A' name='deckqty' value="">
                                            <br>
                                        </span>
                                        <span id="newdecknamespan" style="display: none">
                                            <input class='textinput' id='newdeckname' disabled type='text' name='newdeckname' placeholder='N/A' size='19' style="padding-top: 10px;"/>
                                        </span>
                                        <span id="addtodecksubmitspan" style="display: none">
                                            <input class='importlabel' id="addtodeckbutton" disabled type="submit" value="ADD TO DECK" style="margin-top: 10px;">
                                        </span>
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
                        $msg->logMessage('[NOTICE]',"Rulings: {$result->num_rows} ({$row['oracle_id']})");
                        if (($result->num_rows === 0) AND !in_array($row['layout'],$two_card_detail_sections)):
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
                            if (!in_array($row['layout'],$two_card_detail_sections)):
                                echo "<h3 class='shallowh3'>Rulings:</h3> ".$ruling."&nbsp;";
                            endif;
                            echo("</div>");
                        endif;
                    endif; ?>
                </div>
                <!-- Flip card -->
                <?php 
                if (in_array($row['layout'],$two_card_detail_sections)): ?>
                    <div id="carddetailflip">
                        <div id="carddetailflipimg">    
                            <table>
                               <tr> 
                                    <td colspan="2">
                                        <?php 
                                        $lookupid = htmlentities($row['cs_id'],ENT_QUOTES,"UTF-8");
                                        //If page is being loaded by admin, don't cache the main image
                                        if(($admin == 1) AND ($imagebackurl !== '/cardimg/back.jpg')):
                                            $msg->logMessage('[DEBUG]',"Admin loading, don't cache image");
                                            $imgmodtime = filemtime($ImgLocation.strtolower($setcode)."/".$imgname_2);
                                            $imagelocationback = $imagebackurl.'?='.$imgmodtime;
                                        else:
                                            $imagelocationback = $imagebackurl;
                                        endif;
                                        
                                        $msg->logMessage('[DEBUG]',"Image location is ".$imagelocationback);
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
                                $msg->logMessage('[DEBUG]',"Trying to round f2_cmc {$row['f2_cmc']}");
                                $row['f2_cmc'] = round($row['f2_cmc']);
                                echo "<b>Mana value: </b>".$row['f2_cmc'];
                                echo "<br>";
                            elseif(isset($row['f2_cmc']) AND validateTrueDecimal($row['f2_cmc']) === true):
                                echo "<b>Mana value: </b>".$row['f2_cmc'];
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
                            if(isset($card_lang) AND $card_lang != '' AND $card_lang != 'en'):
                                echo "<b>Lang: </b>".langreplace($card_lang);
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
                if($disqus === 1):
                    $msg->logMessage('[DEBUG]',"Disqus enabled");
                    $page_url = strtok(get_full_url(),'?')."?id=".$cardid;
                    if ($tier === 'dev'):
                        $msg->logMessage('[DEBUG]',"Disqus site is '$disqusDev'");
                        $disqus_site = "$disqusDev/embed.js";
                    else:
                        $msg->logMessage('[DEBUG]',"Disqus site is '$disqusProd'");
                        $disqus_site = "$disqusProd/embed.js";
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
                else:
                    $msg->logMessage('[DEBUG]',"Disqus not enabled, skipping");
                endif;
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