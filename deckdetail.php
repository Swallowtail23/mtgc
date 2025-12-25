<?php

/*
Version:     25.45
Date:        24/12/25
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
    <script src="/js/jquery.js"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        function swapImageWithFade($img, newSrc) {
            $img.css('opacity', '0');
            $img.off('load.mtgfade').on('load.mtgfade', function() {
                const $self = $(this);
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        $self.css('opacity', '1');
                    });
                });
            });
            $img.attr('src', newSrc);
            if ($img[0] && $img[0].complete) {
                setTimeout(function() {
                    $img.trigger('load');
                }, 0);
            }
        }

        const deckImageQueue = [];
        const deckImageQueued = {};
        let deckImageInFlight = 0;
        let deckImagePauseUntil = 0;
        const deckImageMaxConcurrent = 3;

        function enqueueDeckImage(cardId, priority) {
            if (!cardId) {
                return;
            }
            if (deckImageQueued[cardId] !== true) {
                deckImageQueued[cardId] = true;
                if (priority === true) {
                    deckImageQueue.unshift(cardId);
                } else {
                    deckImageQueue.push(cardId);
                }
            } else if (priority === true) {
                const idx = deckImageQueue.indexOf(cardId);
                if (idx > 0) {
                    deckImageQueue.splice(idx, 1);
                    deckImageQueue.unshift(cardId);
                }
            }
            scheduleDeckImageLoad();
        }

        function scheduleDeckImageLoad() {
            if (Date.now() < deckImagePauseUntil) {
                setTimeout(scheduleDeckImageLoad, 120);
                return;
            }
            if (deckImageInFlight >= deckImageMaxConcurrent || deckImageQueue.length === 0) {
                return;
            }
            const cardId = deckImageQueue.shift();
            deckImageInFlight += 1;
            $.ajax({
                url: 'ajax/ajaximagecheck.php',
                type: 'POST',
                data: { cardid: cardId },
                dataType: 'json',
                success: function(response) {
                    if (!response || !response.success) {
                        return;
                    }
                    if (response.front && response.front.indexOf('cardimg') !== -1) {
                        const newSrc = response.front + '?t=' + Date.now();
                        $('img[data-cardid="' + cardId + '"]').each(function() {
                            const $target = $(this);
                            $target.attr('data-front-src', response.front);
                            $target.attr('data-back-src', response.back || $target.attr('data-back-src') || '');
                            swapImageWithFade($target, newSrc);
                        });
                    }
                    if (response.back && response.back.indexOf('cardimg') !== -1) {
                        $('img[data-cardid="' + cardId + '"]').attr('data-back-src', response.back);
                    }
                },
                complete: function() {
                    deckImageInFlight -= 1;
                    setTimeout(scheduleDeckImageLoad, 0);
                }
            });
            setTimeout(scheduleDeckImageLoad, 0);
        }

        function refreshCardImagesAsync() {
            const seen = {};
            $('img[data-cardid]').each(function() {
                const cardId = $(this).data('cardid');
                if (!cardId || seen[cardId]) {
                    return;
                }
                seen[cardId] = true;
                enqueueDeckImage(cardId, false);
            });
        }
    </script>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            refreshCardImagesAsync();
            // Update the 'onerror' attribute for all images
            $('img').on('error', function() {
                this.src = '/images/back.jpg';
            });
            $(document).on('pointerdown keydown', function() {
                deckImagePauseUntil = Date.now() + 300;
            });

            // Click event for the document (outside .deckcardimgdiv and .randomcardimgdiv)
            $(document).on('click', function () {
                $('.deckcardimgdiv').hide("slow");
                $('.randomcardimgdiv').hide("slow");
            });

            $('#menubuttondiv, .searchicon').on('click', function () {
                $('.deckcardimgdiv').hide("slow");
                $('.randomcardimgdiv').hide("slow");
            });

            // Scroll event for the window
            $(window).on('scroll', function () {
                $('.deckcardimgdiv').hide("slow");
                $('.randomcardimgdiv').hide("slow");
            });

            // Function to bind events for newly loaded content
            window.bindRandomCardEvents = function() {
                $('td').off('mouseenter mouseleave');
                $('td.hoverTD').off('touchstart touchmove touchend');
                $('td.hoverTD a.taphover').off('click');
                let touchDetected = false;
                let hoverTimeout;
                let lastHoveredDiv = null;

                function setupNonTouchEvents() {
                    $('td').on('mouseenter', function(e) {
                        if (touchDetected) return;

                        var $link = $(this).find('a.taphover');
                        if ($link.length) {
                            var id = $link.attr('id');
                            var $div = $('#' + id.replace('-taphover', ''));
                            var cardId = $div.find('img[data-cardid]').data('cardid') || $link.data('cardid');
                            if (cardId) {
                                enqueueDeckImage(cardId, true);
                            }

                            if (lastHoveredDiv && lastHoveredDiv !== $div) {
                                clearTimeout(lastHoveredDiv.data('timeoutId'));
                                lastHoveredDiv.hide();
                            }

                            lastHoveredDiv = $div;

                            hoverTimeout = setTimeout(function() {
                                showHoverDiv($link, e);
                            }, 200); // 200ms delay before showing the hover image

                            $div.on('mouseenter', function() {
                                clearTimeout($div.data('timeoutId'));
                            }).on('mouseleave', function() {
                                var timeoutId = setTimeout(function() {
                                    $div.hide("slow");
                                }, 200); // 200ms delay before hiding the div
                                $div.data('timeoutId', timeoutId);
                            });
                        }
                    }).on('mouseleave', function(e) {
                        if (touchDetected) return;

                        clearTimeout(hoverTimeout);

                        var $link = $(this).find('a.taphover');
                        if ($link.length) {
                            var id = $link.attr('id');
                            var $div = $('#' + id.replace('-taphover', ''));
                            var timeoutId = setTimeout(function() {
                                $div.hide("slow");
                            }, 200); // 200ms delay before hiding the div
                            $div.data('timeoutId', timeoutId);
                        }
                    });
                }

                function removeNonTouchEvents() {
                    $('td').off('mouseenter mouseleave');
                }

                function setupTouchEvents() {
                    let touchStartTime = 0;
                    let touchStartX = 0;
                    let touchStartY = 0;
                    let isScrolling = false;
                    let shouldTriggerLink = false;

                    // Touch start event
                    $('td.hoverTD').on('touchstart', function(e) {
                        touchStartTime = Date.now();
                        isScrolling = false;
                        shouldTriggerLink = false;

                        const touch = e.originalEvent.touches[0] || e.originalEvent.changedTouches[0];
                        touchStartX = touch.pageX;
                        touchStartY = touch.pageY;

                        // Add touch-active and no-hover classes
                        $('tr.deckrow').addClass('no-hover');
                    });

                    // Touch move event
                    $('td.hoverTD').on('touchmove', function(e) {
                        const touch = e.originalEvent.touches[0] || e.originalEvent.changedTouches[0];
                        const moveX = touch.pageX;
                        const moveY = touch.pageY;

                        if (Math.abs(moveX - touchStartX) > 10 || Math.abs(moveY - touchStartY) > 10) {
                            isScrolling = true;
                        }
                    });

                    // Touch end event
                    $('td.hoverTD').on('touchend', function(e) {
                        const touchDuration = Date.now() - touchStartTime;

                        if (!isScrolling && touchDuration < 300) {
                            // 300ms threshold to distinguish between tap and scroll
                            var $link = $(this).find('a.taphover');
                            if ($link.length) {
                                e.preventDefault();
                                shouldTriggerLink = true;
                                if (lastHoveredDiv && lastHoveredDiv.is(':visible')) {
                                    lastHoveredDiv.hide();
                                }

                                // Ensure event contains touches or changedTouches directly
                                const touch = e.originalEvent.changedTouches[0] || e.originalEvent.touches[0];
                                const customEvent = {
                                    pageX: touch.pageX,
                                    pageY: touch.pageY
                                };
                                showHoverDiv($link, customEvent); // Custom event with correct coordinates passed here
                                lastHoveredDiv = $('#' + $link.attr('id').replace('-taphover', ''));
                            }
                        } else {
                            shouldTriggerLink = false;
                        }
                    });

                    // Click event to prevent link following
                    $('td.hoverTD a.taphover').on('click', function(e) {
                        if (!shouldTriggerLink) {
                            e.preventDefault();
                        }
                    });
                }

                function getMenuWidth() {
                    const menu = document.getElementById('menu');
                    if (menu) {
                        const computedStyle = window.getComputedStyle(menu);
                        const left = parseInt(computedStyle.left, 10);

                        // If the menu is off-screen (negative left position), consider it inactive
                        if (left < 0) {
                            return 0;
                        }

                        return menu.offsetWidth;
                    }
                    return 0;
                }

                function getHeaderHeight() {
                    const header = document.getElementById('header');
                    if (header) {
                        const computedStyle = window.getComputedStyle(header);
                        const height = parseInt(computedStyle.height, 10);

                        return height;
                    }
                    return 0;
                }

                function showHoverDiv($link, e) {
                    var id = $link.attr('id');
                    var $div = $('#' + id.replace('-taphover', ''));
                    var mouseX, mouseY;
                    var cardId = $div.find('img[data-cardid]').data('cardid') || $link.data('cardid');
                    if (cardId) {
                        enqueueDeckImage(cardId, true);
                    }

                    if (e.pageX && e.pageY) {
                        mouseX = e.pageX;
                        mouseY = e.pageY;
                    } else {
                        // Handle cases where pageX and pageY are not directly available
                        const touch = e.changedTouches ? e.changedTouches[0] : e.touches[0];
                        mouseX = touch.pageX;
                        mouseY = touch.pageY;
                    }

                    // Force reflow to ensure dimensions are calculated
                    $div.css('display', 'block');
                    var divWidth = $div.outerWidth();
                    var divHeight = $div.outerHeight();
                    $div.css('display', 'none');

                    // Set fallback value for divHeight if it's 0
                    if (divWidth === 0) {
                        divWidth = 180; // Assuming 180 as the default width
                    }
                    if (divHeight === 0) {
                        divHeight = 258; // Assuming 258 as the default height
                    }

                    // Get the width of the menu if it's active
                    var menuWidth = getMenuWidth();
                    // Get the height of the header
                    var headerHeight = getHeaderHeight();
                    // Adjust position to prevent overflow if necessary
                    var leftPosition = mouseX - 150;
                    // Always show the image 80px below mouse click, even when scrolled
                    var topPosition = mouseY - headerHeight + 80;

                    // Ensure the div stays within the viewport and does not overlap the menu
                    var viewportWidth = $(window).width();
                    var viewportHeight = $(window).height();
                    var bottomViewable = viewportHeight + window.scrollY;
                    var realImgBottom = mouseY + 80 + divHeight;
                    console.log({
                        mouseX, mouseY, menuWidth, headerHeight,
                        leftPosition, topPosition, viewportWidth, viewportHeight,
                        bottomViewable, divWidth, divHeight, realImgBottom
                    });

                    // TopPosition is the distance from the top even if that is scrolled off the top of the view
                    // It positions the top of the image below the header
                    //      It is relative to bottom of header
                    // viewportHeight is what can be seen
                    // window.scrollY is what is off the top

                    if (leftPosition + divWidth > viewportWidth) {
                        leftPosition = viewportWidth - divWidth - 10; // 10px padding from the edge
                    }
                    if (leftPosition < menuWidth) {
                        leftPosition = menuWidth + 100; // 10px padding from the menu
                    }
                    if (realImgBottom + 10 > bottomViewable) {
                        // the image won't fit in view
                        topPosition = Math.max(
                            mouseY - divHeight - headerHeight - 80,
                            window.scrollY + 10
                        ); // 80px above mouse, unless < 10px from header
                    }

                    console.log({ leftPosition, topPosition }); // Log the final positions

                    $div.css({ top: topPosition + 'px', left: leftPosition + 'px' }).show("slow");
                }

                // Default to non-touch hover solution
                setupNonTouchEvents();

                // Global touchstart listener to detect touch events on td.hoverTD
                window.addEventListener('touchstart', function(e) {
                    if (touchDetected) return;

                    if (!$(e.target).closest('td.hoverTD').length) {
                        return;
                    }

                    touchDetected = true;

                    // Remove existing non-touch event handlers
                    removeNonTouchEvents();

                    // Set up touch-specific events
                    setupTouchEvents();
                }, { capture: true, passive: false }); // Use capture phase and set passive to false

                // Rebind mouse-specific event handlers when mouse movement is detected
                window.addEventListener('mousemove', function(e) {
                    if (!touchDetected) return;

                    touchDetected = false;

                    // Remove touch-specific event handlers
                    $('td.hoverTD').off('touchstart touchmove touchend');

                    // Remove no-hover class on mouse events
                    $('tr.deckrow').removeClass('no-hover');

                    // Set up non-touch events again
                    setupNonTouchEvents();
                });
            };

            // Immediately invoke the function to bind events
            window.bindRandomCardEvents();

        });
    </script>
</head>

<body class="body">
<?php
include_once("includes/analyticstracking.php");
require('includes/overlays.php');
require('includes/header.php');
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
<script src="/js/deckdetail.js"></script>
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
    <div class="staticpagecontent">
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
        <div id="decknotesdiv">
            <?php include 'includes/fragments/deckdetail_warnings.php'; ?>
            <form id="updatenotesform" action="?" method="POST">
                <h4>&nbsp;Notes</h4>
                <textarea class='decknotes textinput' id="notes" name='newnotes' rows='2' cols='40'>
<?php echo $notes; ?></textarea>
                <?php if ($decktype != 'Wishlist') :  ?>
                    <h4>&nbsp;Sideboard notes</h4>
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
            include 'includes/fragments/deckdetail_random_draw.php';
            if ($decktype != 'Wishlist') : // Condense to 2 columns for wishlists
                ?>
        </div>
        <div id='deckfunctions'>
                <?php
            endif;
            include 'includes/fragments/deckdetail_deck_lists.php';
            ?>
            <h4>Add cards</h4>
            <form id="quickadd-form" action="deckdetail.php" method="GET">
                <!-- Hovering help button -->
                <span id="help-button" class="material-symbols-outlined" onclick="toggleInfoBox()">help</span>

                <textarea class='textinput' rows="3" cols="47" name="quickadd" id="quickadd-text"></textarea>
                <br>
                <input class='inline_button stdwidthbutton noprint' type="submit" value="ADD">
                <?php echo "<input type='hidden' name='deck' value='$deckNumber'>"; ?>
            </form>
           From text or csv file:
            <form id="import-form" enctype='multipart/form-data'>
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
</div>

<?php
$msg->logMessage('[DEBUG]', "Page complete");
require('includes/footer.php'); ?>
</body>
</html>
