<?php

/*
Version:     25.63
Date:        29/12/25
Name:        deckdetail.php
Purpose:     Deck detail page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('includes/sessionname.local.php')) :
    require('includes/sessionname.local.php');
else :
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require('includes/ini.php');                //Initialise and load ini file
require('includes/error_handling.php');
require('includes/functions.php');          //Includes basic functions for non-secure pages
require('includes/secpagesetup.php');       //Setup page variables
require('includes/colour.php');
require_once 'ajax/ajaxdeckfragments_lib.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
forcePasswordChange();                       //Check if user is disabled or needs to change password
$msg = new \MTG\Core\Message($logfile);
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

$uniquecard_ref = [];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc; ?> - deck detail</title>
    <link rel="manifest" href="/manifest.json" />
    <link 
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8'); ?>.css"
    >
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <?php include('includes/googlefonts.php'); ?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script type="text/javascript">
        window.mtgImageCacheName = 'mtg-images-<?php echo $serviceWorkerVersion; ?>';
    </script>
    <script src="/js/asyncImageRefresh.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<?php ?>
</head>

<body class="body">
<?php
include_once("includes/analyticstracking.php");
require('includes/overlays.php');
require('includes/header.php');
if ($tier == 'dev') :
    echo '<div id="deckdetail-width-banner" style="position: fixed; top: 10px; right: 10px; z-index: 100000; '
        . 'padding: 8px 10px; border: 1px solid #3f51b5; border-radius: 4px; background: #fff; '
        . 'color: #000; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); max-width: 90%;">'
        . 'Viewable width: (loading)</div>';
    echo '<script>'
        . 'function updateDeckdetailWidthBanner(){'
        . 'var banner=document.getElementById("deckdetail-width-banner");'
        . 'if(!banner){return;}'
        . 'var width=document.documentElement.clientWidth||window.innerWidth;'
        . 'banner.textContent="Viewable width: "+width+"px";'
        . '}'
        . 'if(document.readyState==="loading"){'
        . 'document.addEventListener("DOMContentLoaded",updateDeckdetailWidthBanner);'
        . '}else{'
        . 'updateDeckdetailWidthBanner();'
        . '}'
        . 'window.addEventListener("load",updateDeckdetailWidthBanner);'
        . 'window.addEventListener("resize",updateDeckdetailWidthBanner);'
        . 'setTimeout(updateDeckdetailWidthBanner,0);'
        . '</script>';
endif;
require('includes/menu.php'); //mobile menu

$redirect = false;

$time = time();

if (isset($_GET["deck"])) :
    $deckNumber = filter_input(INPUT_GET, 'deck', FILTER_SANITIZE_NUMBER_INT);
elseif (isset($_POST["deck"])) :
    $deckNumber     = filter_input(INPUT_POST, 'deck', FILTER_SANITIZE_NUMBER_INT);
else : ?>
    <div id='page'>
    <div class='staticpagecontent'>
    <h3>No decknumber given - returning to your deck list...</h3>
    <meta http-equiv='refresh' content='2;url=decks.php'>
    </div>
    </div> <?php
    require('includes/footer.php');
    exit();
endif;?>
<?php
// Check to see if the called deck belongs to the logged in user.
$msg->logMessage('[NOTICE]', "Checking deck $deckNumber");
$obj = new \MTG\Cards\DeckManager($db, $logfile, $userEmail, $serverEmail, $importLinestoIgnore, $nonPreferredSetCodes);
if ($obj->deckOwnerCheck($deckNumber, $user) == false) : ?>
    <div id='page'>
    <div class='staticpagecontent'>
    <h3>This deck is not yours... returning to your deck page...</h3>
    <meta http-equiv='refresh' content='2;url=decks.php'>
    </div>
    </div> <?php
    require('includes/footer.php');
    exit();
endif;

include 'includes/deckdetail_data.php';

// Next the main DIV section ?>
<?php ?>
<script>
    <?php
    $fragmentRegistry = deckdetailFragmentRegistry();
    $fragmentDefaults = deckdetailDefaultFragments($fragmentRegistry);
    $fragmentTargets = deckdetailFragmentTargets($fragmentRegistry);
    ?>
    window.mtgDeckDetailConfig = {
        deckNumber: <?php echo (int) $deckNumber; ?>,
        isCommanderDeck: <?php echo in_array($decktype, $commander_decktypes) ? 'true' : 'false'; ?>,
        deckName: <?php echo json_encode($deckName); ?>,
        deckVersion: <?php echo isset($deck_version) ? (int) $deck_version : 0; ?>,
        csrfToken: <?php echo json_encode(generateCsrfToken()); ?>,
        fragments: <?php echo json_encode($fragmentDefaults); ?>,
        fragmentTargets: <?php echo json_encode($fragmentTargets); ?>,
        randomDrawEnabled: <?php echo (isset($uniquecard_ref) && count($uniquecard_ref) > 6 && $decktype != 'Wishlist')
            ? 'true'
            : 'false'; ?>,
        randomDrawRefs: <?php echo isset($uniquecard_ref) ? json_encode($uniquecard_ref) : '[]'; ?>
    };
</script>
<script src="/js/deckdetail.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
<!-- Info box -->
<div class="info-box" id="infoBox" style="display:none">
    <span class="close-button material-symbols-outlined" onclick="toggleInfoBox()">close</span>
    <div class="info-box-inner">
        <h2 class="h2-no-top-margin">Adding cards</h2>
        Cards can be added singly, with multiple rows, or via a text/csv file.
        To directly add cards, the line can be formatted in several ways:
        Some examples:
        <pre>Madame Vastra
Madame Vastra [WHO]
4 Madame Vastra [WHO]
2 (WHO) 425
2 [WHO 425]
m13,12,"Fog",en,1,0,0,{id}
"m13","12","Fog","1","0","{id}"</pre>
        Text or CSV files should be formatted the same, but can vary line to line.
        If a line in an imported file reads "Sideboard", subsequent lines will be imported into the sideboard.
    </div>
</div>
<div id="page">
    <div class="staticpagecontent" id="deckdetail-layout">
        <div id="decklist">
            <span id="printtitle" class="headername">
                <img src="images/white_m.png"> <?php echo $siteTitleEsc;?>
            </span>
            <form id="deletedeck" action="decks.php" method="POST">
                <input type='hidden' name="deletedeck" value="yes">
                <input type='hidden' name="decktodelete" value="<?php echo $deckNumber; ?>">
            </form>
            <h2 class='h2pad'><span id="deckname"><?php
            if (strlen($deckName) > 17) :
                echo $deckName . '<br><br>';
            else :
                        echo $deckName;
            endif; ?></span>
                &nbsp;
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
                &nbsp;
                <span
                    title="Duplicate"
                    onclick="duplicateDeck(
                        '<?php echo htmlspecialchars($user, ENT_QUOTES, 'UTF-8'); ?>',
                        '<?php echo htmlspecialchars($deckName, ENT_QUOTES, 'UTF-8'); ?>',
                        '<?php echo htmlspecialchars($deckNumber, ENT_QUOTES, 'UTF-8'); ?>',
                        '<?php echo !empty($decktype) ? htmlspecialchars($decktype, ENT_QUOTES, 'UTF-8') : ''; ?>'
                    )"
                    onmouseover=""
                    style="cursor: pointer;"
                    class='material-symbols-outlined'>
                    content_copy
                </span>
            </h2>
                <form id="renameForm" style="display: none;">
                    <br><textarea class='textinput' id='newname' name='newname' rows='1' cols='30'
                        placeholder="New deck name" autofocus></textarea>
                    <input class='inline_button stdwidthbutton noprint' type="submit" value="RENAME">
                </form>
                <?php
                if ($decktype == '') :
                    $decktype = "<i>Not set, click edit above</i>";
                endif;        ?>
                <h3>Deck type:<br><span id="currentType">
                    <?php echo "<span style='font-weight:500' >$decktype</span><br></span>"; ?>
                </span></h3>
                <form id="changeType" style="display: none;">
                    <select class='dropdown' size="1" name="updatetype">
                        <option <?php if ($decktype == '' or $decktype == "<i>Not set, click edit above</i>") :
                            echo "selected='selected'";
                                endif;?>disabled='disabled'>Pick one</option>
                        <?php
                        foreach ($validtypes as $deck) :
                            if ($decktype == $deck) :
                                echo "<option value='$deck' selected='selected'>$deck</option>";
                            else :
                                echo "<option value='$deck'>$deck</option>";
                            endif;
                        endforeach; ?>
                    </select>
                    <input type="hidden"name="deck" value="<?php echo $deckNumber;?>" />
                </form>

            <?php include 'includes/fragments/deckdetail_colour_identity.php'; ?>

            <?php include 'includes/fragments/deckdetail_decklist.php'; ?>
        </div>
        <div id="deckside-wrap">
            <div id="deckside">
                <div id="deckside-hero" class="deckside-placeholder">
                    <a id="deckside-hero-link" href="#" aria-label="Open card detail">
                        <img id="deckside-hero-img" src="/images/back.jpg" alt="Deck preview">
                    </a>
                </div>
                <div id="decknotesdiv">
            <?php include 'includes/fragments/deckdetail_warnings.php'; ?>
            <form id="updatenotesform" action="?" method="POST">
                <h4>Notes</h4>
                <textarea class='decknotes textinput' id="notes" name='newnotes' rows='2' cols='40'>
<?php echo $notes; ?></textarea>
                <?php if ($decktype != 'Wishlist') :  ?>
                    <h4>Sideboard notes</h4>
                    <textarea class='decknotes textinput' id="sidenotes" name='newsidenotes' rows='2' cols='40'>
                    <?php echo $sidenotes; ?></textarea><br>
                <?php endif;  ?>
                <input type='hidden' name='deck' value='<?php echo $deckNumber?>'>
                <button class='inline_button save_icon' type="button" onclick="submitForm()" title="Save" disabled>
                    <span class="material-symbols-outlined">save</span>
                </button>
            </form>
            <hr id='deckline' class='hr324'>
            <?php
            include 'includes/fragments/deckdetail_mana_data.php';
            include 'includes/fragments/deckdetail_mana_value.php';
            include 'includes/fragments/deckdetail_mana_costs.php';
            include 'includes/fragments/deckdetail_deck_value.php';
            if ($decktype != 'Wishlist') : // Condense to 2 columns for wishlists
                ?>
            </div>
            <div id='deckfunctions'>
                <?php
            endif;
            echo '<div id="deck-random-draw-anchor"></div>';
            include 'includes/fragments/deckdetail_random_draw.php';
            include 'includes/fragments/deckdetail_deck_lists.php';
            ?>
            <div id="deck-quickadd-group">
                <div class="section-header-row">
                    <h4>Add cards</h4>
                    <span id="help-button" class="material-symbols-outlined" onclick="toggleInfoBox()">help</span>
                </div>
                <form id="quickadd-form" action="deckdetail.php" method="GET">
                    <textarea class='textinput' rows="3" cols="47" name="quickadd" id="quickadd-text"></textarea>
                    <br>
                    <input class='inline_button stdwidthbutton noprint' type="submit" value="ADD">
                    <?php echo "<input type='hidden' name='deck' value='$deckNumber'>"; ?>
                </form>
                <form id="import-form" enctype='multipart/form-data'>
                    <div class="import-title">From text or csv file:</div>
                    <label class='importlabel'>
                        <input id='importfile' type='file' name='filename'>
                        <span>SELECT</span>
                    </label>
                    <input
                        class='profilebutton'
                        id='importsubmit'
                        type='submit'
                        value='IMPORT'
                        disabled
                    >
                </form>
            </div>
            <div id='photo_upload' style="padding-bottom:20px;">
                <h4>Photo</h4>
                <?php
                $imageFilePath = $imgLocation . 'deck_photos/' . $deckNumber . '.jpg';
                $existingImage = 'cardimg/deck_photos/' . $deckNumber . '.jpg';
                // Check if the file exists and log appropriate messages
                if (file_exists($imageFilePath)) :
                    $msg->logMessage('[DEBUG]', "Image exists at: $imageFilePath, existingImage: $existingImage");
                else :
                    $msg->logMessage('[DEBUG]', "No current image at: $imageFilePath, existingImage: $existingImage");
                endif; ?>
                <form id="uploadForm">
                    <input type="hidden" name="decknumber" value="<?php echo $deckNumber; ?>">
                    <label class='importlabel'>
                        <input id='importphoto' type='file' name='photo' accept='image/jpeg'>
                        <span>SELECT</span>
                    </label>
                    <input class='profilebutton' id='photosubmit' type='submit' value="UPLOAD">
                    <button
                        class="profilebutton"
                        id="deletePhotoBtn"
                        type="button"
                        <?php echo !file_exists($imageFilePath) ? 'style="display:none;"' : ''; ?>
                    >DELETE</button>
                </form>
                <?php
                if (file_exists($imageFilePath)) :?>
                    <div id='photo_div'>
                        <br>
                        <!-- Placeholder for legacy deck photo preview -->
                        <img
                            id="deckPhoto"
                            src="deckimage.php?deck=<?php echo $deckNumber; ?>"
                            style="max-width: 300px;"
                            alt="Existing Photo"
                        >
                    </div><?php
                else : ?>
                    <div id='photo_div' style="display: none;">
                        <br>
                        <img id="deckPhoto" src="" style="max-width: 300px;" alt="Existing Photo">
                    </div> <?php
                endif; ?>
                <div id="result"></div>
            </div>
                </div>
            </div>
            <div id="deckside-footer"></div>
        </div>
    </div>
</div>

<?php
$msg->logMessage('[DEBUG]', "Page complete");
require('includes/footer.php'); ?>
</body>
</html>
