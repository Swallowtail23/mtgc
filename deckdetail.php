<?php

/*
Version:     25.7
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

            // Toggle form visibility using jQuery
            window.toggleForm = function () {
                $("#renameForm, #changeType, #currentType").toggle("block");
            };

            // Cursor change function using jQuery
            window.ComparePrep = function () {
                $('body').css('cursor', 'wait');
            };

            // Event listener for textareas using jQuery
            var notesTextarea = $('#notes');
            var sidenotesTextarea = $('#sidenotes');
            var saveButton = $('.save_icon');

            // Store the initial values of the textareas
            var initialNotesValue = notesTextarea.val();
            var initialSidenotesValue = sidenotesTextarea.val();

            // Add an event listener to the "Notes" textarea
            notesTextarea.on('input', checkChanges);

            // Add an event listener to the "Sideboard notes" textarea (if it exists)
            if (sidenotesTextarea.length) {
                sidenotesTextarea.on('input', checkChanges);
            }

            function checkChanges() {
                // Check if either textarea is different from its initial value
                if (
                    notesTextarea.val() !== initialNotesValue
                    || (sidenotesTextarea.length && sidenotesTextarea.val() !== initialSidenotesValue)
                ) {
                    saveButton.prop('disabled', false);
                } else {
                    saveButton.prop('disabled', true);
                }
            }
            window.duplicateDeck = function(user, deckname, decknumber, decktype) {
                // Create a FormData object to send user and deckname to PHP
                let formData = new FormData();
                formData.append('user', user);
                formData.append('deckname', deckname);
                formData.append('decknumber', decknumber);
                formData.append('decktype', decktype);

                // Make an AJAX request to the PHP script
                fetch('ajax/ajaxduplicatedeck.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json()) // Parse JSON response
                .then(data => {
                    if (data.success) {
                        if (data.decknumber) {
                            alert('Deck duplicated successfully!');
                            window.location.href = 'deckdetail.php?deck=' + data.decknumber;
                            // Redirect to the deck detail page with the deck number
                        } else {
                            alert('Deck duplicated successfully, but no deck number returned.');
                            window.location.href = 'decks.php';
                        }
                    } else {
                        if (data.error === 'User not logged in') {
                            // Redirect to the login page
                            window.location.href = '/login.php';
                        } else {
                            alert('Error duplicating deck: ' + data.error);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('There was an issue duplicating the deck.');
                });
            };
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
    if (isset($_GET["updatetype"])) :
        $updatetype = $_GET["updatetype"];
    endif;
elseif (isset($_POST["deck"])) :
    $deckNumber     = filter_input(INPUT_POST, 'deck', FILTER_SANITIZE_NUMBER_INT);
    $renamedeck     = isset($_POST['renamedeck']) ? 'yes' : '';
    $newname        = isset($_POST['newname'])
        ? filter_input(
            INPUT_POST,
            'newname',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            FILTER_FLAG_NO_ENCODE_QUOTES
        )
        : '';
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
<script type="text/javascript">
    function closeMe( obj )
    {
        obj.style.display = 'none';
        window.location.href="deckdetail.php?deck=<?php echo $deckNumber;?>";
    }
</script> <?php
$cardtoaction   = isset($_GET['card'])          ? filter_input(INPUT_GET, 'card', FILTER_SANITIZE_SPECIAL_CHARS) : '';
$deletemain     = isset($_GET['deletemain'])    ? 'yes' : '';
$deleteside     = isset($_GET['deleteside'])    ? 'yes' : '';
$maintoside     = isset($_GET['maintoside'])    ? 'yes' : '';
$sidetomain     = isset($_GET['sidetomain'])    ? 'yes' : '';
$plusmain       = isset($_GET['plusmain'])      ? 'yes' : '';
$minusmain      = isset($_GET['minusmain'])     ? 'yes' : '';
$plusside       = isset($_GET['plusside'])      ? 'yes' : '';
$minusside      = isset($_GET['minusside'])     ? 'yes' : '';
$valid_commander = array("yes","no");
if (isset($_GET['commander']) and (in_array($_GET['commander'], $valid_commander))) :
    $commander = $_GET['commander'];
else :
    $commander = '';
endif;
if (isset($_GET['partner']) and (in_array($_GET['partner'], $valid_commander))) :
    $partner = $_GET['partner'];
else :
    $partner = '';
endif;

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

// Update name if called before reading info (we've already checked ownership)
if (isset($_POST['newname'])) :
    $msg->logMessage('[DEBUG]', "Renaming deck to $newname");
    $obj = new \MTG\Cards\DeckManager(
        $db,
        $logfile,
        $userEmail,
        $serverEmail,
        $importLinestoIgnore,
        $nonPreferredSetCodes
    );
    $renameresult = $obj->renameDeck($deckNumber, $newname, $user);
    $msg->logMessage('[DEBUG]', "Renaming deck result: $renameresult");
    if ($renameresult == 2) :
        ?>
        <div class="msg-new error-new" onclick='closeMe(this)'><span>Deck name exists already</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <?php
    elseif ($renameresult > 0) :
        ?>
        <div class="msg-new error-new" onclick='closeMe(this)'><span>Unknown error</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <?php
    else :
        $redirect = true;
    endif;
endif;

//Update deck type if called before reading info
if (isset($updatetype)) :
    if (in_array($updatetype, $validtypes)) :
        $msg->logMessage('[DEBUG]', "Updating deck type to '$updatetype'");
        $setdecktype = $obj->setDeckType($deckNumber, $updatetype);
        if ($setdecktype !== 0) :
            throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": Deck type change failed ");
        else :
            if (!in_array($updatetype, $commander_decktypes)) :
                if (
                    $db->execute_query(
                        "UPDATE deckcards SET commander = 0 WHERE decknumber = ?",
                        [$deckNumber]
                    ) === false
                ) :
                    throw new Exception(
                        "[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error
                    );
                endif;
            endif;
        endif;
    else :
        throw new Exception("[ERROR] deckdetail.php " . __LINE__ . ": Error: Invalid deck type");
    endif;

    // Set quantities to 1 for commander decks
    if (in_array($updatetype, $commander_decktypes)) :
        $query = "UPDATE deckcards LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id SET cardqty=? 
            WHERE deckcards.decknumber = ? AND (deckcards.sideqty IS NULL or sideqty = 0) 
            AND cards_scry.type NOT LIKE 'Basic Land%'";
        $msg->logMessage('[DEBUG]', "Updating deck type to a Commander type, setting quantities to 1");
        if ($db->execute_query($query, [1, $deckNumber]) != true) :
            throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
        else :
            $msg->logMessage('[DEBUG]', "...sql result: {$db->info}");
        endif;
        $query = 'UPDATE deckcards SET sideqty=? WHERE (decknumber = ? AND (cardqty IS NULL or cardqty = 0) )';
        if ($db->execute_query($query, [1, $deckNumber]) != true) :
            throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
        else :
            $msg->logMessage('[DEBUG]', "...sql result: {$db->info}");
        endif;
        $query = 'UPDATE deckcards SET sideqty = NULL WHERE (decknumber = ? AND cardqty > 0)';
        if ($db->execute_query($query, [$deckNumber]) != true) :
            throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
        else :
            $msg->logMessage('[DEBUG]', "...sql result: {$db->info}");
        endif;
    endif;
    if ($updatetype == 'Wishlist') :
        $query = 'UPDATE deckcards SET sideqty = NULL WHERE (decknumber = ? AND cardqty > 0)';
        $msg->logMessage('[DEBUG]', "Updating deck type to a Wishlist, deleting sideboard cards");
        if ($db->execute_query($query, [$deckNumber]) != true) :
            throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
        else :
            $msg->logMessage('[DEBUG]', "...sql result: {$db->info}");
        endif;
    endif;
    $redirect = true;
endif;

//Carry out quick add requests
if (isset($_GET["quickadd"])) :
    $deckManager = new \MTG\Cards\DeckManager(
        $db,
        $logfile,
        $userEmail,
        $serverEmail,
        $importLinestoIgnore,
        $nonPreferredSetCodes
    );
    $cardtoadd = $deckManager->processInput($deckNumber, $_GET["quickadd"]);
endif;

//Deck import
if (isset($_POST['import'])) :
    $msg->logMessage('[DEBUG]', "Import called, checking file uploaded...");
    if (is_uploaded_file($_FILES['filename']['tmp_name'])) :
        $msg->logMessage('[DEBUG]', "Import file {$_FILES['filename']['name']} uploaded");
        $file = fopen($_FILES['filename']['tmp_name'], 'r');
        $deckManager = new \MTG\Cards\DeckManager(
            $db,
            $logfile,
            $userEmail,
            $serverEmail,
            $importLinestoIgnore,
            $nonPreferredSetCodes
        );
        // Read the entire file content into a variable
        $fileContent = fread($file, filesize($_FILES['filename']['tmp_name']));
        fclose($file);

        // Call the processInput method with the decknumber and file content
        $cardtoadd = $deckManager->processInput($deckNumber, $fileContent);
        $redirect = ($cardtoadd === 'multierror') ? false : true;
    else :
        $msg->logMessage('[DEBUG]', "Import file {$_FILES['filename']['name']} failed");
    endif;
endif;

// Get deck details from database
if (
    $deckinfoqry = $db->execute_query(
        "SELECT deckname,notes,sidenotes,type FROM decks WHERE decknumber = ? LIMIT 1",
        [$deckNumber]
    )
) :
    $deckinfo = $deckinfoqry->fetch_assoc();
    $deckName   = $deckinfo['deckname'];
    $notes      = $deckinfo['notes'];
    $sidenotes  = $deckinfo['sidenotes'];
    $decktype   = $deckinfo['type'];
else :
    throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
endif;

// Get relevant db_field with legality
if ($decktype != '') :
    $db_field = cardLegalDBField($decktype);
else :
    $db_field = '';
endif;
$msg->logMessage('[DEBUG]', "Legality db-field for this deck is '$db_field'");

// Get deck legalities
if ($db_field != '') :
    $deck_legality_list = deckLegalList($deckNumber, $decktype, $db_field);
else :
    $deck_legality_list = '';
endif;

// Add / delete, before calling the deck list
$obj = new \MTG\Cards\DeckManager($db, $logfile, $userEmail, $serverEmail, $importLinestoIgnore, $nonPreferredSetCodes);

if ($deletemain == 'yes') :
    $obj->subtractDeckCard($deckNumber, $cardtoaction, "main", "all");
    $redirect = true;
elseif ($deleteside == 'yes') :
    $obj->subtractDeckCard($deckNumber, $cardtoaction, "side", "all");
    $redirect = true;
elseif ($maintoside == 'yes') :
    if ($obj->subtractDeckCard($deckNumber, $cardtoaction, 'main', '1') != "-error") :
        $obj->addDeckCard($deckNumber, $cardtoaction, "side", "1");
    endif;
    $redirect = true;
elseif ($sidetomain == 'yes') :
    if ($obj->subtractDeckCard($deckNumber, $cardtoaction, 'side', '1') != "-error") :
        $obj->addDeckCard($deckNumber, $cardtoaction, "main", "1");
    endif;
    $redirect = true;
elseif ($plusmain == 'yes') :
    $obj->addDeckCard($deckNumber, $cardtoaction, "main", "1");
    $redirect = true;
elseif ($minusmain == 'yes') :
    $obj->subtractDeckCard($deckNumber, $cardtoaction, 'main', '1');
    $redirect = true;
elseif ($plusside == 'yes') :
    $obj->addDeckCard($deckNumber, $cardtoaction, "side", "1");
    $redirect = true;
elseif ($minusside == 'yes') :
    $obj->subtractDeckCard($deckNumber, $cardtoaction, 'side', '1');
    $redirect = true;
elseif ($commander == 'yes') :
    $msg->logMessage('[NOTICE]', "Adding Commander to deck $deckNumber: $cardtoaction");
    $obj->addCommander($deckNumber, $cardtoaction);
    $redirect = true;
elseif ($partner == 'yes') :
    $msg->logMessage('[NOTICE]', "Moving Commander to Partner for deck $deckNumber: $cardtoaction");
    $obj->addPartner($deckNumber, $cardtoaction);
    $redirect = true;
elseif ($commander == 'no') :
    $obj->delCommander($deckNumber, $cardtoaction);
    $redirect = true;
endif;

// PRG
if ($redirect == true) : ?>
    <meta http-equiv='refresh' content='0; url=deckdetail.php?deck=<?php echo $deckNumber; ?>'> <?php
    exit();
endif;

//Get card list
$mainquery = ("SELECT *,cards_scry.id AS cardsid
                        FROM deckcards
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id
                    WHERE decknumber = ? AND cardqty > 0 ORDER BY name");
$msg->logMessage('[DEBUG]', "$mainquery");
$result = $db->execute_query($mainquery, [$deckNumber]);
if ($result != true) :
    throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
endif;

$sidequery = ("SELECT *,cards_scry.id AS cardsid
                        FROM deckcards
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id
                    WHERE decknumber = ? AND sideqty > 0 ORDER BY name");
$sideresult = $db->execute_query($sidequery, [$deckNumber]);
if ($sideresult != true) :
    throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
endif;

//Initialise variables to 0
$cdr = $creatures = $instantsorcery = $other = $lands = $deckvalue = $planes = $side = 0;
$deck_colour_mismatch = $illegal_cards = '';

//Illegal card style tags
$red_font_tag = "style='color: OrangeRed; font-weight: bold'";
$firebrick_font_tag = "style='color: FireBrick; font-weight: bold'";

//This section works out which cards the user DOES NOT have, for later linking
// in a text file to download
$resultnames = [];
$rowNumber = 0;
while ($row = $result->fetch_assoc()) :
    $rowNumber++;
    $qty = $row['cardqty'];

    $found = false;
    foreach ($resultnames as &$entry) :
        if ($entry['name'] === $row['name']) :
            if (isset($entry['qty'])) :
                $entry['qty'] += $qty;
            else :
                $entry['qty'] = $qty;
            endif;
            $found = true;
            break;
        endif;
    endforeach;
    unset($entry); // break the reference with the last element

    if (!$found) :
        $resultnames[$rowNumber] = ['name' => $row['name'], 'flavor_name' => $row['flavor_name'], 'qty' => $qty];
    endif;
endwhile;

while ($row = $sideresult->fetch_assoc()) :
    $qty = $row['sideqty'];

    $found = false;
    foreach ($resultnames as &$entry) :
        if ($entry['name'] === $row['name']) :
            if (isset($entry['qty'])) :
                $entry['qty'] += $qty;
            else :
                $entry['qty'] = $qty;
            endif;
            $found = true;
            break;
        endif;
    endforeach;
    unset($entry); // break the reference with the last element

    if (!$found) :
        $resultnames[] = ['name' => $row['name'], 'flavor_name' => $row['flavor_name'], 'qty' => $qty];
    endif;
endwhile;
$uniquecardscount = count($resultnames);
$msg->logMessage('[DEBUG]', "Cards in deck: $uniquecardscount");
$msg->logMessage('[DEBUG]', "Cards in deck: " . print_r($resultnames, true));
$requiredlist = '';
$requiredbuy = '';
if ($uniquecardscount > 0) :
        $shortqty = array_fill(0, $uniquecardscount, '0'); //create an array the right size, all '0'
        $placeholderCount = count($resultnames) * 2; // 2 placeholders per card in the result list
        // Extract names from the subarrays
        $names = array_map(function ($entry) {
                return $entry['name'];
        }, $resultnames);
        $msg->logMessage('[DEBUG]', "Missing check on " . count($resultnames) . " cards");
        $placeholders = implode(',', array_fill(0, count($resultnames), '?'));
        // create placeholders for prepared statement

        $msg->logMessage('[DEBUG]', "Missing check on cards: " . implode(', ', $names));

        // Duplicate the $resultnames array to match the number of placeholders
        $params = array_merge($names, $names);

        $query = "
            SELECT name, flavor_name,
                   SUM(IFNULL(`$mytable`.etched, 0))
                       + SUM(IFNULL(`$mytable`.foil, 0))
                       + SUM(IFNULL(`$mytable`.normal, 0)) AS allcopies
            FROM $mytable
            LEFT JOIN cards_scry
            ON $mytable.id = cards_scry.id
            WHERE
                cards_scry.name IN ($placeholders) OR
                cards_scry.flavor_name IN ($placeholders)
            GROUP BY name
        ";

    if ($totalresult = $db->execute_query($query, $params)) :
        // $totalresult will be an array of qties of cards in collection
        $cardCopies = [];
        $rowNumber = 0;

        while ($totalrow = $totalresult->fetch_assoc()) :
            $rowNumber++;
            $msg->logMessage('[DEBUG]', print_r($totalrow['name'], true));

            if (!isset($cardCopies[$rowNumber])) :
                $cardCopies[$rowNumber] = [];
            endif;

            if (isset($totalrow['name']) && !empty($totalrow['name'])) :
                $cardCopies[$rowNumber]['name'] = $totalrow['name'];
            endif;
            if (isset($totalrow['flavor_name']) && !empty($totalrow['flavor_name'])) :
                $cardCopies[$rowNumber]['flavor_name'] = $totalrow['flavor_name'];
            endif;
            if (isset($totalrow['allcopies']) && !empty($totalrow['allcopies'])) :
                $cardCopies[$rowNumber]['qty'] = $totalrow['allcopies'];
            else :
                    $cardCopies[$rowNumber]['qty'] = 0;
            endif;
        endwhile;
        $msg->logMessage('[DEBUG]', print_r($cardCopies, true));

        foreach ($resultnames as $resultEntry) :
            $found = false;
            foreach ($cardCopies as &$cardEntry) :
                if ($resultEntry['name'] === $cardEntry['name']) : // We have some of this card name
                    if ($resultEntry['qty'] > $cardEntry['qty']) : // but not enough
                        $shortqty = $resultEntry['qty'] - $cardEntry['qty'];
                        $requiredlist .= $resultEntry['name'] . " x " . $shortqty . "\r\n";
                        $requiredbuy .= $resultEntry['name'] . " " . $shortqty . "||";
                    endif;
                    $found = true;
                    break;
                endif;
            endforeach;
            unset($cardEntry); // Break the reference with the last element
            if ($found === false) :
                $requiredlist .= $resultEntry['name'] . " x " . $resultEntry['qty'] . "\r\n";
                $requiredbuy .= $resultEntry['name'] . " " . $resultEntry['qty'] . "||";
            endif;
        endforeach;

        $msg->logMessage('[DEBUG]', "Cards required list: $requiredlist");
        $msg->logMessage('[DEBUG]', "Cards required buy: $requiredbuy");
    else :
            $msg->logMessage('[ERROR]', "Database query failed");
    endif;
endif;

//This section builds hidden divs for each card with the image and a link,
// and increments type and value counters
// for main and side
// It also builds the legal Colour identity for Commander decks
mysqli_data_seek($result, 0);
$cdrSet = false;
$cdr_colours = array();
$w = 0;
$u = 0;
$b = 0;
$r = 0;
$g = 0;
$c = 0;
$gw = 0;
$gu = 0;
$gb = 0;
$gr = 0;
$gg = 0;
$gc = 0;
$i = 0;
while ($row = $result->fetch_assoc()) :
    if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
        $row['name'] = $row['flavor_name'];
    endif;
    if ($row['commander'] != 0 and $row['commander'] != null) :
        $msg->logMessage('[DEBUG]', "Checking card, colour identity {$row['color_identity']}");
        //card is a commander, get its colour identity
        $cdrSet = true;
        $cdr_colours[$i] = $row['color_identity'];
        $i = $i + 1;
    endif;
    $cardset = strtolower($row['setcode']);
    $msg->logMessage('[DEBUG]', "Checking manacost for colour quantities");
    if (
        isset($row['manacost'])
        && is_string($row['manacost'])
        && isset($row['cardqty'])
        && $row['cardqty'] !== null
    ) :
        $w = $w + (substr_count($row['manacost'], "W") * $row['cardqty']);
        $u = $u + (substr_count($row['manacost'], "U") * $row['cardqty']);
        $b = $b + (substr_count($row['manacost'], "B") * $row['cardqty']);
        $r = $r + (substr_count($row['manacost'], "R") * $row['cardqty']);
        $g = $g + (substr_count($row['manacost'], "G") * $row['cardqty']);
        $c = $c + (substr_count($row['manacost'], "C") * $row['cardqty']);
    else :
        $msg->logMessage('[DEBUG]', "Manacost not a string");
    endif;
    $msg->logMessage('[DEBUG]', "Checking for generated mana");
    if (
        isset($row['generatedmana'])
        && is_string($row['generatedmana'])
        && isset($row['cardqty'])
        && $row['cardqty'] !== null
    ) :
        $msg->logMessage('[DEBUG]', "Generated mana ({$row['name']}) is {$row['generatedmana']}");
        $gw = $gw + (substr_count($row['generatedmana'], "W") * $row['cardqty']);
        $gu = $gu + (substr_count($row['generatedmana'], "U") * $row['cardqty']);
        $gb = $gb + (substr_count($row['generatedmana'], "B") * $row['cardqty']);
        $gr = $gr + (substr_count($row['generatedmana'], "R") * $row['cardqty']);
        $gg = $gg + (substr_count($row['generatedmana'], "G") * $row['cardqty']);
        $gc = $gc + (substr_count($row['generatedmana'], "C") * $row['cardqty']);
    else :
        $msg->logMessage('[DEBUG]', "Generated mana not a string");
    endif;
    // For SLD cards and REX cards with empty "Type", use the f1 definition instead
    if ($row['type'] !== null) :
        $card_type = $row['type'];
        $cardcmc = $row['cmc'];
    elseif ($row['type'] === null and isset($row['f1_type'])) :
        $card_type = $row['f1_type'];
        $cardcmc = $row['f1_cmc'];
    endif;

    if (strpos($card_type, ' //') !== false) :
        $len = strpos($card_type, ' //');
        $card_type = substr($card_type, 0, $len);
    endif;
    if ((strpos($card_type, 'Creature') !== false) and ($row['commander'] == 0)) :
        $creatures = $creatures + $row['cardqty'];
    elseif ((strpos($card_type, 'Sorcery') !== false) or (strpos($card_type, 'Instant') !== false)) :
        $instantsorcery = $instantsorcery + $row['cardqty'];
    elseif (
        (strpos($card_type, 'Sorcery') === false)
        and (strpos($card_type, 'Instant') === false)
        and (strpos($card_type, 'Creature') === false)
        and (strpos($card_type, 'Land') === false)
        and ((strpos($card_type, 'Plane') === false || strpos($card_type, 'Planeswalker') !== false))
        and (strpos($card_type, 'Phenomenon') === false)
        and ($row['commander'] == 0)
    ) :
        $other = $other + $row['cardqty'];
    elseif (strpos($card_type, 'Land') !== false) :
        $lands = $lands + $row['cardqty'];
    elseif (
        ((strpos($card_type, 'Plane') !== false && strpos($card_type, 'Planeswalker') === false))
        || strpos($card_type, 'Phenomenon') !== false
    ) :
        $planes = $planes + $row['cardqty'];
    endif;
    $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $imageFunction = $imageManager->getImage(
        $cardset,
        $row['cardsid'],
        $imgLocation,
        $row['layout'],
        $twoCardDetailSections,
        false
    );
    if ($imageFunction['front'] == 'error') :
        $imageUrl = '/images/back.jpg';
    else :
        $imageUrl = $imageFunction['front'];
    endif;
    $deckcardname = str_replace("'", '&#39;', $row["name"]);
    $deckvalue = $deckvalue + ($row['price_sort'] * $row['cardqty']);
    $cardref = str_replace('.', '-', $row['cardsid']);
endwhile;
$msg->logMessage('[DEBUG]', "Colours: W: $w, U: $u, B: $b, R: $r, G: $g, C: $c");
$msg->logMessage('[DEBUG]', "Gen mana: W: $gw, U: $gu, B: $gb, R: $gr, G: $gg, C: $gc");

if (isset($cdrSet) and $cdrSet === true) :
    // Finalise allowable colour identity for Commander decks
    $cdr_colours_raw = $cdr_colours = '["' . count_chars(
        str_replace(array('"', '[', ']', ',', ' '), '', implode(",", $cdr_colours)),
        3
    ) . '"]';
    $msg->logMessage('[DEBUG]', "Commander value (variable i) is $i, Colour identity to check is $cdr_colours");

    if ($i > 0 and $cdr_colours == '[""]') :
        $cdr_colours = '["C"]';
    endif;
    $cdr_colours = colourFunction($cdr_colours);
else :
    $cdr_colours_raw = $cdr_colours = "";
endif;

mysqli_data_seek($sideresult, 0);
while ($row = $sideresult->fetch_assoc()) :
    if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
        $row['name'] = $row['flavor_name'];
    endif;
    $cardset = strtolower($row["setcode"]);
    $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $imageFunction = $imageManager->getImage(
        $cardset,
        $row['cardsid'],
        $imgLocation,
        $row['layout'],
        $twoCardDetailSections,
        false
    );
    if ($imageFunction['front'] == 'error') :
        $imageUrl = '/images/back.jpg';
    else :
        $imageUrl = $imageFunction['front'];
    endif;
    $side = $side + $row['sideqty'];
    $deckvalue = $deckvalue + ($row['price_sort'] * $row['sideqty']);
    $cardref = str_replace('.', '-', $row['cardsid']);
endwhile;

// Next the main DIV section ?>
<?php
if (isset($cardtoadd) and ($cardtoadd == 'cardnotfound' or $cardtoadd == 'cardnotadded')) : ?>
    <div class="msg-new error-new" onclick='closeMe(this)'>
        <span>That didn't work... check card name</span>
        <br>
        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
    </div>
    <?php
elseif (isset($cardtoadd) and ($cardtoadd == 'multierror')) : ?>
    <div class="msg-new error-new" onclick='closeMe(this)'>
        <span>Multi input errors<br>&nbsp;Details sent by email</span>
        <br>
        <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
    </div>
    <?php
elseif (isset($cardtoadd)) : ?>
    <meta http-equiv='refresh' content='0; url=deckdetail.php?deck=<?php echo $deckNumber; ?>'> <?php
    exit();
endif;
?>
<script>
    // Function to toggle the visibility of the info box
    function toggleInfoBox() {
        var infoBox = document.getElementById("infoBox");
        infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "")
            ? "block"
            : "none";
    }
</script>
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
            <h2 class='h2pad'><?php
            if (strlen($deckName) > 17) :
                echo $deckName . '<br><br>';
            else :
                        echo $deckName;
            endif; ?>
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
                <form id="renameForm" style="display: none;" action="?" method="POST">
                    <br><textarea class='textinput' id='newname' name='newname' rows='1' cols='30'
                        placeholder="New deck name" autofocus></textarea>
                    <input type='hidden' id='renamedeck' name='renamedeck' value='yes'>
                    <input type='hidden' id='deck' name='deck' value="<?php echo $deckNumber; ?>">
                    <input class='inline_button stdwidthbutton noprint' type="submit" value="RENAME">
                </form>
                <script type="text/javascript">
                    document.getElementById('renameForm').addEventListener('submit', function(event) {
                        event.preventDefault(); // Prevent form submission
                        var fieldValue = document.getElementById('newname').value;
                        if (fieldValue.trim() === '') {
                            alert('Rename field cannot be empty');
                            return;
                        } else if (fieldValue.trim() === '<?php echo $deckName; ?>') {
                            alert('To cancel rename click edit button again');
                            return;
                        } else {
                            this.submit();
                        }
                    });
                </script> <?php
                if ($decktype == '') :
                    $decktype = "<i>Not set, click edit above</i>";
                endif;        ?>
                <h3>Deck type:<br><span id="currentType">
                    <?php echo "<span style='font-weight:500' >$decktype</span><br></span>"; ?>
                </span></h3>
                <form id="changeType" style="display: none;">
                    <select class='dropdown' size="1" name="updatetype" onchange='this.form.submit()'>
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

            <?php
            if (in_array($decktype, $commander_decktypes) and $i > 0) :
                if ($cdr_colours == 'five') :
                    $identity_title = 'All';
                else :
                    $identity_title = ucfirst($cdr_colours);
                endif;
                echo "Colour identity: <img alt='image' src=images/" . $cdr_colours . "_s.png> ($identity_title)<br>";
            endif;?>

            <table class='deckcardlist'>
                <tr class='deckcardlisthead'>
                    <td class='deckcardlisthead1'>
                        <span class="noprint">Card</span>
                    </td>
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td class="deckcardlisthead3">
                            <span class="noprint">Cdr</span>
                        </td> <?php
                    endif;
                    ?>
                    <td class="deckcardlisthead3">
                        <span class="noprint">Del</span>
                    </td>
                    <?php
                    if ($decktype != 'Wishlist') : ?>
                        <td class='deckcardlisthead3'>
                            <span class="noprint">Side</span>
                        </td> <?php
                    endif;
                    if (!in_array($decktype, $commander_decktypes)) : ?>
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
                if (in_array($decktype, $commander_decktypes)) :
                    $msg->logMessage('[DEBUG]', "This is a '$decktype' deck, adding commander row");
                    ?>
                    <tr>
                        <td colspan='4'>
                            <i><b>Commander</b></i>
                        </td>
                    </tr>
                    <?php
                    $total    = 0;
                    $cmc[0]   = 0;
                    $cmc[1]   = 0;
                    $cmc[2]   = 0;
                    $cmc[3]   = 0;
                    $cmc[4]   = 0;
                    $cmc[5]   = 0;
                    $cmc[6]   = 0;
                    $cmctotal = 0;
                    if (mysqli_num_rows($result) > 0) :
                        mysqli_data_seek($result, 0);
                        $commandercount = 0;
                        while ($row = $result->fetch_assoc()) :
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $row['name'] = $row['flavor_name'];
                            endif;

                            // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;

                            if ($row['commander'] == 1) :
                                $cardname = $row["name"];
                                $rarity = $row["rarity"];
                                $quantity = $row["cardqty"];
                                $cardset = strtolower($row["setcode"]);
                                $cardref = str_replace('.', '-', $row['cardsid']);
                                $cardId = $row['cardsid'];
                                $cardnumber = $row["number_import"];
                                $layout = $row['layout'];
                                $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                                $imageFunction = $imageManager->getImage(
                                    $cardset,
                                    $cardId,
                                    $imgLocation,
                                    $layout,
                                    $twoCardDetailSections,
                                    false
                                );
                                if ($imageFunction['front'] == 'error') :
                                    $imageUrl = '/images/back.jpg';
                                else :
                                    $imageUrl = $imageFunction['front'];
                                endif;
                                $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                                if ($deck_legality_list != '') :
                                    $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                    $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                    if ($index !== false) :
                                        $card_legal = $deck_legality_list[$index]['legality'];
                                        if ($card_legal === 'legal' or $card_legal === null) :
                                            $illegal_tag = '';
                                        else :
                                            $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                            $illegal_cards = true;
                                        endif;
                                    else :
                                        $illegal_tag = '';
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;

                                $cardcmc = round($cardcmc);
                                $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                if ($cardcmc > 5) :
                                    $cardcmc = 6;
                                endif;
                                $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity;
                                $commandername = $cardname;
                                ?>
                                <tr class='deckrow'>
                                <?php $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}"; ?>
                                <td class="deckcardname hoverTD">
                                    <?php echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                        $validpartner = false;
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "This is a '$decktype' deck, checking if $cardname is a valid partner "
                                            . "or background"
                                        );
                                        $i = 0;
                                    while ($i < count($second_commander_text)) :
                                        if (
                                            isset($row['ability'])
                                            and str_contains($row['ability'], $second_commander_text[$i]) == true
                                        ) :
                                            $validpartner = true;
                                        endif;
                                        $i++;
                                    endwhile;
                                    if ($validpartner == true) :
                                        $partnerUrl = $cardActionBase . "&amp;partner=yes"; ?>
                                            <span
                                                onmouseover=""
                                                title="Move to Partner"
                                                style="cursor: pointer;"
                                                onclick="window.location='<?php echo $partnerUrl; ?>'"
                                                class='material-symbols-outlined'>
                                                south_east
                                            </span>
                                            <?php
                                    else :
                                        $commanderNoUrl = $cardActionBase . "&amp;commander=no"; ?>
                                            <span
                                                onmouseover=""
                                                title="Move to main deck"
                                                style="cursor: pointer;"
                                                onclick="window.location='<?php echo $commanderNoUrl; ?>'"
                                                class='material-symbols-outlined'>
                                                arrow_downward
                                            </span>
                                            <?php
                                    endif;
                                        echo "</td>";
                                        echo "</td>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image rotated for deckdetail card '$cardname'"
                                    );
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                                <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                    <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                    <img
                                        alt='<?php echo $deckcardname;?>'
                                        class='deckcardimg'
                                        data-cardid="<?php echo $row['cardsid']; ?>"
                                        data-front-src="<?php echo $imageUrl; ?>"
                                        src='<?php echo $imageUrl;?>'
                                    ></a>
                                </div> <?php
                                $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter'>";
                                    echo $quantity;
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                                $total = $total + $quantity;
                                $commandercount = $commandercount + 1;
                            endif;
                        endwhile;
                    endif;
                    if (in_array($decktype, $commander_decktypes)) :
                        ?>
                        <tr>
                            <td colspan='4'>
                                <i><b>Partner / Background</b></i>
                            </td>
                        </tr>
                        <?php
                        if (mysqli_num_rows($result) > 0) :
                            mysqli_data_seek($result, 0);
                            while ($row = $result->fetch_assoc()) :
                                if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                    $row['name'] = $row['flavor_name'];
                                endif;

                                // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                                if ($row['type'] !== null) :
                                    $card_type = $row['type'];
                                    $cardcmc = $row['cmc'];
                                elseif ($row['type'] === null and isset($row['f1_type'])) :
                                    $card_type = $row['f1_type'];
                                    $cardcmc = $row['f1_cmc'];
                                endif;

                                if ($row['commander'] == 2) :
                                    $cardname = $row["name"];
                                    $rarity = $row["rarity"];
                                    $quantity = $row["cardqty"];
                                    $cardset = strtolower($row["setcode"]);
                                    $cardref = str_replace('.', '-', $row['cardsid']);
                                    $cardId = $row['cardsid'];
                                    $cardnumber = $row["number_import"];
                                    $layout = $row['layout'];
                                    $imageManager = new \MTG\Cards\ImageManager(
                                        $db,
                                        $logfile,
                                        $serverEmail,
                                        $adminEmail
                                    );
                                    $imageFunction = $imageManager->getImage(
                                        $cardset,
                                        $cardId,
                                        $imgLocation,
                                        $layout,
                                        $twoCardDetailSections,
                                        false
                                    );
                                    if ($imageFunction['front'] == 'error') :
                                        $imageUrl = '/images/back.jpg';
                                    else :
                                        $imageUrl = $imageFunction['front'];
                                    endif;
                                    $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                                    if ($deck_legality_list != '') :
                                        $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                        $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                        if ($index !== false) :
                                            $card_legal = $deck_legality_list[$index]['legality'];
                                            if ($card_legal === 'legal' or $card_legal === null) :
                                                $illegal_tag = '';
                                            else :
                                                $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                                $illegal_cards = true;
                                            endif;
                                        else :
                                            $illegal_tag = '';
                                        endif;
                                    else :
                                        $illegal_tag = '';
                                    endif;
                                    $cardcmc = round($cardcmc);
                                    $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                    if ($cardcmc > 5) :
                                        $cardcmc = 6;
                                    endif;
                                    $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity;
                                    $secondcommandername = $cardname;
                                    $warnings = true;
                                    ?>
                                    <tr class='deckrow'>
                                    <?php $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}"; ?>
                                    <td class="deckcardname hoverTD">
                                        <?php echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                            . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                            . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                            . "</a></a>";
                                        echo "<td class='deckcardlistcenter noprint'>";
                                        ?>
                                    <span
                                        onmouseover=""
                                        title="Move to main deck"
                                        style="cursor: pointer;"
                                        onclick="window.location='<?php echo $cardActionBase; ?>&amp;commander=no'"
                                        class='material-symbols-outlined'>
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                    if (
                                        in_array($row['layout'], $image90rotate)
                                        or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                    ) :
                                        $hoverclass = 'deckcardimgdiv splitfloat';
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Hover image rotated for deckdetail card '$cardname'"
                                        );
                                    else :
                                        $hoverclass = 'deckcardimgdiv';
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Hover image not rotated for deckdetail card '$cardname'"
                                        );
                                    endif;
                                    ?>
                                    <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                        <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                        <img
                                            alt='<?php echo $deckcardname;?>'
                                            class='deckcardimg'
                                            data-cardid="<?php echo $row['cardsid']; ?>"
                                            data-front-src="<?php echo $imageUrl; ?>"
                                            src='<?php echo $imageUrl;?>'
                                        ></a>
                                    </div> <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Delete"
                                        style="cursor: pointer;"
                                        onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
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
                                        onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                        class='material-symbols-outlined'>
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                    if (!in_array($decktype, $commander_decktypes)) :
                                        echo "<td class='deckcardlistcenter'>";
                                        echo $quantity;
                                        echo "</td>";
                                    endif;
                                    echo "</tr>";
                                    $total = $total + $quantity;
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
                else :
                    ?>
                    <tr>
                        <?php
                        if (in_array($decktype, $commander_decktypes)) : ?>
                            <td colspan='4'> <?php
                        elseif ($decktype == 'Wishlist') : ?>
                            <td colspan='5'> <?php
                        else : ?>
                            <td colspan='6'> <?php
                        endif; ?>
                            <i><b>Creatures (<?php echo $creatures; ?>)</b></i>
                        </td>
                    </tr>
                    <?php
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
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if ((strpos($card_type, 'Creature') !== false) and ($row['commander'] < 1)) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $uniquecard_ref["$deckcard_no"]['layout'] = $row['layout'];
                                $uniquecard_ref["$deckcard_no"]['f1_type'] = $row['f1_type'] ?? null;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;
                            $cardcmc = round($cardcmc);
                            $cardlegendary = $card_type;
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5) :
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                                if (in_array($decktype, $commander_decktypes)) :
                                    $validcommander = false;
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "This is a '$decktype' deck, checking if $cardname is a valid commander"
                                    );
                                    if (
                                        (strpos($cardlegendary, "Legendary") !== false)
                                        and (strpos($cardlegendary, "Creature") !== false)
                                    ) :
                                        $validcommander = true;
                                    endif;
                                    $i = 0;
                                    while ($i < count($valid_commander_text)) :
                                        if (
                                            isset($row['ability'])
                                            and str_contains($row['ability'], $valid_commander_text[$i]) == true
                                        ) :
                                            $validcommander = true;
                                        endif;
                                        $i++;
                                    endwhile;
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    if ($validcommander == true) :
                                        ?>
                                        <span
                                            onmouseover=""
                                            title="Move to Commander"
                                            style="cursor: pointer;"
                                            onclick="window.location='<?php echo $cardActionBase; ?>&amp;commander=yes'"
                                            class='material-symbols-outlined'>
                                            person
                                        </span>
                                        <?php
                                    endif;
                                    echo "</td>";
                                endif;
                                echo "</td>";
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage('[DEBUG]', "Hover image not rotated for deckdetail card '$cardname'");
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span
                                onmouseover=""
                                title="Delete"
                                style="cursor: pointer;"
                                onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                            <span
                                onmouseover=""
                                title="Move to sideboard"
                                style="cursor: pointer;"
                                onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                class='material-symbols-outlined'>
                                arrow_downward
                            </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;minusmain=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;plusmain=yes'"
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif; ?>
                <tr>
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td colspan='4'> <?php
                    elseif ($decktype == 'Wishlist') : ?>
                        <td colspan='5'> <?php
                    else : ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>Instants and Sorceries (<?php echo $instantsorcery; ?>)</b></i>
                    </td>
                </tr>
                <?php
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if ((strpos($card_type, 'Sorcery') !== false) or (strpos($card_type, 'Instant') !== false)) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $uniquecard_ref["$deckcard_no"]['layout'] = $row['layout'];
                                $uniquecard_ref["$deckcard_no"]['f1_type'] = $row['f1_type'] ?? null;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;
                            $cardcmc = round($cardcmc);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5) :
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                echo "</td>";
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage('[DEBUG]', "Hover image not rotated for deckdetail card '$cardname'");
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                            if (in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span
                                onmouseover=""
                                title="Delete"
                                style="cursor: pointer;"
                                onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;minusmain=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;plusmain=yes'"
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif; ?>
                <tr>
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td colspan='4'> <?php
                    elseif ($decktype == 'Wishlist') : ?>
                        <td colspan='5'> <?php
                    else : ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>Other (<?php echo $other; ?>)</b></i>
                    </td>
                </tr>
                <?php
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            (strpos($card_type, 'Sorcery') === false)
                            and (strpos($card_type, 'Instant') === false)
                            and (strpos($card_type, 'Creature') === false)
                            and (strpos($card_type, 'Land') === false)
                            and (
                                (strpos($card_type, 'Plane') === false || strpos($card_type, 'Planeswalker') !== false)
                            )
                            and (strpos($card_type, 'Phenomenon') === false)
                            and ($row['commander'] < 1)
                        ) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $uniquecard_ref["$deckcard_no"]['layout'] = $row['layout'];
                                $uniquecard_ref["$deckcard_no"]['f1_type'] = $row['f1_type'] ?? null;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;
                            $cardcmc = round($cardcmc);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5) :
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                echo "</td>";
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage('[DEBUG]', "Hover image not rotated for deckdetail card '$cardname'");
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                            if (in_array($decktype, $commander_decktypes)) :
                                $validcommander = false;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "This is a '$decktype' deck, checking if $cardname is valid as a commander"
                                );
                                $i = 0;
                                while ($i < count($valid_commander_text)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $valid_commander_text[$i]) == true
                                    ) :
                                        $validcommander = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $secondcommander = false;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "This is a '$decktype' deck, checking if $cardname is valid as a 2nd commander"
                                );
                                $i = 0;
                                while ($i < count($second_commander_text)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $second_commander_text[$i]) == true
                                    ) :
                                        $secondcommander = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $secondcommanderonly = false;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "This is a '$decktype' deck, checking if $cardname is valid as a 2nd commander only"
                                );
                                $i = 0;
                                while ($i < count($second_commander_only_type)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $second_commander_only_type[$i]) == true
                                    ) :
                                        $secondcommanderonly = true;
                                    endif;
                                    $i++;
                                endwhile;
                                echo "<td class='deckcardlistcenter noprint'>";
                                if ($validcommander == true) :
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Move to Commander"
                                        style="cursor: pointer;"
                                        onclick="window.location='<?php echo $cardActionBase; ?>&amp;commander=yes'"
                                        class='material-symbols-outlined'>
                                        person
                                    </span>
                                    <?php
                                elseif ($secondcommanderonly == true) :
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Move to Background"
                                        style="cursor: pointer;"
                                        onclick="window.location='<?php echo $cardActionBase; ?>&amp;partner=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
                                    class='material-symbols-outlined'>
                                    delete_forever
                                </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;minusmain=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;plusmain=yes'"
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif;
                ?>
                <tr>
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td colspan='4'> <?php
                    elseif ($decktype == 'Wishlist') : ?>
                        <td colspan='5'> <?php
                    else : ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>Lands (<?php echo $lands; ?>)</b></i>
                    </td>
                </tr>
                <?php
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        // Check if it's a land, unless it's a Land Creature (Dryad Arbor)
                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            (strpos($card_type, 'Land') !== false)
                            and (strpos($card_type, 'Land Creature') === false)
                        ) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif; ?>
                            <tr class='deckrow'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                echo "</td>";
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage('[DEBUG]', "Hover image not rotated for deckdetail card '$cardname'");
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                            if (in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span
                                onmouseover=""
                                title="Delete"
                                style="cursor: pointer;"
                                onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
                                class='material-symbols-outlined'>
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                    class='material-symbols-outlined'>
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;minusmain=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;plusmain=yes'"
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif;
                $msg->logMessage('[DEBUG]', "Decktype: $decktype");
                if ($decktype !== 'Wishlist') :
                    $msg->logMessage('[DEBUG]', "Not wishlist, adding a total row");?>
                    <tr style="border-bottom: 1pt solid black; border-top: 1pt solid black;"> <?php
                    if (in_array($decktype, $commander_decktypes)) :
                        $msg->logMessage('[DEBUG]', "Commander type colspan 2");
                        echo "<td colspan='2'><i><b>Total</b></i></td>";
                    else :
                            $msg->logMessage('[DEBUG]', "Not Commander type colspan 4");
                            echo "<td colspan='4'><i><b>Total</b></i></td>";
                    endif;?>
                        <td class='deckcardlistcenter'>
                            <i><b><?php echo $total; ?></b></i>
                        </td>
                        <td>&nbsp;</td>
                    </tr> <?php
                endif;
                if ($planes > 0) :?>
                    <tr>
                        <?php
                        if (in_array($decktype, $commander_decktypes)) : ?>
                            <td colspan='4'> <?php
                        elseif ($decktype == 'Wishlist') : ?>
                            <td colspan='5'> <?php
                        else : ?>
                            <td colspan='6'> <?php
                        endif; ?>
                        <i><b>Planes and Phenomena (<?php echo $planes; ?>)</b></i>
                        </td>
                    </tr>
                    <?php
                    if (mysqli_num_rows($result) > 0) :
                        mysqli_data_seek($result, 0);
                        while ($row = $result->fetch_assoc()) :
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $row['name'] = $row['flavor_name'];
                            endif;

                            // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;

                            if (
                                (strpos($card_type, 'Plane') !== false && strpos($card_type, 'Planeswalker') === false)
                                or (strpos($card_type, 'Phenomenon') !== false)
                            ) :
                                $quantity = $row["cardqty"];
                                $cardname = $row["name"];
                                $rarity = $row["rarity"];
                                $rowqty = 0;
                                $cardset = strtolower($row["setcode"]);
                                $cardref = str_replace('.', '-', $row['cardsid']);
                                $cardId = $row['cardsid'];
                                $cardnumber = $row["number_import"];
                                $layout = $row['layout'];
                                $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                                $imageFunction = $imageManager->getImage(
                                    $cardset,
                                    $cardId,
                                    $imgLocation,
                                    $layout,
                                    $twoCardDetailSections,
                                    false
                                );
                                if ($imageFunction['front'] == 'error') :
                                    $imageUrl = '/images/back.jpg';
                                else :
                                    $imageUrl = $imageFunction['front'];
                                endif;
                                $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");?>
                                <tr class='deckrow'>
                                <td class="deckcardname hoverTD">
                                    <?php
                                    $i = 0;
                                    $cdr_1_plus = false;
                                    while ($i < count($commander_multiples)) :
                                        if (
                                            isset($card_type)
                                            and str_contains($card_type, $commander_multiples[$i]) == true
                                        ) :
                                            $cdr_1_plus = true;
                                        endif;
                                        $i++;
                                    endwhile;
                                    if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                        echo "<a class='taphover' id='list-$cardref-taphover' "
                                            . "href='carddetail.php?id={$row['cardsid']}'>"
                                            . "$quantity $cardname "
                                            . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                            . "</a></a>";
                                    else :
                                        echo "<a class='taphover' id='list-$cardref-taphover' "
                                            . "href='carddetail.php?id={$row['cardsid']}'>"
                                            . "$cardname "
                                            . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                            . "</a></a>";
                                    endif;
                                    echo "</td>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image rotated for deckdetail card '$cardname'"
                                    );
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                                <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                    <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                    <img
                                        alt='<?php echo $deckcardname;?>'
                                        class='deckcardimg'
                                        data-cardid="<?php echo $row['cardsid']; ?>"
                                        data-front-src="<?php echo $imageUrl; ?>"
                                        src='<?php echo $imageUrl;?>'
                                    ></a>
                                </div> <?php
                                $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                                if (in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    echo "</td>";
                                endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;deletemain=yes'"
                                    class='material-symbols-outlined'>
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                if ($decktype != 'Wishlist') :
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;maintoside=yes'"
                                        class='material-symbols-outlined'>
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                endif;
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>";
                                    ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;minusmain=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;plusmain=yes'"
                                        class='material-symbols-outlined'>
                                        add
                                    </span>
                                    <?php
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                            endif;
                        endwhile;
                    endif;
                endif;
// SIDEBOARD
                if ($decktype != 'Wishlist' && $side > 0) :?>
                    <tr style="border-top: 1pt solid black;">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) :
                            ?>
                            <td colspan='4'>
                            <?php
                        else :
                            ?>
                            <td colspan='6'>
                            <?php
                        endif;

                        ?>
                        <i><b>Sideboard</b></i>
                        </td>
                    </tr>
                    <?php
                    $sidetotal = 0;
                    if (mysqli_num_rows($sideresult) > 0) :
                        mysqli_data_seek($sideresult, 0);
                        while ($row = $sideresult->fetch_assoc()) :
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $row['name'] = $row['flavor_name'];
                            endif;
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;
                            $illegal_tag = $red_font_tag;
                            $wrong_colour_tag = $firebrick_font_tag;
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $quantity = $row["sideqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            $msg->logMessage('[DEBUG]', "Sideboard card '$cardname ($cardset $cardnumber)'");
                            if (
                                $deck_legality_list != ''
                                and (
                                    (strpos($card_type, 'Plane') === false
                                    || strpos($card_type, 'Planeswalker') !== false)
                                )
                                and strpos($card_type, 'Phenomenon') === false
                            ) :
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Checking legality for sideboard card '$cardname' ('$card_type')"
                                );
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $msg->logMessage('[DEBUG]', "Card legality is 'legal' or null");
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $msg->logMessage('[DEBUG]', "Card legality is unknown");
                                    $illegal_tag = '';
                                endif;
                            else :
                                $msg->logMessage('[DEBUG]', "Card legality is not needed");
                                $illegal_tag = '';
                            endif;
                            if (
                                in_array($decktype, $commander_decktypes)
                                and $illegal_tag == ''
                                and (
                                    (strpos($card_type, 'Plane') === false
                                    || strpos($card_type, 'Planeswalker') !== false)
                                )
                                and (strpos($card_type, 'Phenomenon') === false)
                            ) :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;

                            // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;?>

                            <tr class='deckrow'>
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                                $deckcardname = str_replace("'", '&#39;', $cardname);
                                ?>
                                <td class="deckcardname hoverTD">
                                <?php
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='listside-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                        . "</a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='listside-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                        . "</a>";
                                endif;
                                ?>
                            </td>
                            <?php
                            if (in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;deleteside=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;sidetomain=yes'"
                                    class='material-symbols-outlined'>
                                    arrow_upward
                                </span>
                                <?php
                                echo "</td>";
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Remove one"
                                        style="cursor: pointer;"
                                        onclick="window.location='<?php echo $cardActionBase; ?>&amp;minusside=yes'"
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
                                    onclick="window.location='<?php echo $cardActionBase; ?>&amp;plusside=yes'"
                                    class='material-symbols-outlined'>
                                    add
                                </span>
                                    <?php
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage('[DEBUG]', "Hover image not rotated for deckdetail card '$cardname'");
                            endif;
                                ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "listside-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div>
                            <?php
                            $sidetotal = $sidetotal + $quantity;
                        endwhile;
                    endif;?>
                    <tr style="border-bottom: 1pt solid black; border-top: 1pt solid black;">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) :
                            ?>
                            <td colspan="2">
                            <?php
                        else :
                            ?>
                            <td colspan='4'>
                            <?php
                        endif;?>
                            <i><b>Total sideboard</b></i>
                        </td>

                        <td colspan="1" class='deckcardlistcenter'>
                            <i><b><?php echo $sidetotal; ?></b></i>
                        </td>
                        <td colspan="1">&nbsp;</td>
                    </tr> <?php
                else :
                    $sidetotal = 0;
                endif; ?>
            </table>
        </div>
        <div id="decknotesdiv">
            <?php
            if ((in_array($decktype, $hundredcarddecks) and $total < 100)) :
                $warnings = true;
                $hundred_not_enough = true;
            endif;
            if ((in_array($decktype, $sixtycarddecks) and $total < 60)) :
                $warnings = true;
                $sixty_not_enough = true;
            endif;
            if ((in_array($decktype, $fiftycarddecks) and $total < 50)) :
                $warnings = true;
                $fifty_not_enough = true;
            endif;
            if ($illegal_cards == true) :
                $warnings = true;
            endif;
            if ($deck_colour_mismatch == true) :
                $warnings = true;
            endif;

            if (isset($warnings)) :
                echo "<h4>&nbsp;Warnings</h4>";
                echo "<ul style='margin-right: 20px;'>";
                if (isset($secondcommandername)) :
                    echo "<li>You have a second commander ('<i>$secondcommandername</i>') - check rules and "
                        . "validity with your primary commander</li>";
                endif;
                if (isset($hundred_not_enough)) :
                    echo "<li>Your commander deck doesn't have enough cards for legal play</li>";
                endif;
                if (isset($sixty_not_enough)) :
                    echo "<li>Your deck doesn't have enough cards for legal play</li>";
                endif;
                if (isset($fifty_not_enough)) :
                    echo "<li>Your deck doesn't have enough cards for legal play</li>";
                endif;
                if (isset($illegal_cards) and $illegal_cards == true) :
                    echo "<li>Your deck contains <span $red_font_tag>cards </span> not legal in this format</li>";
                endif;
                if (isset($deck_colour_mismatch) and $deck_colour_mismatch == true) :
                    echo "<li>Your deck contains <span $firebrick_font_tag>cards </span> not in your Commander(s) "
                        . "colour identity</li>";
                endif;
                echo "</ul>";
            endif;
            ?>
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
            <script>
                function submitForm() {
                    var notesTextarea = $('#notes');
                    var sidenotesTextarea = $('#sidenotes');
                    var saveButton = $('.save_icon');

                    var notes = notesTextarea.val();
                    var sidenotes = sidenotesTextarea.length ? sidenotesTextarea.val() : '';
                    var deck = $('#updatenotesform').find('input[name="deck"]').val();

                    $.ajax({
                        url: 'ajax/ajaxdecknotes.php',
                        type: 'POST',
                        data: {
                            newnotes: notes,
                            newsidenotes: sidenotes,
                            decknumber: deck
                        },
                        dataType: 'json',
                        success: function(result) {
                            if (result.success) {
                                // Reset the initial values to the newly saved content
                                initialNotesValue = notesTextarea.val();
                                initialSidenotesValue = sidenotesTextarea.val();

                                // Disable the save button again
                                saveButton.prop('disabled', true);
                            } else {
                                alert('Error updating notes: ' + result.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                            alert('An error occurred while updating the notes.');
                        }
                    });
                }
            </script>
            <hr id='deckline' class='hr324'>
            <?php
            if ($total + $sidetotal > 0 and $decktype != 'Wishlist') :
                ?>
                <h4>&nbsp;Mana value</h4>
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
                if (($total - $lands) != 0) :
                    $avgcmc = round(($cmctotal / ($total - $lands)), 2);
                    echo "<br>Average mana value = $avgcmc" ;
                else :
                    echo "<br>Average mana value = N/A";
                endif;
                if ($w + $u + $b + $r + $g + $c + $gw + $gu + $gb + $gr + $gg + $gc > 0) :
                    $totalpips = $w + $u + $b + $r + $g + $c;
                    $totalmana = $gw + $gu + $gb + $gr + $gg + $gc;
                    $w_percent = $u_percent = $b_percent = $r_percent = $g_percent
                        = $gw_percent = $gu_percent = $gb_percent = $gr_percent
                        = $gg_percent = $c_percent = $gc_percent = 0;
                    if ($w > 0) :
                        $w_percent = number_format($w / $totalpips * 100, 0);
                    endif;
                    if ($u > 0) :
                        $u_percent = number_format($u / $totalpips * 100, 0);
                    endif;
                    if ($b > 0) :
                        $b_percent = number_format($b / $totalpips * 100, 0);
                    endif;
                    if ($r > 0) :
                        $r_percent = number_format($r / $totalpips * 100, 0);
                    endif;
                    if ($g > 0) :
                        $g_percent = number_format($g / $totalpips * 100, 0);
                    endif;
                    if ($c > 0) :
                        $c_percent = number_format($c / $totalpips * 100, 0);
                    endif;
                endif;
                if ($gw + $gu + $gb + $gr + $gg + $gc > 0) :
                    $totalmana = $gw + $gu + $gb + $gr + $gg + $gc;
                    if ($gw > 0) :
                        $gw_percent = number_format($gw / $totalmana * 100, 0);
                    endif;
                    if ($gu > 0) :
                        $gu_percent = number_format($gu / $totalmana * 100, 0);
                    endif;
                    if ($gb > 0) :
                        $gb_percent = number_format($gb / $totalmana * 100, 0);
                    endif;
                    if ($gr > 0) :
                        $gr_percent = number_format($gr / $totalmana * 100, 0);
                    endif;
                    if ($gg > 0) :
                        $gg_percent = number_format($gg / $totalmana * 100, 0);
                    endif;
                    if ($gc > 0) :
                        $gc_percent = number_format($gc / $totalmana * 100, 0);
                    endif;
                endif;
                if ($decktype != 'Wishlist') :?>
                    <table style="width: 95%;">
                        <tr>
                            <td style="text-align: center; width: 20%;"><b>Mana:</b></td>
                            <td style="text-align: center;"><b>Costs</b></td>
                            <td style="text-align: center;"><b>Sources</b></td>
                        </tr><?php
                        if ($w + $gw > 0) : ?>
                        <tr>
                            <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{W}"); ?> </td>
                            <td style="text-align: center;"><?php echo $w === 0 ? '-' : "$w ($w_percent%)"; ?> </td>
                            <td style="text-align: center;"><?php echo $gw === 0 ? '-' : "$gw ($gw_percent%)"; ?> </td>
                        </tr><?php
                        endif;
                        if ($u + $gu > 0) : ?>
                        <tr>
                            <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{U}"); ?> </td>
                            <td style="text-align: center;"><?php echo $u === 0 ? '-' : "$u ($u_percent%)"; ?> </td>
                            <td style="text-align: center;"><?php echo $gu === 0 ? '-' : "$gu ($gu_percent%)"; ?> </td>
                        </tr><?php
                        endif;
                        if ($b + $gb > 0) : ?>
                        <tr>
                            <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{B}"); ?> </td>
                            <td style="text-align: center;"><?php echo $b === 0 ? '-' : "$b ($b_percent%)"; ?> </td>
                            <td style="text-align: center;"><?php echo $gb === 0 ? '-' : "$gb ($gb_percent%)"; ?> </td>
                        </tr><?php
                        endif;
                        if ($r + $gr > 0) : ?>
                        <tr>
                            <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{R}"); ?> </td>
                            <td style="text-align: center;"><?php echo $r === 0 ? '-' : "$r ($r_percent%)"; ?> </td>
                            <td style="text-align: center;"><?php echo $gr === 0 ? '-' : "$gr ($gr_percent%)"; ?> </td>
                        </tr><?php
                        endif;
                        if ($g + $gg > 0) : ?>
                        <tr>
                            <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{G}"); ?> </td>
                            <td style="text-align: center;"><?php echo $g === 0 ? '-' : "$g ($g_percent%)"; ?> </td>
                            <td style="text-align: center;"><?php echo $gg === 0 ? '-' : "$gg ($gg_percent%)"; ?> </td>
                        </tr><?php
                        endif;
                        if ($c + $gc > 0) : ?>
                        <tr>
                            <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{C}"); ?> </td>
                            <td style="text-align: center;"><?php echo $c === 0 ? '-' : "$c ($c_percent%)"; ?> </td>
                            <td style="text-align: center;"><?php echo $gc === 0 ? '-' : "$gc ($gc_percent%)"; ?> </td>
                        </tr><?php
                        endif; ?>
                    </table> <?php
                endif;
                $a = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
                $formattedDeckValue = $a->format($deckvalue);
                $msg->logMessage('[DEBUG]', "Formatted value = $formattedDeckValue");
                if (isset($rate) and $rate > 0) :
                    $b = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
                    $b->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $targetCurrency);
                    $currencySymbol = $b->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
                    $localvalue = $b->format($deckvalue * $rate);
                    echo "<b>Deck value</b><br>(TCGplayer) = " . $formattedDeckValue . " ($localvalue)";
                else :
                    echo "<b>Deck value</b><br>(TCGplayer) = " . $formattedDeckValue;
                endif;
            endif;
            if (isset($uniquecard_ref) and count($uniquecard_ref) > 6 and $decktype != 'Wishlist') : ?>
                <script type="text/javascript">
                    // AJAX call to refresh the table and rebind events
                    function refreshTable() {
                        var xhr = new XMLHttpRequest();
                        var data = JSON.stringify({
                            uniquecard_ref: <?php echo json_encode($uniquecard_ref); ?>,
                            include_check: true
                        });
                        xhr.open('POST', 'ajax/ajaxrandomdraw.php', true);
                        xhr.setRequestHeader('Content-Type', 'application/json');
                        xhr.onreadystatechange = function () {
                            if (xhr.readyState == 4 && xhr.status == 200) {
                                document.getElementById('table-container').innerHTML = xhr.responseText;
                                // Rebind events for newly loaded content
                                window.bindRandomCardEvents();
                                // Ensure layout recalculations
                                window.dispatchEvent(new Event('resize'));
                            }
                        };
                        xhr.send(data);
                    }

                    // Attach the refreshTable function to the button click event
                    jQuery(document).ready(function ($) {
                        $('button.profilebutton').on('click', refreshTable);
                    });
                </script>
                <h4>Random draw</h4>
                <button class='profilebutton' onclick="refreshTable()">NEW DRAW</button>
                <div id="table-container">
                    <?php
                    define('INCLUDE_CHECK', true);
                    include 'ajax/ajaxrandomdraw.php'; ?>
                </div>
                <?php
            endif;
            if ($decktype != 'Wishlist') : // Condense to 2 columns for wishlists
                ?>
        </div>
        <div id='deckfunctions'>
                <?php
            endif;
            if ($total + $sidetotal > 0) : ?>
                <h4>Deck lists</h4>
                <?php
                $filename = preg_replace('/[^\w]/', '', $deckName);
                ?>
                <table style="width:100%;">
                    <tr style="height:36px;">
                        <td>Export formatted card list:</td>
                        <td><form action="dltext.php" method="POST">
                                <input class='profilebutton' type="submit" value="DECKLIST">
                                <?php echo "<input type='hidden' name='decknumber' value='$deckNumber'>"; ?>
                            </form>
                        </td>
                    </tr>
                    <?php
                    if ($requiredlist != '') :
                        $requiredlist = htmlspecialchars($requiredlist, ENT_QUOTES, 'UTF-8');
                        $requiredbuy = htmlspecialchars($requiredbuy, ENT_QUOTES, 'UTF-8');
                        $filename_missing = preg_replace('/[^\w]/', '', $deckName . '_missing');
                        $msg->logMessage('[DEBUG]', "Required list = $requiredlist");
                        $msg->logMessage('[DEBUG]', "Missing filename = $filename_missing");?>
                        <script type="text/javascript">
                            document.body.style.cursor='default';
                        </script>
                        <tr style="height:36px;">
                            <td>Missing from My Collection:</td>
                            <td><form action="dltext.php" method="POST">
                                    <input class='profilebutton' type="submit" value="MISSING">
                                    <?php echo "<input type='hidden' name='text' value='$requiredlist'>"; ?>
                                    <?php echo "<input type='hidden' name='filename' value='$filename_missing'>"; ?>
                                </form>
                            </td>
                        </tr>
                        <?php
                        $tcgUrl = "https://store.tcgplayer.com/list/selectproductmagic.aspx"
                            . "?partner=MTGCOLLECT&c={$requiredbuy}";
                        ?>
                        <tr style="height:36px;">
                            <td>Buy missing:</td>
                            <td>
                                <a
                                    href="<?php echo $tcgUrl; ?>"
                                    target="_blank"
                                    class="profilebutton tcgbuybutton"
                                >
                                    TCGPLAYER
                                </a>
                            </td>
                        </tr>
                        <?php
                    else : ?>
                        <tr style="height:48px;">
                            <td colspan="2">(No cards missing from My Collection)</td>
                        </tr>
                        <?php
                    endif; ?>
                </table> <?php
            endif;
            ?>
            <h4>Add cards</h4>
            <form action="deckdetail.php"  method="GET">
                <!-- Hovering help button -->
                <span id="help-button" class="material-symbols-outlined" onclick="toggleInfoBox()">help</span>

                <textarea class='textinput' rows="3" cols="47" name="quickadd"></textarea>
                <br>
                <input class='inline_button stdwidthbutton noprint' type="submit" value="ADD">
                <?php echo "<input type='hidden' name='deck' value='$deckNumber'>"; ?>
            </form>
            <h4>Text or csv file</h4>
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
                    var csrfToken = <?php echo json_encode(generateCsrfToken()); ?>;

                    // Create form data
                    var formData = new FormData();
                    formData.append('decknumber', deckNumber);
                    formData.append('delete', '');
                    formData.append('csrf_token', csrfToken);

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
                        var csrfToken = <?php echo json_encode(generateCsrfToken()); ?>;

                        // Append the deck number to the form data
                        var formData = new FormData(this);
                        formData.append('decknumber', deckNumber);
                        formData.append('update', '');
                        formData.append('csrf_token', csrfToken);

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
                                    // var imageUrl = 'cardimg/deck_photos/<?php echo $deckNumber; ?>.jpg';
                                    var imageUrl = 'deckimage.php?deck=<?php echo $deckNumber; ?>';
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
                function importPrep()
                    {
                        document.body.style.cursor='wait';
                    }
            </script>
            <form enctype='multipart/form-data' action='?' method='post'>
                <label class='importlabel'>
                    <input id='importfile' type='file' name='filename'>
                    <span>SELECT</span>
                </label>
                <input
                    class='profilebutton'
                    id='importsubmit'
                    type='submit'
                    name='import'
                    value='IMPORT'
                    disabled
                    onclick='importPrep()'
                >
                <input type='hidden' id='deck' name='deck' value="<?php echo $deckNumber; ?>">
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
                        onclick="deletePhoto()"
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
