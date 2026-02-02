<?php

/*
Version:     5.47
Date:        02/02/26
Name:        decks.php
Purpose:     Main decks list page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;

// Bootstrap
$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$cssver                     = (string) $ctx->meta('cssver', '');
$serviceWorkerVersion       = (string) $ctx->meta('serviceWorkerVersion', 'v6');
$sessionUser                = $ctx->sessionUser();

$siteTitle                  = (string) $appConfig->general('title', '');
$tier                       = (string) $appConfig->general('tier', 'prod');
$copyright                  = (string) $appConfig->general('copyright', '');
$user                       = $sessionUser->id();
$userEmail                  = $sessionUser->email();

// Content
// page specific variables
$newdeck = isset($_POST['newdeck']) ? 'yes' : '';
$deckName = isset($_POST['deckname'])
    ? filter_input(INPUT_POST, 'deckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES)
    : '';
$deletedeck = isset($_POST['deletedeck']) ? 'yes' : '';
$decktodeleteRaw = $_POST['decktodelete'] ?? [];
if (!is_array($decktodeleteRaw)) :
    $decktodeleteRaw = [$decktodeleteRaw];
endif;
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
$csrfToken = SessionManager::generateCsrfToken();
$csrfTokenEsc = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
$rulesValidTypes = $gameRules->getArray('validtypes');
$rulesCommanderDeckTypes = $gameRules->getArray('commander_decktypes');
if (!is_array($rulesValidTypes)) :
    $rulesValidTypes = [];
endif;
if (!is_array($rulesCommanderDeckTypes)) :
    $rulesCommanderDeckTypes = [];
endif;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title> <?php echo $siteTitleEsc;?> - decks</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo $cssver?>.css?v=<?php echo $serviceWorkerVersion; ?>"
    >
    <?php include APP_ROOT . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script type="text/javascript">
        $(function() {
            $('tbody tr[data-href]').addClass('clickable').click(function () {
                if (document.body.classList.contains('deck-edit-mode')) {
                    var $checkbox = $(this).find('.deck-delete-checkbox');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', !$checkbox.prop('checked'));
                        deleteready();
                    }
                    return;
                }
                window.location = $(this).attr('data-href');
            });
        });

        $(function() {
            $("#deletedecks").submit(function(event){
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
            var selectedCount = document.querySelectorAll('.deck-delete-checkbox:checked').length;
            updateButtonState("deletebutton", selectedCount > 0 ? "selected" : "");
            updateButtonState("exportbutton", selectedCount > 0 ? "selected" : "");
            var selectionActions = document.getElementById("deck-selection-actions");
            if (selectionActions) {
                selectionActions.classList.toggle('is-visible', selectedCount > 0);
            }
        };
    </script>
    <script type="text/javascript">
        window.mtgDecksConfig = {
            csrfToken: <?php echo json_encode($csrfToken); ?>,
            commanderDeckTypes: <?php echo json_encode(array_values($rulesCommanderDeckTypes)); ?>
        };

        $(function() {
            var $importFile = $('#importfile');
            function setImportButtonState(hasFile) {
                var $importSubmit = $('#importsubmit');
                if (hasFile) {
                    $importSubmit.prop('disabled', false).show();
                } else {
                    $importSubmit.prop('disabled', true).hide();
                }
            }

            if ($importFile.length && $importFile.val() === '') {
                setImportButtonState(false);
            }

            function submitDeckImport(formData) {
                var $form = $('#import-form');
                if ($form.data('busy')) {
                    return;
                }
                $form.data('busy', true);
                document.body.style.cursor = 'wait';
                formData.append('csrf_token', window.mtgDecksConfig.csrfToken);
                $.ajax({
                    url: 'ajax/ajaxdecksimport.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function (response) {
                    if (!response || response.success !== true || !response.decknumber) {
                        alert('That did not work. Please try again.');
                        return;
                    }
                    window.location.href = 'deckdetail.php?deck=' + response.decknumber;
                }).fail(function () {
                    alert('That did not work. Please try again.');
                }).always(function () {
                    $form.data('busy', false);
                    document.body.style.cursor = '';
                    $('#importfile').val('');
                    setImportButtonState(false);
                });
            }

            $(document).off('change.decks', '#importfile').on('change.decks', '#importfile', function () {
                var hasFile = $(this).val() !== '';
                setImportButtonState(hasFile);
            });

            $(document).off('submit.decks', '#import-form').on('submit.decks', '#import-form', function (event) {
                event.preventDefault();
                var fileInput = $('#importfile')[0];
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    alert('Please select a file to import');
                    return;
                }
                var formData = new FormData($('#import-form')[0]);
                submitDeckImport(formData);
            });

            $(document).off('click.decks', '#importpaste').on('click.decks', '#importpaste', function () {
                if (!navigator.clipboard || !navigator.clipboard.readText) {
                    var fallbackText = window.prompt('Paste your deck list');
                    if (!fallbackText) {
                        return;
                    }
                    var fallbackData = new FormData();
                    fallbackData.append('paste', fallbackText);
                    submitDeckImport(fallbackData);
                    return;
                }
                navigator.clipboard.readText().then(function (text) {
                    if (!text || text.trim() === '') {
                        alert('Clipboard is empty');
                        return;
                    }
                    var formData = new FormData();
                    formData.append('paste', text);
                    submitDeckImport(formData);
                }).catch(function () {
                    alert('Clipboard access denied');
                });
            });
        });
    </script>
    <script type="text/javascript">
        $(function() {
            $(document)
                .off('click.decks', '#deck-edit-toggle')
                .on('click.decks', '#deck-edit-toggle', function () {
                var isEditing = document.body.classList.toggle('deck-edit-mode');
                $('.deck-delete-cell').toggle(isEditing);
                if (!isEditing) {
                    exitEditMode();
                    return;
                }
                deleteready();
            });

            function exitEditMode() {
                document.body.classList.remove('deck-edit-mode');
                $('.deck-delete-cell').hide();
                $('.deck-delete-checkbox').prop('checked', false);
                $('#deck-type-bulk').prop('selectedIndex', 0);
                deleteready();
            }

            $(document)
                .off('keydown.decks', document)
                .on('keydown.decks', document, function (event) {
                if (event.key !== 'Escape') {
                    return;
                }
                if (!document.body.classList.contains('deck-edit-mode')) {
                    return;
                }
                exitEditMode();
            });

            $(document)
                .off('click.decks', '.deck-delete-checkbox')
                .on('click.decks', '.deck-delete-checkbox', function (event) {
                event.stopPropagation();
                deleteready();
            });

            $(document)
                .off('click.decks', '.deck-delete-label')
                .on('click.decks', '.deck-delete-label', function (event) {
                event.stopPropagation();
            });

            $(document)
                .off('change.decks', '#deck-type-bulk')
                .on('change.decks', '#deck-type-bulk', function () {
                var selectedType = $(this).val();
                if (!selectedType) {
                    return;
                }
                var selectedDecks = document.querySelectorAll('.deck-delete-checkbox:checked');
                if (!selectedDecks.length) {
                    $(this).prop('selectedIndex', 0);
                    return;
                }
                if (
                    window.mtgDecksConfig.commanderDeckTypes
                        .indexOf(selectedType) !== -1
                    && !confirm(
                        "Changing decks to Commander types may result in cards being removed "
                            + "to enforce singleton limits. Continue?"
                    )
                ) {
                    $(this).prop('selectedIndex', 0);
                    return;
                }
                var requests = [];
                selectedDecks.forEach(function (checkbox) {
                    requests.push(
                        $.ajax({
                            url: 'ajax/ajaxdecktype.php',
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                decknumber: checkbox.value,
                                updatetype: selectedType,
                                csrf_token: window.mtgDecksConfig.csrfToken
                            }
                        })
                    );
                });
                $.when.apply($, requests).done(function () {
                    var args = arguments;
                    var responses = [];
                    if (requests.length === 1) {
                        responses = [args[0]];
                    } else {
                        responses = $.map(args, function (item) {
                            return item[0];
                        });
                    }
                    var failed = responses.some(function (response) {
                        return !response || response.success !== true;
                    });
                    if (failed) {
                        alert('Deck type update failed for one or more decks.');
                    }
                    exitEditMode();
                    window.location.reload();
                }).fail(function () {
                    alert('Deck type update failed. Please try again.');
                    exitEditMode();
                    window.location.reload();
                });
            });

            function positionDeckActions() {
                var $selectionActions = $('#deck-selection-actions');
                var $mobileAnchor = $('#deck-selection-anchor');
                var $desktopAnchor = $('#deck-selection-operations-anchor');
                if (!$selectionActions.length) {
                    return;
                }
                if (window.innerWidth <= 513) {
                    if ($mobileAnchor.length) {
                        $selectionActions.insertAfter($mobileAnchor);
                    }
                } else if ($desktopAnchor.length) {
                    $selectionActions.insertAfter($desktopAnchor);
                }
            }

            $(document)
                .off('submit.decks', '#exportdecks')
                .on('submit.decks', '#exportdecks', function (event) {
                var $form = $(this);
                $form.find('input[name="decktoexport[]"]').remove();
                var selectedDecks = document.querySelectorAll('.deck-delete-checkbox:checked');
                if (!selectedDecks.length) {
                    event.preventDefault();
                    return;
                }
                selectedDecks.forEach(function (checkbox) {
                    var $input = $('<input>', {
                        type: 'hidden',
                        name: 'decktoexport[]',
                        value: checkbox.value
                    });
                    $form.append($input);
                });
            });

            positionDeckActions();
            $(window).on('resize.decks', function () {
                positionDeckActions();
            });
        });
    </script>
</head>

<body class="body">
<?php include_once APP_ROOT . '/includes/analyticstracking.php';
// Start building the page here, so errors show in the website template
// Includes first - menu and header
require APP_ROOT . '/includes/overlays.php';
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/menu.php'; //mobile menu

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
            $msg->logMessage('[NOTICE]', "Deck delete requested from decks.php for user $user");
            $obj = new DeckManager(
                $db,
                $appConfig,
                $gameRules,
                $userEmail
            );
            foreach ($decktodeleteRaw as $deckToDeleteRaw) :
                $deckToDelete = (int) filter_var($deckToDeleteRaw, FILTER_SANITIZE_NUMBER_INT);
                if ($deckToDelete <= 0) :
                    $msg->logMessage('[DEBUG]', "Deck delete skipped invalid id: '$deckToDeleteRaw'");
                    continue;
                endif;
                $msg->logMessage('[NOTICE]', "Calling Deckmanager->deleteDeck: '($user) $deckToDelete'");
                $obj->delDeck($deckToDelete);
            endforeach;
        endif;
        // List decks
        ?>
        <div id='decklistdiv'>
        <h2 class='h2pad'>
            My Decks
            &nbsp;
            <span
                id="deck-edit-toggle"
                title="Edit"
                onclick="return false;"
                onmouseover=""
                style="cursor: pointer;"
                class='material-symbols-outlined'>
                edit
            </span>
        </h2>
        <div id="deck-selection-anchor"></div>
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
                    <?php
                    echo "<td class='decklist_name'>";
                    $deckDeleteId = 'deckdelete_' . (int) $row['decknumber'];
                    echo "<span class='deck-delete-cell checkbox-group' style='display: none;'>";
                    echo "<input id='{$deckDeleteId}' class='deck-delete-checkbox checkbox' type='checkbox' "
                        . "name='decktodelete[]' value='{$row['decknumber']}' form='deletedecks' />";
                    echo "<label class='deck-delete-label' for='{$deckDeleteId}' aria-label='Select deck to delete'>";
                    echo "<span class='check'></span><span class='box'></span>";
                    echo "</label>";
                    echo "</span>";
                    echo htmlspecialchars($row['deckname'], ENT_QUOTES, 'UTF-8');
                    echo "</td>";
                    ?>
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
            <h3>Import a deck</h3>
            <form id="import-form" enctype='multipart/form-data'>
                <div class="import-title">From file:</div>
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
                    style='display: none;'
                >
                <div class="import-title">From clipboard:</div>
                <input
                    class='profilebutton'
                    id='importpaste'
                    type='button'
                    value='PASTE'
                >
            </form>
            <div id="deck-selection-operations-anchor"></div>
            <div id="deck-selection-actions">
                <h3 class="deck-delete-title">Edit selected decks</h3>
                <div class="deck-selection-block">
                    Change type: <br>
                    <select id="deck-type-bulk" class='dropdown' size="1" name="bulkdecktype">
                        <option selected="selected" disabled="disabled">Pick one</option>
                        <?php foreach ($rulesValidTypes as $deckType) : ?>
                            <option value="<?php echo htmlspecialchars($deckType, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($deckType, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="deck-selection-block">
                    <form id="exportdecks" action="decksexport.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfTokenEsc; ?>">
                        <input
                            class='profilebutton'
                            id="exportbutton"
                            type="submit"
                            value="EXPORT"
                            disabled
                        >
                    </form>
                </div>
                <div class="deck-selection-block">
                    <form id="deletedecks" action="decks.php" method="POST">
                        <input type='hidden' name="deletedeck" value="yes">
                        <input
                            class='profilebutton'
                            id="deletebutton"
                            type="submit"
                            value="DELETE"
                            disabled
                        >
                    </form>
                </div>
            </div>
            <br> &nbsp;
            <?php
        else :
            throw new Exception('[ERROR] decks.php: List decks SQL error');
        endif;
        ?>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
</body>
</html>
