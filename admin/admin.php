<?php
/*
Version:     4.6
Date:        29/11/25
Name:        admin.php
Purpose:     Site control panel
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       Configuration management - behind re-login

History:
    1.0         Initial version
    2.0         Mysqli_Manager
    3.0         Moved from writelog to Message class
    4.0         PHP 8.1 compatibility
    4.1         Fixed error on unminifying CSS
    4.2 20/01/24 Move to include sessionname and logMessage
    4.3 24/11/25 Code tidy (phpcs)
    4.4 24/11/25 Add bounded log tail reader to avoid loading full log file
    4.5 25/11/25 Header tidy and metadata standardization
    4.6 29/11/25 Rename forcePasswordChange() usage
                 Rename cssVersionCheck() usage
                 Rename setMtceMode() usage
*/
if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');             //Initialise and load ini file
require('../includes/error_handling.php');
require('../includes/functions.php');       //Includes basic functions for non-secure pages
require('../includes/secpagesetup.php');    //Setup page variables
forcePasswordChange();                      //Check if user is disabled or needs to change password
$msg = new Message($logfile);

/**
 * Read the last N lines from a log file without loading it entirely.
 */
function getLogTailLines($filepath, $maxLines = 8)
{
    global $msg;

    if (!is_readable($filepath)) :
        if (isset($msg)) :
            $msg->logMessage('[ERROR]', "Log file not readable: $filepath");
        endif;
        return [];
    endif;

    $handle = fopen($filepath, 'rb');
    if ($handle === false) :
        if (isset($msg)) :
            $msg->logMessage('[ERROR]', "Failed to open log file: $filepath");
        endif;
        return [];
    endif;

    $buffer = 4096;
    fseek($handle, 0, SEEK_END);
    $output = '';
    $linesFound = 0;

    while (ftell($handle) > 0 && $linesFound <= $maxLines) :
        $seek = min(ftell($handle), $buffer);
        fseek($handle, -$seek, SEEK_CUR);
        $chunk = fread($handle, $seek);
        $output = $chunk . $output;
        fseek($handle, -$seek, SEEK_CUR);
        $linesFound += substr_count($chunk, "\n");
    endwhile;

    fclose($handle);

    $allLines = explode("\n", trim($output));

    return array_slice($allLines, -$maxLines);
}

//Check if user is logged in, if not redirect to login.php
$msg->logMessage('[DEBUG]', "Admin page called by user $userName ($userEmail) Admin result: " . $admin);
if ($admin !== 1) :
    require('reject.php');
endif;

//Get date for update form
$dateObject = new DateYMD();
$date = $dateObject->getToday();

$clearScryfallJson = isset($_GET['clearscryfalljson']) ? 'y' : '';
$toggleCss = isset($_GET['togglecss']) ? 'y' : '';
$publishCss = isset($_GET['publishcss']) ? 'y' : '';

if (isset($_POST['update']) && $_POST['update'] === 'ADD') :
    $update = 1;
    if (isset($_POST['date'])) :
        $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_NUMBER_INT);
    endif;
    if (isset($_POST['name'])) :
        $name = strtolower(
            filter_input(
                INPUT_POST,
                'name',
                FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                FILTER_FLAG_NO_ENCODE_QUOTES
            )
        );
    endif;
    if (isset($_POST['updatetext'])) :
        $updateText = filter_input(
            INPUT_POST,
            'updatetext',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            FILTER_FLAG_NO_ENCODE_QUOTES
        );
    endif;

    $stmt = $db->prepare("INSERT INTO updatenotices (`date`, `author`, `update`) VALUES (?, ?, ?)");

    if ($stmt) :
        $stmt->bind_param("sss", $date, $name, $updateText);
        if ($stmt->execute()) :
            $msg->logMessage('[NOTICE]', "Adding update notice: Insert ID: " . $stmt->insert_id);
        else :
            trigger_error("[ERROR] admin.php: Adding update notice: failed " . $stmt->error, E_USER_ERROR);
        endif;
        $stmt->close();
    else :
        trigger_error("[ERROR] admin.php: Adding update notice: failed (prepare statement)" . $db->error, E_USER_ERROR);
    endif;
endif;

