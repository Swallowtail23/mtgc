<?php

/*
Version:     5.15
Date:        11/01/26
Name:        decks.php
Purpose:     Main decks list page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;
use MTG\Core\Message;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php'; // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php'; // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php'; // Setup page variables
SessionManager::forcePasswordChange($appConfig); // Check if user is disabled or needs to change password
$msg = new Message($appConfig);

//page specific variables
$newdeck = isset($_POST['newdeck']) ? 'yes' : '';
$deckName = isset($_POST['deckname'])
    ? filter_input(INPUT_POST, 'deckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES)
    : '';
$deletedeck = isset($_POST['deletedeck']) ? 'yes' : '';
$decktodelete = isset($_POST['decktodelete'])
    ? filter_input(INPUT_POST, 'decktodelete', FILTER_SANITIZE_NUMBER_INT)
    : '';
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title> <?php echo $siteTitleEsc;?> - decks</title>
    <link rel="manifest" href="/manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script type="text/javascript">
        $(function() {
            $('tbody tr[data-href]').addClass('clickable').click( function() {
            window.location = $(this).attr('data-href');
             });
        });

        $(function() {
            $("#deletedeck").submit(function(event){
                if (!confirm("Confirm OK to delete deck?")){
                    event.preventDefault();
                }
            });
        });

        function closeMe( obj ) {
            obj.style.display = 'none';
        };

        function updateButtonState(elementId, value) {
            var button = document.getElementById(elementId);
            if (value === "") {
                button.disabled = true;
                button.style.cursor = 'not-allowed';
                button.classList.remove('inline_button');
                button.classList.add('inline_button_disabled');
            } else {
                button.disabled = false;
                button.style.cursor = 'pointer';
                button.classList.add('inline_button');
                button.classList.remove('inline_button_disabled');
            }
        };

        function createready() {
            var newdeckname = document.getElementById("newdeckname");
            updateButtonState("createsubmit", newdeckname.value);
        };

        function deleteready() {
            var deckselect = document.getElementById("deckselect");
            updateButtonState("deletebutton", deckselect.value);
        };
    </script>
</head>

<body class="body">
<?php include_once("includes/analyticstracking.php");
// Start building the page here, so errors show in the website template
// Includes first - menu and header
require('includes/overlays.php');
require('includes/header.php');
require('includes/menu.php'); //mobile menu

// Next the main DIV section
?>
<div id="page">
    <div class="staticpagecontent">
        <?php

        // Create a new deck
        if ($newdeck == "yes") :
            if ($deckName == '') :
                ?>
                <div class="msg-new error-new" onclick='closeMe(this)'><span>Name can't be empty</span>
                    <br>
                    <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
                </div>
                <?php
            else :
                $msg->logMessage('[NOTICE]', "Calling Deckmanager->addDeck: '$user/$deckName'");
                $obj = new DeckManager(
                    $db,
                    $appConfig,
                    $gameRules,
                    $userEmail
                );
                // returns array with success flag, and if success flag is 1, the deck number (otherwise NULL)
                $decksuccess = $obj->addDeck($user, $deckName);
            endif;
        endif;

        // Delete a deck
        if ($deletedeck == "yes") :
            $msg->logMessage('[NOTICE]', "Calling Deckmanager->deleteDeck: '($user) $decktodelete'");
            $obj = new DeckManager(
                $db,
                $appConfig,
                $gameRules,
                $userEmail
            );
            $obj->delDeck($decktodelete);
        endif;
        // List decks
        ?>
        <div id='decklistdiv'>
        <h2 class='h2pad'>My Decks</h2>
        <?php
        if (
            $sqlquery = $db->execute_query(
                "SELECT * FROM decks WHERE owner = ? ORDER BY type ASC, deckname ASC",
                [$user]
            )
        ) : ?>
            <table class="decklist">
                <?php
                $typeheader = '';
                while ($row = $sqlquery->fetch_assoc()) :
                    if ($row['type'] == null) :
                        $row['type'] = 'Not set';
                    endif;
                    if ($typeheader == '' or $row['type'] != $typeheader) :
                        echo "<tr><td><b>{$row['type']}</b></td></tr>";
                        $typeheader = $row['type'];
                    endif;?>
                    <tr class='resultsrow' style='cursor: pointer;'
                        <?php echo "data-href='deckdetail.php?deck={$row['decknumber']}'"; ?>>
                    <?php echo "<td class='decklist_name'>" . $row['deckname'] . "</td>"; ?>
                    </tr>
                    <?php
                endwhile;?>
            </table>
            </div>
            <div id='deckoperations'>
            <h3>Add a new deck</h3>
                <form name="newdeck" action="decks.php" method="post">
                    <input type='hidden' name="newdeck" value="yes">
                    <input class='textinput' onkeyup='createready()' title="Please enter deck title"
                        placeholder="DECK TITLE" id="newdeckname" name="deckname" type="text" size="24"
                        maxlength="150" />
                    <br><br>
                <input class='inline_button_disabled stdwidthbutton' id="createsubmit" style='cursor: not-allowed;'
                    type="submit" value="CREATE DECK" disabled/>
            </form>
            <h3>Delete a deck</h3>
            <form id="deletedeck" action="decks.php" method="POST">
                <input type='hidden' name="deletedeck" value="yes">
                <select id='deckselect' name='decktodelete' onchange='deleteready()'>
                    <option selected='selected' disabled='disabled'>Pick one</option>
                    <?php
                    mysqli_data_seek($sqlquery, 0);
                    while ($row = $sqlquery->fetch_assoc()) :
                        echo "<option value='{$row['decknumber']}'>{$row['deckname']}</option>";
                    endwhile;
                    ?>
                </select><br><br>
                <input class='inline_button_disabled stdwidthbutton' style='cursor: not-allowed;' id="deletebutton"
                    type="submit" value="DELETE DECK" disabled>
            </form>
            <br> &nbsp;
            <?php
        else :
            throw new Exception('[ERROR] decks.php: List decks SQL error');
        endif;
        ?>
        </div>
    </div>
</div>

<?php require('includes/footer.php'); ?>
</body>
</html>
