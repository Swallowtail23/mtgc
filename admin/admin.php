<?php
/*
Version:     4.10
Date:        30/11/25
Name:        admin.php
Purpose:     Site control panel
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       Move style elements to CSS file

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
    4.7 30/11/25 Add re-auth gated ini editing UI
    4.8 30/11/25 Hide ini settings until editing unlocked
    4.9 30/11/25 Add cancel to re-auth prompt
    4.10 30/11/25 Tooltips, wider inputs, writable path checks, timezone select, extra cancel on DB password
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

function isPathWritable($path)
{
    if ($path === null || $path === '') :
        return false;
    endif;
    if (file_exists($path)) :
        return is_writable($path);
    endif;
    $directory = dirname($path);
    if ($directory === '.' || $directory === '') :
        return false;
    endif;
    return is_dir($directory) && is_writable($directory);
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

$configEditUnlocked = false;
$configAuthRequested = false;
$configEditMessage = $_SESSION['config_save_message'] ?? '';
$configEditError = '';
$configEditErrorTarget = '';
$configAuthWindowSeconds = 600;
$configAction = filter_input(INPUT_POST, 'config_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$timezoneList = timezone_identifiers_list();
sort($timezoneList);
$configInputStyle = 'style="width:220px"';
$turnstileSiteKeyIni = $iniArray['security']['Turnstile_site_key'] ?? '';
$turnstileSecretKeyIni = $iniArray['security']['Turnstile_secret_key'] ?? '';
$trustDurationIni = $iniArray['security']['TrustDuration'] ?? '';
$fxApiIni = $iniArray['fx']['FreecurrencyAPI'] ?? '';
$fxUrlIni = $iniArray['fx']['FreecurrencyURL'] ?? '';
$fxTargetCurrencyIni = $iniArray['fx']['TargetCurrency'] ?? '';
$smtpDebugIni = $iniArray['email']['SMTPDebug'] ?? '';
$smtpHostIni = $iniArray['email']['Host'] ?? '';
$smtpPortIni = $iniArray['email']['Port'] ?? '';
$smtpUserIni = $iniArray['email']['Username'] ?? '';
$smtpPasswordIni = $iniArray['email']['Password'] ?? '';
$smtpSecureIni = $iniArray['email']['SMTPSecure'] ?? '';
$smtpHeloIni = $iniArray['email']['SMTPHelo'] ?? gethostname();
$smtpVerifyIni = $iniArray['email']['SMTPVerifySSL'] ?? 1;
$smtpSecureChoice = 'none';
if ($smtpSecureIni === 'PHPMailer::ENCRYPTION_SMTPS') :
    $smtpSecureChoice = 'smtps';
elseif ($smtpSecureIni === 'PHPMailer::ENCRYPTION_STARTTLS') :
    $smtpSecureChoice = 'starttls';
endif;
$disqusDevUrlIni = $iniArray['comments']['DisqusDevURL'] ?? '';
$disqusProdUrlIni = $iniArray['comments']['DisqusProdURL'] ?? '';
$smtpDebugEnabled = ($smtpDebugIni !== 'SMTP::DEBUG_OFF' && $smtpDebugIni !== '');
$smtpParameters = [
    'SMTPHost' => $smtpHostIni,
    'SMTPPort' => $smtpPortIni,
    'SMTPAuth' => $iniArray['email']['SMTPAuth'] ?? 0,
    'SMTPUsername' => $smtpUserIni,
    'SMTPSecure' => $smtpSecureIni,
    'SMTPHelo' => $smtpHeloIni,
    'SMTPVerifySSL' => $smtpVerifyIni
];

if (isset($_SESSION['config_edit_expires'])) :
    if ($_SESSION['config_edit_expires'] > time()) :
        $configEditUnlocked = true;
    else :
        unset($_SESSION['config_edit_expires']);
    endif;
endif;

if ($configAction === 'start_reauth') :
    $configAuthRequested = true;
elseif ($configAction === 'reauth_submit') :
    $reauthPassword = filter_input(INPUT_POST, 'config_password', FILTER_UNSAFE_RAW);
    $passwordCheck = new PasswordCheck($db, $logfile, $siteTitle);
    $reauthResult = $passwordCheck->validatePassword($userEmail, $reauthPassword);
    if ($reauthResult === 10) :
        $_SESSION['config_edit_expires'] = time() + $configAuthWindowSeconds;
        $configEditUnlocked = true;
        $configEditMessage = 'Configuration editing enabled for 10 minutes.';
        header('Location: admin.php#inisettings');
        exit();
    else :
        $configAuthRequested = true;
        $configEditError = 'Re-authentication failed. Please try again.';
    endif;
elseif ($configAction === 'cancel_config_edit') :
    unset($_SESSION['config_edit_expires']);
    header('Location: admin.php#inisettings');
    exit();
endif;

function getPostedValue($name, $default = '')
{
    $value = filter_input(INPUT_POST, $name, FILTER_UNSAFE_RAW);
    if ($value === null) :
        return $default;
    endif;
    return trim($value);
}

if ($configEditUnlocked && $configAction === 'save_ini') :
    $updatedIni = $iniArray;
    $updatedIni['security']['Turnstile_site_key'] = $turnstileSiteKeyIni;
    $updatedIni['security']['Turnstile_secret_key'] = $turnstileSecretKeyIni;
    $updatedIni['security']['TrustDuration'] = $trustDurationIni;
    $updatedIni['fx']['FreecurrencyAPI'] = $fxApiIni;
    $updatedIni['fx']['FreecurrencyURL'] = $fxUrlIni;
    $updatedIni['fx']['TargetCurrency'] = $fxTargetCurrencyIni;
    $updatedIni['email']['SMTPDebug'] = $smtpDebugIni;
    $updatedIni['email']['Host'] = $smtpHostIni;
    $updatedIni['email']['Port'] = $smtpPortIni;
    $updatedIni['email']['Username'] = $smtpUserIni;
    $updatedIni['email']['SMTPSecure'] = $smtpSecureIni;
    $updatedIni['comments']['DisqusDevURL'] = $disqusDevUrlIni;
    $updatedIni['comments']['DisqusProdURL'] = $disqusProdUrlIni;

    // General settings
    $updatedIni['general']['title'] = getPostedValue('general_title', $iniArray['general']['title']);
    $updatedIni['general']['tier'] = getPostedValue('general_tier', $iniArray['general']['tier']);
    $updatedIni['general']['ImgLocation'] = getPostedValue('general_img_location', $iniArray['general']['ImgLocation']);
    $updatedIni['general']['Logfile'] = getPostedValue('general_logfile', $iniArray['general']['Logfile']);
    $timezoneSelection = getPostedValue('general_timezone', $iniArray['general']['Timezone']);
    if (in_array($timezoneSelection, $timezoneList, true)) :
        $updatedIni['general']['Timezone'] = $timezoneSelection;
    endif;
    $updatedIni['general']['Locale'] = getPostedValue('general_locale', $iniArray['general']['Locale']);
    $updatedIni['general']['Copyright'] = getPostedValue('general_copyright', $iniArray['general']['Copyright']);
    $updatedIni['general']['URL'] = getPostedValue('general_url', $iniArray['general']['URL']);
    $loglevelSelection = filter_input(INPUT_POST, 'general_loglevel', FILTER_SANITIZE_NUMBER_INT);
    if ($loglevelSelection !== null && $loglevelSelection !== false && $loglevelSelection !== '') :
        $updatedIni['general']['Loglevel'] = $loglevelSelection;
    endif;

    // Database settings
    $updatedIni['database']['DBServer'] = getPostedValue('database_host', $iniArray['database']['DBServer']);
    $updatedIni['database']['DBName'] = getPostedValue('database_name', $iniArray['database']['DBName']);
    $updatedIni['database']['DBUser'] = getPostedValue('database_user', $iniArray['database']['DBUser']);
    $dbPasswordChanged = filter_input(INPUT_POST, 'database_password_changed', FILTER_SANITIZE_NUMBER_INT);
    if ($dbPasswordChanged === '1') :
        $updatedIni['database']['DBPass'] = getPostedValue('database_dbpass', $iniArray['database']['DBPass']);
    endif;

    // Security settings
    $updatedIni['security']['AdminIP'] = getPostedValue('security_admin_ip', $iniArray['security']['AdminIP']);
    $badLoginLimit = filter_input(INPUT_POST, 'security_badloginlimit', FILTER_SANITIZE_NUMBER_INT);
    if ($badLoginLimit !== null && $badLoginLimit !== false && $badLoginLimit !== '') :
        $updatedIni['security']['Badloginlimit'] = $badLoginLimit;
    endif;
    $turnstileChoice = getPostedValue('security_turnstile', $iniArray['security']['Turnstile']);
    if (in_array($turnstileChoice, array('enabled', 'disabled'), true)) :
        $updatedIni['security']['Turnstile'] = $turnstileChoice;
    endif;
    $turnstileSiteKey = getPostedValue('security_turnstile_site_key', $turnstileSiteKeyIni);
    if ($turnstileSiteKey !== '') :
        $updatedIni['security']['Turnstile_site_key'] = $turnstileSiteKey;
    endif;
    $turnstileSecretKey = getPostedValue('security_turnstile_secret_key', $turnstileSecretKeyIni);
    if ($turnstileSecretKey !== '') :
        $updatedIni['security']['Turnstile_secret_key'] = $turnstileSecretKey;
    endif;
    $trustDuration = filter_input(INPUT_POST, 'security_trust_duration', FILTER_SANITIZE_NUMBER_INT);
    if ($trustDuration !== null && $trustDuration !== false && $trustDuration !== '') :
        $updatedIni['security']['TrustDuration'] = $trustDuration;
    endif;

    // FX settings
    $updatedIni['fx']['FreecurrencyAPI'] = getPostedValue('fx_api_key', $fxApiIni);
    $updatedIni['fx']['FreecurrencyURL'] = getPostedValue('fx_api_url', $fxUrlIni);
    $fxTargetCurrency = strtoupper(getPostedValue('fx_target_currency', $fxTargetCurrencyIni));
    if ($fxTargetCurrency !== '') :
        $updatedIni['fx']['TargetCurrency'] = $fxTargetCurrency;
    endif;

    // Email settings
    $updatedIni['email']['ServerEmail'] = getPostedValue('email_server', $iniArray['email']['ServerEmail']);
    $updatedIni['email']['AdminEmail'] = getPostedValue('email_admin', $iniArray['email']['AdminEmail']);
    $smtpDebugChoice = getPostedValue('email_smtp_debug', $smtpDebugIni);
    if ($smtpDebugChoice === 'enabled') :
        $updatedIni['email']['SMTPDebug'] = 'SMTP::DEBUG_SERVER';
    elseif ($smtpDebugChoice === 'disabled') :
        $updatedIni['email']['SMTPDebug'] = 'SMTP::DEBUG_OFF';
    else :
        $updatedIni['email']['SMTPDebug'] = $smtpDebugIni;
    endif;
    $updatedIni['email']['Host'] = getPostedValue('email_host', $smtpHostIni);
    $updatedIni['email']['SMTPHelo'] = getPostedValue('email_helo', $smtpHeloIni ?: gethostname());
    $smtpPort = filter_input(INPUT_POST, 'email_port', FILTER_SANITIZE_NUMBER_INT);
    if ($smtpPort !== null && $smtpPort !== false && $smtpPort !== '') :
        $updatedIni['email']['Port'] = $smtpPort;
    endif;
    $emailStatus = getPostedValue('email_status', $iniArray['email']['Email'] ?? 'enabled');
    if (in_array($emailStatus, array('enabled', 'disabled'), true)) :
        $updatedIni['email']['Email'] = $emailStatus;
    endif;
    $smtpAuth = getPostedValue('email_auth', $iniArray['email']['SMTPAuth']);
    if ($smtpAuth === 'enabled' || $smtpAuth === '1' || $smtpAuth === 'true' || $smtpAuth === 1) :
        $updatedIni['email']['SMTPAuth'] = 1;
    else :
        $updatedIni['email']['SMTPAuth'] = 0;
    endif;
    $updatedIni['email']['Username'] = getPostedValue('email_username', $smtpUserIni);
    $emailPasswordChanged = filter_input(INPUT_POST, 'email_password_changed', FILTER_SANITIZE_NUMBER_INT);
    if ($emailPasswordChanged === '1') :
        $updatedIni['email']['Password'] = getPostedValue('email_password', $smtpPasswordIni);
    endif;
    $smtpSecureChoice = getPostedValue('email_secure', $smtpSecureIni);
    if ($smtpSecureChoice === 'smtps') :
        $updatedIni['email']['SMTPSecure'] = 'PHPMailer::ENCRYPTION_SMTPS';
    elseif ($smtpSecureChoice === 'starttls') :
        $updatedIni['email']['SMTPSecure'] = 'PHPMailer::ENCRYPTION_STARTTLS';
    elseif ($smtpSecureChoice === 'none') :
        $updatedIni['email']['SMTPSecure'] = 'none';
    else :
        $updatedIni['email']['SMTPSecure'] = $smtpSecureIni;
    endif;
    $smtpVerifyChoice = getPostedValue(
        'email_verify',
        ($smtpVerifyIni && $smtpVerifyIni !== '0') ? 'verify' : 'allow'
    );
    if ($smtpVerifyChoice === 'allow') :
        $updatedIni['email']['SMTPVerifySSL'] = 0;
    else :
        $updatedIni['email']['SMTPVerifySSL'] = 1;
    endif;

    // Comment settings
    $commentsStatus = getPostedValue('comments_status', $iniArray['comments']['Disqus']);
    if (in_array($commentsStatus, array('enabled', 'disabled'), true)) :
        $updatedIni['comments']['Disqus'] = $commentsStatus;
    endif;
    $updatedIni['comments']['DisqusDevURL'] = getPostedValue('comments_dev_url', $disqusDevUrlIni);
    $updatedIni['comments']['DisqusProdURL'] = getPostedValue('comments_prod_url', $disqusProdUrlIni);

    $pathErrors = array();
    if (!isPathWritable($updatedIni['general']['ImgLocation'])) :
        $pathErrors[] = array('field' => 'general_img_location', 'message' => 'Image file path is not writable.');
    endif;
    if (!isPathWritable($updatedIni['general']['Logfile'])) :
        $pathErrors[] = array('field' => 'general_logfile', 'message' => 'Logfile path is not writable.');
    endif;

    if (empty($pathErrors)) :
        $iniSaveResult = $ini->write(null, $updatedIni);

        if ($iniSaveResult === true) :
            $msg->logMessage('[NOTICE]', "Configuration updated by $userName");
            $_SESSION['config_save_message'] = 'Configuration saved.';
            header('Location: admin.php');
            exit();
            // re-read ini file for updated values
            $ini = new INI("/opt/mtg/mtg_new.ini");
            $iniArray = $ini->data;
            $logLevelIni = $iniArray['general']['Loglevel'];
            $smtpParameters = [
                'SMTPDebug' => $iniArray['email']['SMTPDebug'],
                'SMTPHost' => $iniArray['email']['Host'],
                'SMTPAuth' => $iniArray['email']['SMTPAuth'],
                'SMTPUsername' => $iniArray['email']['Username'],
                'SMTPPassword' => $iniArray['email']['Password'],
                'SMTPSecure' => $iniArray['email']['SMTPSecure'],
                'SMTPPort' => $iniArray['email']['Port'],
                'SMTPHelo' => $iniArray['email']['SMTPHelo'] ?? gethostname(),
                'SMTPVerifySSL' => $iniArray['email']['SMTPVerifySSL'] ?? 1,
                'globalDebug' => $logLevelIni
            ];
            $adminEmail = $iniArray['email']['AdminEmail'];
            $serverEmail = $iniArray['email']['ServerEmail'];
            $turnstileSiteKeyIni = $iniArray['security']['Turnstile_site_key'] ?? '';
            $turnstileSecretKeyIni = $iniArray['security']['Turnstile_secret_key'] ?? '';
            $trustDurationIni = $iniArray['security']['TrustDuration'] ?? '';
            $fxApiIni = $iniArray['fx']['FreecurrencyAPI'] ?? '';
            $fxUrlIni = $iniArray['fx']['FreecurrencyURL'] ?? '';
            $fxTargetCurrencyIni = $iniArray['fx']['TargetCurrency'] ?? '';
            $smtpDebugIni = $iniArray['email']['SMTPDebug'] ?? '';
            $smtpHostIni = $iniArray['email']['Host'] ?? '';
            $smtpPortIni = $iniArray['email']['Port'] ?? '';
            $smtpUserIni = $iniArray['email']['Username'] ?? '';
            $smtpPasswordIni = $iniArray['email']['Password'] ?? '';
            $smtpSecureIni = $iniArray['email']['SMTPSecure'] ?? '';
            $smtpHeloIni = $iniArray['email']['SMTPHelo'] ?? gethostname();
            $smtpVerifyIni = $iniArray['email']['SMTPVerifySSL'] ?? 1;
            $disqusDevUrlIni = $iniArray['comments']['DisqusDevURL'] ?? '';
            $disqusProdUrlIni = $iniArray['comments']['DisqusProdURL'] ?? '';
        else :
            $configEditError = 'Saving configuration failed. Check ini file permissions.';
        endif;
    else :
        $messages = array_map(function ($err) {
            return $err['message'];
        }, $pathErrors);
        $configEditError = "<div class='alert-box error'><span>error: </span>"
            . htmlspecialchars(implode(' ', $messages))
            . "</div>";
        $configEditErrorTarget = $pathErrors[0]['field'];
    endif;
endif;

$turnstileEnabled = ($iniArray['security']['Turnstile'] === 'enabled');
$commentsEnabled = ($iniArray['comments']['Disqus'] === 'enabled');
$emailEnabledSetting = $iniArray['email']['Email'] ?? 'enabled';
$emailEnabled = ($emailEnabledSetting === 'enabled');
$emailAuthEnabled = (
    $iniArray['email']['SMTPAuth'] === true
    || $iniArray['email']['SMTPAuth'] === 1
    || $iniArray['email']['SMTPAuth'] === '1'
    || $iniArray['email']['SMTPAuth'] === 'true'
);
$configEditExpiry = $_SESSION['config_edit_expires'] ?? 0;
$turnstileSiteKeyIni = $iniArray['security']['Turnstile_site_key'] ?? '';
$turnstileSecretKeyIni = $iniArray['security']['Turnstile_secret_key'] ?? '';
$trustDurationIni = $iniArray['security']['TrustDuration'] ?? '';
$fxApiIni = $iniArray['fx']['FreecurrencyAPI'] ?? '';
$fxUrlIni = $iniArray['fx']['FreecurrencyURL'] ?? '';
$fxTargetCurrencyIni = $iniArray['fx']['TargetCurrency'] ?? '';
$smtpDebugIni = $iniArray['email']['SMTPDebug'] ?? '';
$smtpHostIni = $iniArray['email']['Host'] ?? '';
$smtpPortIni = $iniArray['email']['Port'] ?? '';
$smtpUserIni = $iniArray['email']['Username'] ?? '';
$smtpPasswordIni = $iniArray['email']['Password'] ?? '';
$smtpSecureIni = $iniArray['email']['SMTPSecure'] ?? '';
$smtpHeloIni = $iniArray['email']['SMTPHelo'] ?? '';
$smtpVerifyIni = $iniArray['email']['SMTPVerifySSL'] ?? 1;
$disqusDevUrlIni = $iniArray['comments']['DisqusDevURL'] ?? '';
$disqusProdUrlIni = $iniArray['comments']['DisqusProdURL'] ?? '';
?>

<!DOCTYPE html>
<head>
    <title><?php echo $siteTitle;?> - admin (site)</title>
    <link rel="manifest" href="/manifest.json" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
    <?php include('../includes/googlefonts.php');?>
    <script src="../js/jquery.js"></script>
    <script type="text/javascript">
        jQuery( function($) {
            var configPasswordField = $('#config_password');
            if (configPasswordField.length) {
                configPasswordField.focus();
            }
            function markDisabledFields() {
                $('input, select, button').each(function() {
                    if ($(this).prop('disabled')) {
                        $(this).addClass('disabled-field');
                    } else {
                        $(this).removeClass('disabled-field');
                    }
                });
            }

            $('#newinfoupdate').submit(function() {
                if(($('#updatetext').val() === '') || ($('#updatedate').val() === '')){
                    alert("You need to complete the date and update text fields");
                    return false;
                }
            });
            function toggleDependent(controllerSelector, targetSelectors, enableValues) {
                var controller = $(controllerSelector);
                if (controller.length === 0) {
                    return;
                }
                var enabled = enableValues.indexOf(controller.val()) !== -1;
                targetSelectors.forEach(function(selector) {
                    $(selector).prop('disabled', !enabled);
                    if (!enabled) {
                        $(selector).addClass('disabled-field');
                    } else {
                        $(selector).removeClass('disabled-field');
                    }
                });
            }

            function setupPasswordSection(toggleSelector, sectionSelector, flagSelector, altTextOnShow) {
                var toggle = $(toggleSelector);
                var section = $(sectionSelector);
                var flag = $(flagSelector);
                var defaultLabel = toggle.text();
                function hideSection() {
                    section.hide();
                    flag.val('0');
                    section.find('input[type="password"]').val('');
                    if (altTextOnShow) {
                        toggle.text(defaultLabel);
                    }
                }
                function showSection() {
                    section.show();
                    flag.val('1');
                    section.find('input[type="password"]').first().focus();
                    if (altTextOnShow) {
                        toggle.text(altTextOnShow);
                    }
                }
                toggle.on('click', function(event) {
                    event.preventDefault();
                    if (section.is(':visible')) {
                        hideSection();
                    } else {
                        showSection();
                    }
                });
            }

            setupPasswordSection(
                '#db_password_toggle',
                '#db_password_section',
                '#database_password_changed',
                'CANCEL'
            );
            setupPasswordSection(
                '#email_password_toggle',
                '#email_password_section',
                '#email_password_changed',
                'CANCEL'
            );

            toggleDependent(
                '#security_turnstile',
                ['#security_turnstile_site_key', '#security_turnstile_secret_key'],
                ['enabled']
            );
            $('#security_turnstile').on('change', function() {
                toggleDependent(
                    '#security_turnstile',
                    ['#security_turnstile_site_key', '#security_turnstile_secret_key'],
                    ['enabled']
                );
            });

            toggleDependent('#comments_status', ['#comments_dev_url', '#comments_prod_url'], ['enabled']);
            $('#comments_status').on('change', function() {
                toggleDependent('#comments_status', ['#comments_dev_url', '#comments_prod_url'], ['enabled']);
            });

            toggleDependent(
                '#email_auth',
                ['#email_username', '#email_password_toggle', '#email_secure'],
                ['enabled']
            );
            // Ensure SMTP auth-dependent fields respect initial state on load
            if ($('#email_auth').val() !== 'enabled') {
                $('#email_password_section').hide();
                $('#email_password_changed').val('0');
            }
            toggleDependent(
                '#email_status',
                [
                    '#email_server',
                    '#email_admin',
                    '#email_smtp_debug',
                    '#email_host',
                    '#email_port',
                    '#email_auth',
                    '#email_username',
                    '#email_password_toggle',
                    '#email_secure'
                ],
                ['enabled']
            );
            toggleDependent(
                '#email_auth',
                ['#email_username', '#email_password_toggle', '#email_secure'],
                ['enabled']
            );
            if ($('#email_auth').val() !== 'enabled') {
                $('#email_password_section').hide();
                $('#email_password_changed').val('0');
            }
            markDisabledFields();
            $('#email_auth').on('change', function() {
                toggleDependent(
                    '#email_auth',
                    ['#email_username', '#email_password_toggle', '#email_secure'],
                    ['enabled']
                );
                if ($(this).val() !== 'enabled') {
                    $('#email_password_section').hide();
                    $('#email_password_changed').val('0');
                }
            });
            $('#email_status').on('change', function() {
                toggleDependent(
                    '#email_status',
                    [
                        '#email_server',
                        '#email_admin',
                        '#email_smtp_debug',
                        '#email_host',
                        '#email_port',
                        '#email_auth',
                        '#email_username',
                        '#email_password_toggle',
                        '#email_secure'
                    ],
                    ['enabled']
                );
                if ($(this).val() !== 'enabled') {
                    $('#email_password_section').hide();
                    $('#email_password_changed').val('0');
                }
                toggleDependent(
                    '#email_auth',
                    ['#email_username', '#email_password_toggle', '#email_secure'],
                    ['enabled']
                );
                if ($('#email_auth').val() !== 'enabled') {
                    $('#email_password_section').hide();
                    $('#email_password_changed').val('0');
                }
                markDisabledFields();
            });
            const turnstilePlaceholder = "N/A - Tier is 'dev'";
            function toggleTierTurnstileFields() {
                const isDevTier = $('#general_tier').val() === 'dev';
                ['#security_turnstile_site_key', '#security_turnstile_secret_key'].forEach(function(selector) {
                    const field = $(selector);
                    if (isDevTier) {
                        if (field.data('realvalue') === undefined || field.val() !== turnstilePlaceholder) {
                            field.data('realvalue', field.val());
                        }
                        field.val(turnstilePlaceholder);
                        field.prop('disabled', true);
                    } else {
                        if (field.val() === turnstilePlaceholder && field.data('realvalue') !== undefined) {
                            field.val(field.data('realvalue'));
                        }
                        field.prop('disabled', $('#security_turnstile').val() !== 'enabled');
                    }
                });
                markDisabledFields();
            }
            toggleTierTurnstileFields();
            $('#general_tier').on('change', toggleTierTurnstileFields);

            <?php if (!empty($configEditErrorTarget)) : ?>
                alert("<?php echo addslashes(strip_tags($configEditError));?>");
                var errorField = document.getElementsByName('<?php echo $configEditErrorTarget; ?>')[0]
                    || document.getElementById('<?php echo $configEditErrorTarget; ?>');
                if (errorField) {
                    errorField.classList.add('field-error');
                    errorField.focus();
                }
            <?php endif; ?>
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
        <?php if (!empty($configEditMessage)) : ?>
            <div class='alert-box success'>
                <span>success: </span><?php echo htmlspecialchars($configEditMessage); ?>
            </div>
            <?php unset($_SESSION['config_save_message']); ?>
        <?php endif; ?>
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

            <?php
            $logLinesToShow = 8;
            $recentLogLines = getLogTailLines($logfile, $logLinesToShow); ?>
            <h3>Logs - last <?php echo $logLinesToShow . " lines"; ?></h3>
            <?php
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
            <h3>Site administration</h3>
            <table>
                <tbody>
                    <tr>
                        <td class="options_left">
                            <h4>CSS</h4>
                            <?php
                            if (strpos($cssver, "min") == true) :
                                echo "Current CSS status: Using minified";
                            else :
                                    echo
                                        "Current CSS status: Using unminified";
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
                            <span id="inisettings">Clear all Scryfall data from JSON table</span>
                        </td>
                        <td>
                            <form action="/admin/admin.php">
                                <input class='profilebutton' type="submit" value="WIPE JSON" />
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
                            <h3>Configuration settings</h3>
                        </td>
                        <td>
                            <?php
                            if ($configEditMessage !== '') :
                                echo '<div class="successmsg">'
                                    . htmlspecialchars($configEditMessage, ENT_QUOTES, 'UTF-8') . '</div>';
                            endif;
                            if ($configEditUnlocked) :
                                if ($configEditExpiry) :
                                    echo '<div>Editing unlocked until ' . date('H:i', $configEditExpiry) . '</div>';
                                endif; ?>
                                <form
                                    method="post"
                                    action="admin.php#inisettings"
                                    style="display:inline-block; margin-right: 10px;"
                                >
                                    <input type="hidden" name="config_action" value="cancel_config_edit">
                                    <input class='profilebutton' type="submit" value="CANCEL" />
                                </form>
                                <?php if ($configEditUnlocked) : ?>
                                    <button
                                        class="profilebutton"
                                        type="button"
                                        onclick="document.getElementById('configedit').requestSubmit();"
                                    >
                                        SAVE
                                    </button>
                                <?php endif; ?>
                                <?php
                            else : ?>
                                <form method="post" action="admin.php#inisettings">
                                    <input type="hidden" name="config_action" value="start_reauth">
                                    <input class='profilebutton' type="submit" value="SHOW/EDIT" />
                                </form> <?php
                            endif; ?>
                        </td>
                    </tr>
                    <?php if ($configAuthRequested && !$configEditUnlocked) : ?>
                    <tr>
                        <td class="options_left" colspan="2">
                            <form method="post" action="admin.php#inisettings" class="config-reauth-form">
                                <label>
                                    Re-authenticate to edit configuration<br>
                                    <input
                                        class="textinput"
                                        type="password"
                                        name="config_password"
                                        id="config_password"
                                        autocomplete="current-password"
                                    >
                                </label>
                                <br>
                                <button class='profilebutton' type="submit" name="config_action" value="reauth_submit">
                                    CONFIRM
                                </button>
                                <button
                                    class='profilebutton'
                                    type="submit"
                                    name="config_action"
                                    value="cancel_config_edit"
                                >
                                    CANCEL
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="2">
                            <?php if ($configEditUnlocked) :
                                $imgLocationValue = htmlspecialchars($iniArray['general']['ImgLocation']);
                                $copyrightValue = htmlspecialchars($iniArray['general']['Copyright']);
                                $dbServerValue = htmlspecialchars($iniArray['database']['DBServer']);
                                $badLoginLimitValue = htmlspecialchars($iniArray['security']['Badloginlimit']);
                                ?>
                                <form id="configedit" method="post" action="admin.php">
                                    <input type="hidden" name="config_action" value="save_ini">
                                    <input
                                        type="hidden"
                                        name="database_password_changed"
                                        id="database_password_changed"
                                        value="0"
                                    >
                                    <input
                                        type="hidden"
                                        name="email_password_changed"
                                        id="email_password_changed"
                                        value="0"
                                    >
                                    <div class="config-grid">
                                        <div class="config-section">
                                            <h4>General settings</h4>
                                            <label>Title<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_title"
                                                    <?php echo $configInputStyle;?>
                                                    title="Site title shown to users"
                                                    value="<?php
                                                        echo htmlspecialchars($iniArray['general']['title']); ?>"
                                                >
                                            </label><br>
                                            <label>Tier<br>
                                                <select
                                                    name="general_tier"
                                                    id="general_tier"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="dev uses built-in dev Turnstile keys; "
                                                           . "prod uses configured keys"
                                                >
                                                    <option value="dev"
                                                        <?php if ($iniArray['general']['tier'] === 'dev') :
                                                            echo 'selected';
                                                        endif;?>
                                                    >dev</option>
                                                    <option value="prod"
                                                        <?php if ($iniArray['general']['tier'] === 'prod') :
                                                            echo 'selected';
                                                        endif;?>
                                                    >prod</option>
                                                </select>
                                            </label><br>
                                            <label>Image file path<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_img_location"
                                                    <?php echo $configInputStyle;?>
                                                    title="Directory where card images are stored (must be writable)"
                                                    value="<?php echo $imgLocationValue;?>"
                                                >
                                            </label><br>
                                            <label>Logfile path<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_logfile"
                                                    <?php echo $configInputStyle;?>
                                                    title="Full path to application logfile (must be writable)"
                                                    value="<?php
                                                        echo htmlspecialchars($iniArray['general']['Logfile']);?>"
                                                >
                                            </label><br>
                                            <label>Timezone<br>
                                                <select
                                                    class="textinput"
                                                    name="general_timezone"
                                                    id="general_timezone"
                                                    <?php echo $configInputStyle;?>
                                                    title="Timezone for dates and logs"
                                                >
                                                    <?php foreach ($timezoneList as $timezoneItem) : ?>
                                                        <option
                                                            value="<?php echo htmlspecialchars($timezoneItem);?>"
                                                            <?php
                                                            if ($timezoneItem === $iniArray['general']['Timezone']) :
                                                                echo 'selected';
                                                            endif;
                                                            ?>
                                                        >
                                                            <?php echo htmlspecialchars($timezoneItem);?>
                                                        </option>
                                                    <?php endforeach;?>
                                                </select>
                                            </label><br>
                                            <label>Locale<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_locale"
                                                    <?php echo $configInputStyle;?>
                                                    title="Locale used for formatting numbers and dates"
                                                    value="<?php
                                                     echo htmlspecialchars($iniArray['general']['Locale']);?>"
                                                >
                                            </label><br>
                                            <label>Copyright<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_copyright"
                                                    <?php echo $configInputStyle;?>
                                                    title="Copyright text shown in footer"
                                                    value="<?php echo $copyrightValue;?>"
                                                >
                                            </label><br>
                                            <label>URL<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Base site URL (no trailing slash!)"
                                                    value="<?php
                                                        echo htmlspecialchars($iniArray['general']['URL']);?>"
                                                >
                                            </label><br>
                                            <label>Log level<br>
                                                <select
                                                    name="general_loglevel"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="Controls verbosity of application logging"
                                                >
                                                    <option value="1"
                                                        <?php if ($logLevelIni == 1) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >1 - Error</option>
                                                    <option value="2"
                                                        <?php if ($logLevelIni == 2) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >2 - Notice</option>
                                                    <option value="3"
                                                        <?php if ($logLevelIni == 3) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >3 - Debug</option>
                                                </select>
                                            </label>
                                        </div>
                                        <div class="config-section">
                                            <h4>Email settings</h4>
                                            <label>Email status<br>
                                                <select
                                                    name="email_status"
                                                    id="email_status"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="Enable or disable all email sending"
                                                >
                                                    <option value="enabled"
                                                        <?php if ($emailEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$emailEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>Server email<br>
                                                <input
                                                    class="textinput"
                                                    type="email"
                                                    id="email_server"
                                                    name="email_server"
                                                    <?php echo $configInputStyle;?>
                                                    title="From/Reply-To address used by emails"
                                                    value="<?php echo htmlspecialchars($serverEmail);?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Admin email<br>
                                                <input
                                                    class="textinput"
                                                    type="email"
                                                    id="email_admin"
                                                    name="email_admin"
                                                    <?php echo $configInputStyle;?>
                                                    title="Administrative contact email"
                                                    value="<?php echo htmlspecialchars($adminEmail);?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP debug<br>
                                                <select
                                                    name="email_smtp_debug"
                                                    id="email_smtp_debug"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="PHPMailer debug level"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                                    <option value="enabled"
                                                        <?php if ($smtpDebugEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$smtpDebugEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>SMTP host<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="email_host"
                                                    name="email_host"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP server hostname"
                                                    value="<?php echo htmlspecialchars($smtpParameters['SMTPHost']);?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP HELO name<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="email_helo"
                                                    name="email_helo"
                                                    <?php echo $configInputStyle;?>
                                                    title="Hostname sent in SMTP HELO/EHLO"
                                                    value="
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $smtpParameters['SMTPHelo'] ?? gethostname()
                                                    );
                                                    ?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP port<br>
                                                <input
                                                    class="textinput"
                                                    type="number"
                                                    id="email_port"
                                                    name="email_port"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP server port"
                                                    value="<?php echo htmlspecialchars($smtpParameters['SMTPPort']);?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP auth<br>
                                                <select
                                                    name="email_auth"
                                                    id="email_auth"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="Enable SMTP authentication"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                                    <option value="enabled"
                                                        <?php if ($emailAuthEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$emailAuthEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>SMTP username<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="email_username"
                                                    name="email_username"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP username"
                                                    value="<?php
                                                        echo htmlspecialchars($smtpParameters['SMTPUsername']);?>"
                                                    <?php if (!$emailAuthEnabled || !$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP secure<br>
                                                <select
                                                    name="email_secure"
                                                    id="email_secure"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP encryption mode"
                                                    <?php if (!$emailAuthEnabled || !$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                                    <option value="smtps"
                                                        <?php if ($smtpSecureIni === 'PHPMailer::ENCRYPTION_SMTPS') :
                                                            echo 'selected';
                                                        endif;?>
                                                    >SMTPS</option>
                                                    <option value="starttls"
                                                        <?php
                                                        if (
                                                            $smtpSecureIni === 'PHPMailer::ENCRYPTION_STARTTLS'
                                                        ) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >STARTTLS</option>
                                                    <option value="none"
                                                    <?php
                                                    if ($smtpSecureIni === 'none') :
                                                        echo 'selected';
                                                    endif;
                                                    ?>
                                                    >None</option>
                                                </select>
                                            </label><br>
                                            <label>Certificate validation<br>
                                                <select
                                                    name="email_verify"
                                                    id="email_verify"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="TLS certificate validation behavior"
                                                    <?php if (!$emailEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                                    <option value="verify"
                                                        <?php if ($smtpVerifyIni && $smtpVerifyIni !== '0') :
                                                            echo 'selected';
                                                        endif;?>
                                                    >Require valid certificate</option>
                                                    <option value="allow"
                                                        <?php if (!$smtpVerifyIni || $smtpVerifyIni === '0') :
                                                            echo 'selected';
                                                        endif;?>
                                                    >Allow self-signed/invalid</option>
                                                </select>
                                            </label>
                                            <button id="email_password_toggle" type="button" class="profilebutton"
                                                <?php if (!$emailAuthEnabled || !$emailEnabled) :
                                                    echo 'disabled';
                                                endif;?>
                                            >
                                                SMTP PASS
                                            </button>
                                            <div id="email_password_section" style="display:none;">
                                                <label>SMTP password<br>
                                                    <input
                                                        class="textinput"
                                                        type="password"
                                                        name="email_password"
                                                        autocomplete="new-password"
                                                        <?php echo $configInputStyle;?>
                                                        title="SMTP password"
                                                    >
                                                </label>
                                            </div>
                                        </div>
                                        <div class="config-section">
                                            <h4>Security settings</h4>
                                            <label>Admin IP<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="security_admin_ip"
                                                    <?php echo $configInputStyle;?>
                                                    title="Restrict admin login to this IP (disabled if empty)"
                                                    value="<?php
                                                        echo htmlspecialchars($iniArray['security']['AdminIP']);?>"
                                                >
                                            </label><br>
                                            <label>Bad login limit<br>
                                                <input
                                                    class="textinput"
                                                    type="number"
                                                    min="1"
                                                    name="security_badloginlimit"
                                                    <?php echo $configInputStyle;?>
                                                    title="Lock account after this many failed logins"
                                                    value="<?php echo $badLoginLimitValue;?>"
                                                >
                                            </label><br>
                                            <label>Turnstile<br>
                                                <select
                                                    name="security_turnstile"
                                                    id="security_turnstile"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="Enable/disable Cloudflare Turnstile on login"
                                                >
                                                    <option value="enabled"
                                                        <?php if ($turnstileEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$turnstileEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>Turnstile site key<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="security_turnstile_site_key"
                                                    name="security_turnstile_site_key"
                                                    <?php echo $configInputStyle;?>
                                                    title="Turnstile site key (prod tier only)"
                                                    value="<?php
                                                    if ($iniArray['general']['tier'] === 'dev') :
                                                        echo 'N/A - Tier is \'dev\'';
                                                    else :
                                                        echo htmlspecialchars($turnstileSiteKeyIni);
                                                    endif;
                                                    ?>"
                                                    data-realvalue="<?php
                                                        echo htmlspecialchars($turnstileSiteKeyIni);?>"
                                                    <?php
                                                    if (!$turnstileEnabled || $iniArray['general']['tier'] === 'dev') :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Turnstile secret key<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="security_turnstile_secret_key"
                                                    name="security_turnstile_secret_key"
                                                    value="<?php
                                                    if ($iniArray['general']['tier'] === 'dev') :
                                                        echo 'N/A - Tier is \'dev\'';
                                                    endif;
                                                    ?>"
                                                    placeholder="Leave blank to keep existing"
                                                    <?php echo $configInputStyle;?>
                                                    title="Turnstile secret key (prod tier only)"
                                                    data-realvalue=""
                                                    <?php
                                                    if (!$turnstileEnabled || $iniArray['general']['tier'] === 'dev') :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Trusted device duration (days)<br>
                                                <input
                                                    class="textinput"
                                                    type="number"
                                                    min="1"
                                                    name="security_trust_duration"
                                                    <?php echo $configInputStyle;?>
                                                    title="How long trusted devices remain valid"
                                                    value="<?php echo htmlspecialchars($trustDurationIni);?>"
                                                >
                                            </label>
                                            <h4>Disqus settings</h4>
                                            <label>Status<br>
                                                <select
                                                    name="comments_status"
                                                    id="comments_status"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="Enable or disable Disqus comments"
                                                >
                                                    <option value="enabled"
                                                        <?php if ($commentsEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$commentsEnabled) :
                                                            echo 'selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>Dev URL<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="comments_dev_url"
                                                    name="comments_dev_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Disqus shortname/URL for dev tier"
                                                    value="<?php echo htmlspecialchars($disqusDevUrlIni);?>"
                                                    <?php if (!$commentsEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Prod URL<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="comments_prod_url"
                                                    name="comments_prod_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Disqus shortname/URL for production tier"
                                                    value="<?php echo htmlspecialchars($disqusProdUrlIni);?>"
                                                    <?php if (!$commentsEnabled) :
                                                        echo 'disabled';
                                                    endif;?>
                                                >
                                            </label>
                                        </div>
                                        <div class="config-section">
                                            <h4>Database settings</h4>
                                            <label>Host<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="database_host"
                                                    <?php echo $configInputStyle;?>
                                                    title="Database host/server name"
                                                    value="<?php echo $dbServerValue;?>"
                                                >
                                            </label><br>
                                            <label>Database<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="database_name"
                                                    <?php echo $configInputStyle;?>
                                                    title="Database name"
                                                    value="<?php
                                                        echo htmlspecialchars($iniArray['database']['DBName']);?>"
                                                >
                                            </label><br>
                                            <label>User<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="database_user"
                                                    <?php echo $configInputStyle;?>
                                                    title="Database user name"
                                                    value="<?php
                                                        echo htmlspecialchars($iniArray['database']['DBUser']);?>"
                                                >
                                            </label><br>
                                            <button id="db_password_toggle" type="button" class="profilebutton">
                                                DB PASS
                                            </button>
                                            <div id="db_password_section" style="display:none;">
                                                <label>New password<br>
                                                    <input
                                                        class="textinput"
                                                        type="password"
                                                        name="database_dbpass"
                                                        autocomplete="new-password"
                                                        <?php echo $configInputStyle;?>
                                                        title="Set a new database password"
                                                    >
                                                </label>
                                            </div>
                                            <h4>FX settings</h4>
                                            <label>Freecurrency API key<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="fx_api_key"
                                                    <?php echo $configInputStyle;?>
                                                    title="Freecurrency API key"
                                                    value="<?php echo htmlspecialchars($fxApiIni);?>"
                                                >
                                            </label><br>
                                            <label>Freecurrency URL<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="fx_api_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Endpoint URL for Freecurrency API"
                                                    value="<?php echo htmlspecialchars($fxUrlIni);?>"
                                                >
                                            </label><br>
                                            <label>Local currency<br>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="fx_target_currency"
                                                    <?php echo $configInputStyle;?>
                                                    title="Default local currency code"
                                                    value="<?php echo htmlspecialchars($fxTargetCurrencyIni);?>"
                                                >
                                            </label>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
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