if ((isset($_POST['deleteMigrations'])) && ($_POST['deleteMigrations'] == 'DELETE')) :
    $msg->logMessage('[DEBUG]', "Delete all migrations called");

    // Delete records from cards_scry table
    $deleteSql = "DELETE cards_scry
                      FROM cards_scry
                      INNER JOIN migrations
                      ON cards_scry.id = migrations.old_scryfall_id
                      WHERE migrations.db_match = 1";
    $deleteResult = $db->query($deleteSql);
    if ($deleteResult !== false) :
        // Log the total number of rows deleted in migrations
        $msg->logMessage('[NOTICE]', "Deleted {$db->affected_rows} rows in cards_scry");
    endif;
    // Update records in migrations table
    $updateSql = "UPDATE migrations set db_match = 0 WHERE db_match = 1";
    $updateResult = $db->query($updateSql);
    if ($updateResult !== false) :
        // Log the total number of rows deleted in migrations
        $msg->logMessage('[NOTICE]', "Updated {$db->affected_rows} rows in migrations");
    endif;
elseif ((isset($_POST['deleteMigrations'])) && ($_POST['deleteMigrations'] == 'TEST')) :
    $msg->logMessage('[DEBUG]', "Test delete migrations called");

    $sql = "SELECT old_scryfall_id FROM migrations WHERE db_match = 1";
    $result = $db->query($sql);

    if ($result !== false) :
        $totalMatchesInCardsScry = 0; // Initialize a counter

        while ($row = $result->fetch_assoc()) :
            $oldScryfallId = $row['old_scryfall_id'];

            // Count the matching records in cards_scry table (for testing)
            $countSql = "SELECT COUNT(*) FROM cards_scry WHERE id = ?";
            $countResult = $db->execute_query($countSql, [$oldScryfallId]);

            if ($countResult !== false) :
                $rowCount = $countResult->fetch_row();
                $totalMatchesInCardsScry += $rowCount[0];
            else :
                // Handle count error if needed
                trigger_error(
                    "[ERROR] cards.php: Counting matches in cards_scry: Wrong SQL: ($countSql) Error: " . $db->error,
                    E_USER_ERROR
                );
            endif;
        endwhile;

        // Log the total number of matches found in cards_scry (for testing)
        $msg->logMessage('[NOTICE]', "Total matches found in cards_scry (TEST): $totalMatchesInCardsScry");
    endif;
endif;

if (isset($_GET['loglevel'])) :
    $newloglevel = filter_input(INPUT_GET, 'loglevel', FILTER_SANITIZE_NUMBER_INT);
    $ini->data['general']['Loglevel'] = "$newloglevel";
    $msg->logMessage('[NOTICE]', "Log level change by user $userName to $newloglevel");
    $ini->write();
    //re-read ini file
    $ini = new INI("/opt/mtg/mtg_new.ini");
    $iniArray = $ini->data;
    $logLevelIni = $iniArray['general']['Loglevel'];
    if ($logLevelIni == $newloglevel) :
        $msg->logMessage('[NOTICE]', "Log level change success to $newloglevel");
    endif;
endif;
?>

<!DOCTYPE html>
<head>
    <title><?php echo $siteTitle;?> - admin (site)</title>
    <link rel="manifest" href="manifest.json" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
    <?php include('../includes/googlefonts.php');?>
    <script src="../js/jquery.js"></script>
    <script type="text/javascript">
        jQuery( function($) {
            $('#newinfoupdate').submit(function() {
                if(($('#updatetext').val() === '') || ($('#updatedate').val() === '')){
                    alert("You need to complete the date and update text fields");
                    return false;
                }
            });
        });
    </script>
</head>
<body id="body" class="body">

