<?php

/*
Version:     1.20
Date:        11/01/26
Name:        collection.php
Purpose:     Collection value tab view.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;
use MTG\Cards\ImportExport;
use MTG\Core\Message;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php';               // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/secpagesetup.php';      // Setup page variables

$msg = new Message($appConfig);
$msg->logMessage('[DEBUG]', "Collection page load");
$userId = isset($_SESSION['user']) ? $_SESSION['user'] : 0;
$emailEnabled = (($iniArray['email']['Email'] ?? 'enabled') === 'enabled');
// Has DELETE collection been called?
$deletecollection = (isset($_GET['deletecollection']) && $_GET['deletecollection'] === 'DELETE') ? 'DELETE' : '';
$delcollresult = ''; // Variable to hold error message

// CSV export status (set via session after attempt)
$csvsuccess = $_SESSION['csv_status'] ?? '';
unset($_SESSION['csv_status']);

if (isset($_GET['deckcreated'])) :
    $newdecksuccess = htmlspecialchars($_GET['deckcreated'], ENT_QUOTES, 'UTF-8');
    $msg->logMessage('[DEBUG]', "New deck name $newdecksuccess");
else :
    $newdecksuccess = '';
endif;
if (isset($_GET['decknumber'])) :
    $newdecknumber = filter_input(INPUT_GET, 'decknumber', FILTER_VALIDATE_INT);
    $msg->logMessage('[DEBUG]', "New deck number $newdecknumber");
    if ($newdecknumber === false) :
        $newdecknumber = ''; // If not a valid integer, reset to empty string
    endif;
else :
    $newdecknumber = '';
endif;
if ($newdecksuccess === '' or $newdecknumber === '') :
    $newdecksuccess = $newdecknumber = '';
endif;
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

if ($deletecollection === 'DELETE') :
    $msg->logMessage('[DEBUG]', "Called to delete collection '$mytable'");
    $obj = new ImportExport($db, $appConfig, $gameRules, $userEmail);
    $msg->logMessage('[DEBUG]', "Exporting collection to email...");
    $csvResult = $obj->exportCollectionToCsv($mytable, $myURL, 'email');
    if ($csvResult !== true) :
        $msg->logMessage('[ERROR]', "CSV export email failed: " . (is_string($csvResult) ? $csvResult : 'unknown'));
        $_SESSION['csv_status'] = 'false';
    else :
        $_SESSION['csv_status'] = 'true';
    endif;
    $msg->logMessage('[DEBUG]', "Truncating collection table...");
    if (!$db->execute_query("TRUNCATE TABLE `$mytable`")) :
        $msg->logMessage('[ERROR]', "Truncate table failed");
        $delcollresult = "Error: Failed to delete collection";
    else :
        $msg->logMessage('[DEBUG]', "PRG with success parameter...");
        $delcollresult = "Success: Deleted collection";
    endif;
else :
    $msg->logMessage('[DEBUG]', "Normal page load...");
endif;
$import = isset($_POST['import']) ? 'yes' : '';
$adddeck = isset($_POST['adddeck']) ? 'yes' : '';

$valid_importType = ['add','replace','subtract'];
$importType = isset($_POST['importscope']) ? "{$_POST['importscope']}" : '';
if (!in_array($importType, $valid_importType)) :
    $importType = '';
endif;

$valid_format = ['mtgc','delverlens','regex'];
$importFormat = isset($_POST['format']) ? $_POST['format'] : '';
if (!in_array($importFormat, $valid_format)) :
    $importFormat = '';
endif;

// Does the user have a collection table?
$tableExistsQuery = "SHOW TABLES LIKE '$mytable'";
$msg->logMessage('[DEBUG]', "Checking if user has a collection table...");

$result = $db->query($tableExistsQuery);
if ($result->num_rows == 0) :
    $msg->logMessage('[NOTICE]', "No existing collection table...");
    $query2 = "CREATE TABLE `$mytable` LIKE collectionTemplate";
    $msg->logMessage('[DEBUG]', "Copying collection template...: $query2");

    if ($db->query($query2) === true) :
        $msg->logMessage('[NOTICE]', "Collection template copy successful");
    else :
        $msg->logMessage('[NOTICE]', "Collection template copy failed: " . $db->error);
    endif;
else :
    $msg->logMessage('[DEBUG]', "Collection table exists");
endif;
$weeklyexportQry = $db->execute_query("SELECT weeklyexport FROM users WHERE usernumber = ? LIMIT 1", [$userId]);
if ($weeklyexportQry && $weeklyexportQry->num_rows === 1) :
    $weeklyRow = $weeklyexportQry->fetch_assoc();
    $current_weekly = (int) ($weeklyRow['weeklyexport'] ?? 0);
else :
    $current_weekly = 0;
endif;

?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title><?php echo $siteTitleEsc;?> - collection</title>
        <link rel="manifest" href="/manifest.json" />
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
        <?php include('includes/googlefonts.php');?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
        <script>
            var csrfToken = (window.mtgAjaxConfig && window.mtgAjaxConfig.csrfToken)
                ? window.mtgAjaxConfig.csrfToken
                : <?php echo json_encode(SessionManager::generateCsrfToken()); ?>;
            window.mtgAjaxConfig = window.mtgAjaxConfig || {};
            if (!window.mtgAjaxConfig.csrfToken) {
                window.mtgAjaxConfig.csrfToken = csrfToken;
            }
            document.addEventListener('DOMContentLoaded', function() {
                var csvSuccess = "<?php echo $csvsuccess; ?>";
                if (csvSuccess === 'true') {
                    document.getElementById('csvsuccess').style.display = 'block';
                }
                else if (csvSuccess === 'false') {
                    document.getElementById('csvfailure').style.display = 'block';
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                var delcollresult = "<?php echo isset($delcollresult) ? $delcollresult : ''; ?>";
                if (delcollresult !== '') {
                    document.getElementById('delcollresult').style.display = 'block';
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                var newdecksuccess = "<?php echo isset($newdecksuccess) ? $newdecksuccess : ''; ?>";
                if (newdecksuccess !== '') {
                    document.getElementById('newdecksuccess').style.display = 'block';
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                var importScopeSelect = document.getElementById('importScopeSelect');
                if (!importScopeSelect) {
                    return;
                }
                importScopeSelect.addEventListener('change', function() {
                    var addDeckRow = document.getElementById('addDeckRow');
                    if (this.value === 'replace' || this.value === 'subtract') {
                        addDeckRow.style.display = 'none';
                    } else {
                        addDeckRow.style.display = '';
                    }
                });
            });

            function toggleInfoBox() {
                var infoBox = document.getElementById("infoBox");
                infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "")
                    ? "block"
                    : "none";
            }

            function closeMe( obj )
            {
                obj.style.display = 'none';
                window.location.href = "<?php echo $myURL; ?>/collection.php";
            }

            function setCollectionRefreshing(state) {
                var overlay = document.getElementById('collection-refresh-overlay');
                if (!overlay) {
                    return;
                }
                overlay.style.display = state ? 'flex' : 'none';
            }

            function refreshCollectionAsync() {
                setCollectionRefreshing(true);
                $.ajax({
                    url: '/ajax/ajaxcollectionvalue.php',
                    method: 'GET',
                    data: { 'csrf_token': csrfToken },
                    dataType: 'json'
                }).done(function(data) {
                    if (data && data.success && data.html) {
                        $('#collection-content').html(data.html);
                    } else if (data && data.error) {
                        console.error(data.error);
                    }
                }).fail(function(jqXHR, textStatus) {
                    console.error('Collection refresh failed: ' + textStatus);
                }).always(function() {
                    setCollectionRefreshing(false);
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                refreshCollectionAsync();
            });

            $(document).ready(function() {
                $("#importfileProfile").change(function() {
                    if ($(this).val()){
                        $("#submitfile").attr("style", "display: inline");
                        $("#importsubmit").attr("style", "box-shadow: none");
                    }
                    else {
                        $("#submitfile").attr("style", "display: none");
                    }
                });
            });

            function displayFileName() {
                var input = document.getElementById('importfileProfile');
                var fileNameSpan = document.getElementById('fileNameSpan');
                if (input && input.files.length > 0) {
                    var fileName = input.files[0].name;
                    fileNameSpan.textContent = fileName;
                    document.getElementById('submitfile').style.display = 'block';
                } else if (fileNameSpan) {
                    fileNameSpan.textContent = '';
                    document.getElementById('submitfile').style.display = 'none';
                }
            }

            function importPrep() {
                document.body.style.cursor='wait';
            };
            function confirmDelete() {
                var firstConfirm;
                if (<?php echo $emailEnabled ? 'true' : 'false'; ?>) {
                    firstConfirm = confirm(
                        "Delete all cards from your collection? Selecting OK will send a CSV collection "
                        + "export to your registered email address and then delete all cards."
                    );
                } else {
                    firstConfirm = confirm(
                        "Delete all cards from your collection? Email is disabled so no export will be sent."
                    );
                }

                if (firstConfirm) {
                    var secondConfirm = confirm(
                        "This action is irreversible. Are you absolutely sure you want to delete all cards "
                        + "from your collection?"
                    );
                    return secondConfirm;
                }

                return false;
            };

            $(document).ready(function () {
                $('#weekly_toggle').on('change', function () {
                    var weekly = this.checked ? "TURN ON" : "TURN OFF";
                    $.ajax({
                        url: "/ajax/ajaxweekly.php",
                        method: "POST",
                        data: { "weekly": weekly, "csrf_token": csrfToken },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.error("AJAX error: " + textStatus + " - " + errorThrown);
                        }
                    });
                });
            });

            let collectionChart = null;
            let currentHistoryRange = '30d';

            function fetchHistory(range) {
                currentHistoryRange = range;
                $('#history-range button').removeClass('active');
                $('#btn-range-' + range).addClass('active');
                $('#history-status').text('Loading...');
                $.getJSON('/ajax/ajaxcollectionhistory.php', { range: range, csrf_token: csrfToken })
                    .done(function (resp) {
                        if (!resp || resp.success !== true) {
                            $('#history-status').text('Unable to load history');
                            return;
                        }
                        renderHistoryChart(resp.data || []);
                        $('#history-status').text('');
                    })
                    .fail(function () {
                        $('#history-status').text('Unable to load history');
                    });
            }

            function renderHistoryChart(data) {
                const chartEl = document.getElementById('collectionHistoryChart');
                chartEl.height = 200; // stabilise height across redraws
                const ctx = chartEl.getContext('2d');
                const labels = data.map(p => p.t);
                const usdData = data.map(p => p.usd);
                const localData = data.map(p => p.local);
                const cardCounts = data.map(p => p.cards);

                if (collectionChart !== null) {
                    collectionChart.destroy();
                }

                collectionChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'USD',
                                data: usdData,
                                borderColor: '#3f51b5',
                                backgroundColor: 'rgba(63,81,181,0.08)',
                                tension: 0.2
                            },
                            {
                                label: 'Local',
                                data: localData,
                                borderColor: '#26a69a',
                                backgroundColor: 'rgba(38,166,154,0.08)',
                                tension: 0.2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    afterBody: function (items) {
                                        const idx = items[0].dataIndex;
                                        const cards = cardCounts[idx] ?? 0;
                                        return 'Cards: ' + cards;
                                    }
                                }
                            },
                            legend: {
                                labels: {
                                    usePointStyle: true
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    },
                    maintainAspectRatio: false
                });
            }

            function exportHistory() {
                const range = currentHistoryRange || '30d';
                window.location = '/ajax/ajaxcollectionhistory.php?range='
                    + encodeURIComponent(range)
                    + '&format=csv&csrf_token=' + encodeURIComponent(csrfToken);
            }

            document.addEventListener('DOMContentLoaded', function() {
                fetchHistory('30d');
            });
        </script>
    </head>

    <body> <?php
        include_once 'includes/analyticstracking.php';
        require 'includes/overlays.php';
        require 'includes/header.php';
        require 'includes/menu.php';
        require 'includes/profilemenus.php'; ?>
        <div id="csvsuccess" class="msg-new" onclick='closeMe(this)' style="display: none;">
            <span>CSV email send was successful</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div id="csvfailure" class="msg-new error-new" onclick='closeMe(this)' style="display: none;">
            <span>CSV email send was NOT successful</span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div id="delcollresult" class="msg-new" onclick='closeMe(this)' style="display: none;">
            <span><?php echo isset($delcollresult) ? $delcollresult : ''; ?></span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div id="newdecksuccess" class="msg-new" onclick='closeMe(this)' style="display: none;">
            <span>
                Deck
                <i>
                    <a href='deckdetail.php?deck=<?php echo $newdecknumber ?? ''; ?>'>
                        "<?php echo $newdecksuccess ?? ''; ?>"
                    </a>
                </i>
                created
            </span>
            <br>
            <p onmouseover="" style="cursor: pointer;" id='dismiss'>OK</p>
        </div>
        <div class="info-box" id="infoBox" style="display:none">
            <span class="close-button-profile material-symbols-outlined" onclick="toggleInfoBox()">close</span>
            <div class="info-box-inner">
                <h2 class="h2-no-top-margin">Import help</h2>
                <ul>
                    <li>Select 'Add a deck' to create a deck with cards in this import</li>
                    <li>
                        Select Import type 'Add', 'Replace' or 'Remove' to add to existing, replace existing, or remove
                        cards
                    </li>
                    <li>Import file can be a MTGC CSV, e.g.:</li>
                </ul>
                <pre>
      setcode,number,name,lang,normal,foil,etched,id
      LTR,3,Bill the Pony,en,5,0,0,{Scryfall id}</pre>
                <ul>
                    <li>Delver Lens lists can be imported in the CSV export format of</li>
                </ul>
                <pre>
      'Edition code','Collector's number','Name',
      'Non-foil quantity','Foil quantity','Scryfall ID'</pre>
                <ul>
                    <li>
                        <u>Do not import etched cards with Delver Lens</u>, it flags etched foils as separate cards
                        instead of variations of a card
                    </li>
                    <li>
                        <u>Do not import stamped cards with Delver Lens</u>, it tends to misallocate
                        (e.g. Planeswalker-stamped promos, The List, etc.
                    </li>
                    <li>Files can also be decklists (MTGC or Moxfield)</li>
                    <li>
                        If "id" is a valid Scryfall UUID value, the line will be imported as that id
                        <i>without checking anything else</i>
                    </li>
                    <li>
                        If a Scryfall UUID cannot be matched, import will try a setcode/name/collector number/language
                        match or skip the row
                    </li>
                    <li>If language is unspecified, the primary version is imported (usually English)</li>
                    <li>Set codes and collector numbers must be as <a href='sets.php'> here </a>for success</li>
                    <li>For a format example: export first, use that file as a template</li>
                    <li>Edit CSVs in an app like Notepad++ (<b>don't use Excel</b>)</li>
                    <li>You will be emailed a list of import failures/warnings</li>
                </ul>
            </div>
        </div>
        <div id='page'>
            <div class='staticpagecontent'>
                <div class="profile-container">
                    <?php require 'includes/profile_collection.php'; ?>
                    <div id="collection-history" class="collection-history">
                        <div class="history-header">
                            <h3 class="history-title">Value history</h3>
                            <div id="history-range" class="history-range">
                                <button id="btn-range-30d" class="profilebutton" 
                                    type="button" onclick="fetchHistory('30d')">30d
                                </button>
                                <button id="btn-range-90d" class="profilebutton" 
                                    type="button" onclick="fetchHistory('90d')">90d
                                </button>
                                <button id="btn-range-1y" class="profilebutton" 
                                    type="button" onclick="fetchHistory('1y')">1y
                                </button>
                                <button id="btn-range-all" class="profilebutton"
                                    type="button" onclick="fetchHistory('all')">ALL
                                </button>
                            </div>
                            <span id="history-status" class="history-status"></span>
                            <button
                                id="history-export"
                                class="profilebutton"
                                type="button"
                                onclick="exportHistory()"
                            >
                                EXPORT
                            </button>
                        </div>
                        <div class="history-canvas">
                            <canvas id="collectionHistoryChart"></canvas>
                        </div>
                    </div>
                    <table class="profile_options" style="width: 100%;">
                        <tr>
                            <td colspan="4" style="border-width: 0px 0px 1px;">
                                <h2 class='h2pad'>Collection Management</h2>
                            </td>
                        </tr>
                        <tr class="hoverhighlight">
                            <td class="options_left">
                                <b>Delete</b>
                            </td>
                            <td class="options_centre" colspan="2">
                                <?php if ($emailEnabled) : ?>
                                    Email CSV and delete all cards in your collection
                                <?php else : ?>
                                    Delete all cards in your collection
                                <?php endif; ?>
                            </td>
                            <td class="options_right">
                                <form action="?"  method="GET" onsubmit="return confirmDelete()">
                                    <input
                                        id='delCollection'
                                        name='deletecollection'
                                        class='profilebutton'
                                        type="submit"
                                        value="DELETE"
                                    >
                                    <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                </form>
                            </td>
                        </tr>
                        <tr class="hoverhighlight">
                            <td class="options_left" style="padding-top: 10px;">
                                <b>Import</b>
                            </td>
                            <td class="options_centre" colspan="2">
                                Import cards to your collection&nbsp;
                                <span id="help-button" class="material-symbols-outlined" onclick="toggleInfoBox()">
                                    help
                                </span>
                            </td>
                            <td class="options_right">
                                <form enctype='multipart/form-data' action='?' method='post'>
                                    <label class='profilelabel'>
                                        <input
                                            id='importfileProfile'
                                            type='file'
                                            name='filename'
                                            onchange='displayFileName()'
                                        >
                                        <span>SELECT</span>
                                    </label><br>
                                    <div id='submitfile' style="display: none;">
                                        <label id='profilefilelabel'>
                                            <input
                                                id='importsubmit'
                                                class='importlabel'
                                                type='submit'
                                                name='import'
                                                value='IMPORT'
                                                onclick='importPrep()';>
                                            <input type="hidden" name="format" value="regex">
                                        </label>
                                        <table>
                                            <tr title='Selected file name'>
                                                <td style='text-align: left'>
                                                    <b>Selected:&nbsp;</b>
                                                </td>
                                                <td>
                                                    <span id='fileNameSpan'></span>
                                                </td>
                                            </tr>
                                            <tr
                                                title="Add, replace, or remove cards from your collection"
                                            >
                                                <td style='text-align: left'>
                                                    <b>Action:</b>
                                                </td>
                                                <td>
                                                    <select
                                                        class="dropdown"
                                                        name='importscope'
                                                        id='importScopeSelect'
                                                    >
                                                        <option value='add'>Add</option>
                                                        <option value='replace'>Replace</option>
                                                        <option value='subtract'>Remove</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr title='Add a new deck with these imported cards' id='addDeckRow'>
                                                <td style='text-align: left'>
                                                    <b>Deck:</b>
                                                </td>
                                                <td>
                                                    <span class="checkbox-group">
                                                        <input
                                                            id="adddeck"
                                                            type="checkbox"
                                                            class="checkbox"
                                                            name="adddeck"
                                                            value="yes"
                                                        >
                                                        <label for='adddeck'>
                                                            <span class="check"></span>
                                                            <span class="box"></span>
                                                        </label>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <tr class="hoverhighlight">
                            <td class="options_left">
                                <b>Export</b>
                            </td>
                            <td class="options_centre" colspan="2">
                                 Download a CSV file with all cards in your collection
                            </td>
                            <td class="options_right">
                                <form action="csv.php"  method="GET">
                                    <input id='exportsubmit' class='profilebutton' type="submit" value="EXPORT">
                                    <input type='hidden' name='type' value='echo'>
                                    <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                </form>
                            </td>
                        </tr>
                        <tr class="hoverhighlight">
                            <td class="options_left">
                                &nbsp;
                            </td>
                            <td class="options_centre" colspan="2">
                                Email a CSV file with all cards in your collection to your email address
                            </td>
                            <td class="options_right">
                                <?php if ($emailEnabled) : ?>
                                    <form action="csv.php"  method="GET">
                                        <input id='emailsubmit' class='profilebutton' type="submit" value="EMAIL">
                                        <input type='hidden' name='type' value='email'>
                                        <?php echo "<input type='hidden' name='table' value='$mytable'>"; ?>
                                    </form>
                                <?php else : ?>
                                    Email disabled
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="hoverhighlight">
                            <td class="options_left">
                                <b>&nbsp;</b>
                            </td>
                            <td class="options_centre" colspan="2">
                                Weekly email to you with a CSV file of your collection
                            </td>
                            <td class="options_right">
                                <?php if ($emailEnabled) : ?>
                                    <?php if ($current_weekly == 1) : ?>
                                    <label class="switch">
                                        <input
                                            type="checkbox"
                                            id="weekly_toggle"
                                            class="option_toggle"
                                            checked="true"
                                            value="on"
                                        />
                                    <div class="slider round"></div>
                                    </label>
                                    <?php else : ?>
                                    <label class="switch">
                                        <input
                                            type="checkbox"
                                            id="weekly_toggle"
                                            class="option_toggle"
                                            value="off"
                                        />
                                    <div class="slider round"></div>
                                    </label>
                                    <?php endif; ?>
                                <?php else : ?>
                                    Email disabled
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <?php
                    if ($import === 'yes' && $importType !== '' && $importFormat !== '') :
                        $msg->logMessage('[DEBUG]', "Import called, checking file uploaded...");
                        if (is_uploaded_file($_FILES['filename']['tmp_name'])) :
                            echo "<br><h4>" . "File " . $_FILES['filename']['name']
                                . " uploaded successfully. Processing..." . "</h4>";
                            $msg->logMessage('[DEBUG]', "Import file {$_FILES['filename']['name']} uploaded");
                        else :
                            echo "<br><h4>" . "File " . $_FILES['filename']['name']
                                . " did not upload successfully. Exiting..." . "</h4>";
                            $msg->logMessage('[DEBUG]', "Import file {$_FILES['filename']['name']} failed");
                            exit;
                        endif;
                        $importfile = $_FILES['filename']['tmp_name'];
                        $obj = new ImportExport($db, $appConfig, $gameRules, $userEmail);
                        $importcards = $obj->importCollectionRegex(
                            $importfile,
                            $mytable,
                            $importType,
                            $userEmail
                        );
                        if ($importcards === 'emptyfile') :
                            echo "<h4>File contains no card data</h4>";
                            exit;
                        else :
                            if ($adddeck === 'yes') :
                                $currentDateTime = date("j F Y, g:i:sa");
                                $tmpdeckname = $currentDateTime;
                                $obj = new DeckManager(
                                    $db,
                                    $appConfig,
                                    $gameRules,
                                    $userEmail
                                );
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Import called with 'add deck' option, $tmpdeckname to be created..."
                                );
                                // returns array with success flag, and deck number if success
                                $decksuccess = $obj->addDeck($userId, $tmpdeckname);
                                if ($decksuccess['flag'] === 1) :
                                    $deckNumber = $decksuccess['decknumber'];
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Deck created, $tmpdeckname created, deck number is $deckNumber"
                                    );
                                    echo "<script>var deckNumber = '$deckNumber'; var deckName = '$tmpdeckname'; "
                                        . "var deckCreated = true;</script>";
                                    $file = fopen($_FILES['filename']['tmp_name'], 'r');
                                    $deckManager = new DeckManager(
                                        $db,
                                        $appConfig,
                                        $gameRules,
                                        $userEmail
                                    );
                                    // Read the entire file content into a variable
                                    $fileContent = fread($file, filesize($_FILES['filename']['tmp_name']));
                                    fclose($file);

                                    // Call the processInput method with the decknumber and file content
                                    $deckManager->processInput($deckNumber, $fileContent);
                                else :
                                    $msg->logMessage('[ERROR]', "Deck NOT created");
                                endif;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "redirecting to collection.php?deckcreated=$tmpdeckname&decknumber=$deckNumber"
                                );
                                echo "<meta http-equiv='refresh' "
                                    . "content='0;url=collection.php?deckcreated=$tmpdeckname";
                                echo "&decknumber=$deckNumber'>";
                            else :
                                $msg->logMessage('[DEBUG]', "adddeck is not 'yes', skipping deck creation.");
                                echo "<meta http-equiv='refresh' content='0;url=collection.php'>";
                            endif;
                        endif;
                    elseif ($import === 'yes' && ($importType === '' or $importFormat === '')) :
                        $msg->logMessage('[ERROR]', "Import called without valid importType");
                        echo "<h4>Invalid parameters</h4>";
                        echo "<meta http-equiv='refresh' content='2;url=collection.php'>";
                    else :
                    endif; ?>
                </div>
            </div>
        </div> <?php
        require('includes/footer.php'); ?>
    </body>
</html>