<?php
include '../includes/overlays.php';
include '../includes/header.php';
require('../includes/menu.php');
?>
<div id='page'>
    <div class='staticpagecontent'>
        <div>
            <h3>Add Info update</h3>
            <form id='newinfoupdate' action="?" method="POST">
                <table>
                    <tr>
                        <td colspan='2'>
                            Date
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input
                                class='textinput' id='updatedate' type='date' name='date' value='<?php echo $date ?>'
                            >
                        </td>
                    </tr>
                    <tr>
                        <td colspan='2'>
                            Update notes
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <textarea class='textinput' id='updatetext' name='updatetext' rows='8'></textarea>
                        </td>
                        <td>
                            <input class='profilebutton' name='update' type="submit" value="ADD">
                        </td>
                    </tr>
                </table>
                <input name='name' type='hidden' value='<?php echo ucfirst($userName) ?>'/>
            </form>

            <h3>Logging </h3>
            <h4>Log file path</h4> <?php
            $filepath = "$logfile";
            echo 'Log file location: ' . $filepath . '<p>';
            echo '<h4>Log file - recent</h4>';
            $logLinesToShow = 8;
            $recentLogLines = getLogTailLines($filepath, $logLinesToShow);

            if (empty($recentLogLines)) :
                echo 'No log entries available or log file could not be read.<br>';
            else :
                foreach ($recentLogLines as $line) :
                    echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br>";
                endforeach;
            endif;

            if ((isset($toggleCss)) and ($toggleCss == "y")) :
                $msg->logMessage('[DEBUG]', "Turning off minimised CSS...");
                $cssQuery = 0;
                $query = 'UPDATE admin SET usemin=?';
                if ($db->execute_query($query, [$cssQuery]) === true) :
                    $msg->logMessage('[NOTICE]', "Turned off minimised CSS");
                else :
                    trigger_error("[ERROR] admin.php: Turning off minimised CSS: Failed: " . $db->error, E_USER_ERROR);
                endif;
                $cssver = cssVersionCheck(); //run again
            endif;
            if ((isset($publishCss)) and ($publishCss == "y")) :
                $msg->logMessage('[DEBUG]', "Turning on minimised CSS...");
                $cssQuery = 1;
                $query = 'UPDATE admin SET usemin=?';
                if ($db->execute_query($query, [$cssQuery]) === true) :
                    $msg->logMessage('[NOTICE]', "Turned on minimised CSS");
                else :
                    trigger_error("[ERROR] admin.php: Turning on minimised CSS: Failed: " . $db->error, E_USER_ERROR);
                endif;
                $cssver = cssVersionCheck(); //run again
            endif;
            if ((isset($clearScryfallJson)) and ($clearScryfallJson == "y")) :
                if ($db->query('TRUNCATE TABLE scryfalljson') === true) :
                    $msg->logMessage('[NOTICE]', "JSON data removed");
                else :
                    trigger_error("[ERROR] admin.php: JSON removal failed: " . $db->error, E_USER_ERROR);
                endif;
                $cssver = cssVersionCheck(); //run again
            endif;

            if ((isset($_GET['mtce'])) and ($_GET['mtce'] == 'MTCE ON')) :
                setMtceMode('on');
            elseif ((isset($_GET['mtce'])) and ($_GET['mtce'] == 'MTCE OFF')) :
                setMtceMode('off');
            endif;
            $mtceStatus = mtceModeCheck($user); ?>
            <br>
            <table>
                <tbody>
                    <tr>
                        <td class="options_left">
                            <h4>Log level</h4>
                            If log level set fails, check permissions of web server to the ini file
                        </td>
                        <td>
                            <form action="/admin/admin.php">
                                <label class="radio">
                                    <input type="radio" name="loglevel" value="1"
                                        <?php
                                        if ($logLevelIni === '1') :
                                            echo 'checked="checked"';
                                        endif;
                                        ?>
                                    >
                                    <span class="outer">
                                        <span class="inner">
                                        </span>
                                    </span>1 - Error;
                                </label>
                                <br>
                                <label class="radio">
                                    <input type="radio" name="loglevel" value="2"
                                        <?php
                                        if ($logLevelIni === '2') :
                                            echo 'checked="checked"';
                                        endif;
                                        ?>
                                    >
                                    <span class="outer">
                                        <span class="inner">
                                        </span>
                                    </span>2 - Notice;
                                </label>
                                <br>
                                <label class="radio">
                                    <input type="radio" name="loglevel" value="3"
                                        <?php
                                        if ($logLevelIni === '3') :
                                            echo 'checked="checked"';
                                        endif;
                                        ?>
                                    >
                                    <span class="outer">
                                        <span class="inner">
                                        </span>
                                    </span>3 - Debug;
                                </label><br>
                                <input class='profilebutton' type="submit" value="SET" />
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h4>CSS</h4>
                            <?php
                            if (strpos($cssver, "min") == true) :
                                echo "Current CSS status: Minified <p>";
                                echo "Un-minify to see results of editing CSS!!";
                            else :
                                    echo
                                        "Current CSS status: Not minified <p> Edit style$cssver.css, save, "
                                        . "minify it to 'css/style-min.css', then 'publish'";
                            endif;?>
                        </td>
                        <td>
                            <?php
                            if (strpos($cssver, "min") == true) : ?>
                                <form action="/admin/admin.php">
                                    <input class='profilebutton' type="submit" value="UNMINIFY" />
                                    <input type="hidden" name="togglecss" value="y"/>
                                </form> <?php
                            else : ?>
                                <form action="/admin/admin.php">
                                    <input class='profilebutton' type="submit" value="MINIFY" />
                                    <input type="hidden" name="publishcss" value="y"/>
                                </form> <?php
                            endif;?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h4>Scryfall JSON</h4>
                            Clear all Scryfall data from JSON table
                        </td>
                        <td>
                            <form action="/admin/admin.php">
                                <input class='profilebutton' type="submit" value="CLEAR JSON" />
                                <input type="hidden" name="clearscryfalljson" value="y"/>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h4>Maintenance Mode</h4>
                            Current Maintenance mode status: <?php
                            if (($mtceStatus == 1) or ($mtceStatus == 2)) :
                                echo "On";
                            else :
                                echo "Off";
                            endif; ?>
                        </td>
                        <td> <?php
                        if (($mtceStatus == 1) or ($mtceStatus == 2)) : ?>
                                <form action='admin.php' method='GET'>
                                    <input
                                        class='profilebutton'
                                        id='mtce'
                                        type='submit'
                                        value='MTCE OFF'
                                        name='mtce'
                                    />
                                </form> <?php
                        else : ?>
                                <form action='admin.php' method='GET'>
                                    <input
                                        class='profilebutton'
                                        id='mtce'
                                        type='submit'
                                        value='MTCE ON'
                                        name='mtce'
                                    />
                                </form> <?php
                        endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h3>Ini file settings</h3>
                        </td>
                        <td>
                            <i>(update in ini file)</i>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left" colspan="2">
                            <h4>General settings</h4>
                        </td>
                    <tr>
                        <td class="options_left">
                            Title<br>
                            Tier<br>
                            Image file path<br>
                            Logfile path<br>
                            Timezone<br>
                            Locale<br>
                            Copyright<br>
                            URL<br>
                        </td>
                        <td>
                            <?php
                            echo $iniArray['general']['title'] . '<br>';
                            echo $iniArray['general']['tier'] . '<br>';
                            echo $iniArray['general']['ImgLocation'] . '<br>';
                            echo $iniArray['general']['Logfile'] . '<br>';
                            echo $iniArray['general']['Timezone'] . '<br>';
                            echo $iniArray['general']['Locale'] . '<br>';
                            echo $iniArray['general']['Copyright'] . '<br>';
                            echo $iniArray['general']['URL'] . '<br>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left" colspan="2">
                            <h4>Database settings</h4>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            Host<br>
                            Database<br>
                            User<br>
                            Password<br>
                        </td>
                        <td>
                            <?php
                            echo $iniArray['database']['DBServer'] . '<br>';
                            echo $iniArray['database']['DBName'] . '<br>';
                            echo $iniArray['database']['DBUser'] . '<br>';
                            echo 'See ini file';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left" colspan="2">
                            <h4>Security settings</h4>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            Admin IP<br>
                            Bad login limit<br>
                            Turnstile<br>
                            Turnstile site key<br>
                            Turnstile secret key<br>
                        </td>
                        <td>
                            <?php
                            echo $iniArray['security']['AdminIP'] . '<br>';
                            echo $iniArray['security']['Badloginlimit'] . '<br>';
                            echo $iniArray['security']['Turnstile'] . '<br>';
                            echo $iniArray['security']['Turnstile_site_key'] . '<br>';
                            echo 'See ini file';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left" colspan="2">
                            <h4>FX settings</h4>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            Freecurrency API<br>
                            Freecurrency URL<br>
                            Local currency<br>
                        </td>
                        <td>
                            <?php
                            echo $iniArray['fx']['FreecurrencyAPI'] . '<br>';
                            echo $iniArray['fx']['FreecurrencyURL'] . '<br>';
                            echo $iniArray['fx']['TargetCurrency'] . '<br>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left" colspan="2">
                            <h4>Email settings</h4>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            Server email<br>
                            Admin email<br>
                            SMTP host<br>
                            SMTP port<br>
                            SMTP auth<br>
                            SMTP username<br>
                            SMTP password<br>
                        </td>
                        <td>
                            <?php
                            echo $serverEmail . '<br>';
                            echo $adminEmail . '<br>';
                            echo $smtpParameters['SMTPHost'] . '<br>';
                            echo $smtpParameters['SMTPPort'] . '<br>';
                            echo $smtpParameters['SMTPAuth'] . '<br>';
                            echo $smtpParameters['SMTPUsername'] . '<br>';
                            echo 'See ini file';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left" colspan="2">
                            <h4>Disqus settings</h4>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            Status<br>
                            Dev URL<br>
                            Prod URL<br>
                        </td>
                        <td>
                            <?php
                            echo $iniArray['comments']['Disqus'] . '<br>';
                            echo $iniArray['comments']['DisqusDevURL'] . '<br>';
                            echo $iniArray['comments']['DisqusProdURL'] . '<br>';
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Migration cards (Scryfall corrections)</h3> <?php
            $stmt = $db->execute_query(
                "SELECT
                    old_scryfall_id,
                    object,
                    performed_at,
                    migration_strategy,
                    note,
                    metadata_name,
                    metadata_set_code,
                    metadata_collector_number,
                    new_scryfall_id
                FROM migrations
                WHERE db_match = 1"
            );
            if ($stmt != true) :
                trigger_error(
                    "[ERROR] Class " . __METHOD__ . " " . __LINE__,
                    " - SQL failure: Error: " . $db->error,
                    E_USER_ERROR
                );
            else :
                if ($stmt->num_rows > 0) : ?>
                    <script>
                        function confirmTestDelete() {
                            // Display a confirmation dialog
                            if (confirm("Are you sure you want to test delete all migrations?")) {
                                // If the user confirms, submit the form
                                document.getElementById("testDeleteForm").submit();
                            }
                        }
                    </script>

                    <!-- Conditional display of buttons based on the $countSql variable -->
                    <?php
                    if (isset($totalMatchesInCardsScry) && $totalMatchesInCardsScry > 0) : ?>
                        <!-- Display the quantity of rows found in the test -->
                        <p>Rows found in test: <?php echo $totalMatchesInCardsScry; ?></p>

                        <!-- Display the DELETE button -->
                        <form id="deleteForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                            <button
                                type="submit"
                                name="deleteMigrations"
                                value="DELETE"
                                onclick="confirmDelete()"
                            >
                            Delete ALL migrations (<?php echo $totalMatchesInCardsScry; ?>)
                            </button>
                        </form>
                    <?php else : ?>
                        <!-- Display the TEST DELETE button with the $countSql variable -->
                        <form id="testDeleteForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                            <input type="hidden" name="deleteMigrations" value="TEST">
                            <button type="button" onclick="confirmTestDelete()">Test migrations deletion</button>
                        </form>
                    <?php endif; ?>

                <table border="1">
                    <tr style="font-weight: bold;">
                        <th>Row</th>
                        <th>Old Scryfall ID</th>
                        <th>Object</th>
                        <th>Migration Strategy</th>
                        <th>Name</th>
                        <th>Set code</th>
                        <th>Card number</th>
                        <th>Note</th>
                        <th>Merge new Scryfall ID</th>
                        <th>Decks</th>
                        <th>Owned</th>
                    </tr>
                    <tr>
                    <?php
                    $rowNumber = 1;
                    while ($row = $stmt->fetch_assoc()) :
                        $rowNumber = $rowNumber + 1;

                        // Find decks and owners of cards needing migration
                        $userResultArray = $collectionResultArray = $resultArray = array();
                        $sql2 = "SELECT deckname, username FROM decks
                            LEFT JOIN users ON decks.owner = users.usernumber
                            LEFT JOIN deckcards ON decks.decknumber = deckcards.decknumber
                            WHERE deckcards.cardnumber = ?";

                        $stmt2 = $db->prepare($sql2);
                        if ($stmt2) :
                            $stmt2->bind_param("s", $row['old_scryfall_id']);
                            $stmt2->execute();
                            $stmt2->bind_result($deckName, $deckOwner);
                        else :
                            trigger_error("[ERROR] cards.php: Wrong SQL: ($sql2) Error: " . $db->error, E_USER_ERROR);
                        endif;
                        while ($stmt2->fetch()) :
                            $resultArray[] = array('deckname' => $deckName, 'deckowner' => $deckOwner);
                        endwhile;
                        $stmt2->close();

                        $sql3 = "SELECT usernumber,username FROM users";
                        $stmt3 = $db->prepare($sql3);
                        if ($stmt3) :
                            $stmt3->execute();
                            $stmt3->bind_result($userNumber, $userName);
                        else :
                            trigger_error("[ERROR] cards.php: Wrong SQL: ($sql3) Error: " . $db->error, E_USER_ERROR);
                        endif;
                        while ($stmt3->fetch()) :
                            $userResultArray[] = array('usernumber' => $userNumber, 'username' => $userName);
                        endwhile;
                        $stmt3->close();

                        foreach ($userResultArray as $userArray) :
                            $table = $userArray['usernumber'] . "collection";
                            $sql4 = "SELECT
                                         SUM(
                                             COALESCE(`$table`.`normal`, 0)
                                                 +
                                             COALESCE(`$table`.`foil`, 0)
                                                 +
                                             COALESCE(`$table`.`etched`, 0)
                                         )
                                         AS total
                                         FROM `$table`
                                         WHERE id = ?";
                            $stmt4 = $db->prepare($sql4);

                            // Check if the statement was prepared successfully
                            if ($stmt4) :
                                $stmt4->bind_param("s", $row['old_scryfall_id']);
                                if ($stmt4->error) {
                                    trigger_error("[ERROR] Bind error: " . $stmt4->error, E_USER_ERROR);
                                }
                                $stmt4->execute();
                                $stmt4->bind_result($total);
                            else :
                                trigger_error(
                                    "[ERROR] cards.php: Wrong SQL: ($sql4) Error: " . $db->error,
                                    E_USER_ERROR
                                );
                            endif;
                            while ($stmt4->fetch()) :
                                if ($total !== null and $total != 0) :
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Found one!: "
                                        . "User: {$userArray['username']}, ID: {$row['old_scryfall_id']}: Total: $total"
                                    );
                                    $collectionResultArray[] = array(
                                        'owner' => $userArray['username'],
                                        'total' => $total
                                    );
                                endif;
                            endwhile;
                            $stmt4->close();
                        endforeach;
                        ?>
                        <tr>
                            <td><?php echo($rowNumber);?></td>
                            <td><?php
                                echo(
                                    "<a href=$myURL/carddetail.php?id="
                                    . "{$row['old_scryfall_id']}>{$row['old_scryfall_id']}</a>"
                                );
                                ?>
                            </td>
                            <td><?php echo($row['object']);?></td>
                            <td><?php
                                echo(
                                    "<a href=$myURL/admin/cards.php?cardtoedit="
                                    . "{$row['old_scryfall_id']}>{$row['migration_strategy']}</a>"
                                );
                                ?>
                            </td>
                            <td><?php echo($row['metadata_name']);?></td>
                            <td><?php echo($row['metadata_set_code']);?></td>
                            <td><?php echo($row['metadata_collector_number']);?></td>
                            <td><?php echo($row['note']);?></td>
                            <td><?php
                                echo(
                                    "<a href=$myURL/carddetail.php?id="
                                    . "{$row['new_scryfall_id']}>{$row['new_scryfall_id']}</a>"
                                );
                                ?>
                            </td>
                            <td><?php
                            if (!empty($resultArray)) :
                                echo '<table border="1">';
                                echo '<tr><th>Deck Name</th><th>Owner</th></tr>';
                                foreach ($resultArray as $deckresult) :
                                    echo '<tr>';
                                    echo '<td>' . $deckresult['deckname'] . '</td>';
                                    echo '<td>' . $deckresult['deckowner'] . '</td>';
                                    echo '</tr>';
                                endforeach;
                                echo '</table>';
                            else :
                                    echo 'None';
                            endif;?>
                            </td>
                            <td><?php
                            if (!empty($collectionResultArray)) :
                                $msg->logMessage('[DEBUG]', "Should be here if there is one");
                                echo '<table border="1">';
                                echo '<tr><th>Owner</th><th>Total</th></tr>';
                                foreach ($collectionResultArray as $userresult) :
                                    echo '<tr>';
                                    echo '<td>' . $userresult['owner'] . '</td>';
                                    echo '<td>' . $userresult['total'] . '</td>';
                                    echo '</tr>';
                                endforeach;
                                echo '</table>';
                            else :
                                    echo 'None';
                            endif;
                            ?>
                            </td>
                        </tr>
                        <?php
                    endwhile; ?>
                    </tr>
                </table>
                &nbsp; <?php
                else :
                    $msg->logMessage('[DEBUG]', "No rows");
                    echo "No cards needing action <br>";
                    echo "&nbsp;<br>";
                endif;
            endif;
            ?>
        </div>
    </div>
</div>

<?php require('../includes/footer.php'); ?>
</body>
</html>
