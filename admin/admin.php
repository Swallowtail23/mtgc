<?php
/*
Version:     6.4
Date:        21/12/25
Name:        admin.php
Purpose:     Site control panel
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

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
    5.0 30/11/25 Tooltips, wider inputs, writable path checks, timezone select, extra cancel on DB password
    5.1 04/12/25 Add email settings test
    5.2 04/12/25 Add Scryfall JSON wipe success message
    5.3 04/12/25 Display current application version
    5.4 04/12/25 Trim SMTP HELO value whitespace
    6.0 16/12/25 Improve escaping and variable usage, refactor flow and PRGs
    6.1          Add scrollable/selectable log display
    6.2 21/12/25 Keep site title raw in email subjects
    6.3 21/12/25 Simplify site title usage
    6.4 21/12/25 Replace E_USER_ERROR trigger_error with exceptions for PHP 8.4 compatibility
*/
if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
if (empty($_SESSION['csrf_token'])) :
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
endif;
require('../includes/ini.php');             //Initialise and load ini file
require('../includes/error_handling.php');
require('../includes/functions.php');       //Includes basic functions for non-secure pages
require('../includes/secpagesetup.php');    //Setup page variables
forcePasswordChange();                      //Check if user is disabled or needs to change password
$msg = new Message($logfile);

function requireCsrfToken(): void
{
    $posted = (string) filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);
    $token  = $_SESSION['csrf_token'] ?? '';
    if ($posted === '' || !hash_equals($token, $posted)) :
        http_response_code(403);
        die('CSRF check failed');
    endif;
}

/**
 * Determine current version from env or VERSION file.
 */
function getAppVersion(string $fallback = 'dev'): string
{
    global $msg;

    $sanitize = function (string $value): string {
        $value = trim($value);

        // remove control chars (prevents log/HTML weirdness)
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        // keep version chars conservative
        $value = preg_replace('/[^A-Za-z0-9.\-_+]/', '', $value) ?? '';

        // keep it short
        if (strlen($value) > 64) :
            $value = substr($value, 0, 64);
        endif;

        return $value;
    };

    // Prefer env (containers/CI)
    $envVersion = getenv('MTGC_VERSION');
    if ($envVersion !== false && $envVersion !== '') :
        $envVersion = $sanitize((string) $envVersion);
        if ($envVersion !== '') :
            $msg->logMessage('[DEBUG]', "Version (env): '$envVersion'");
            return $envVersion;
        endif;
    endif;

    // Then VERSION file (normal)
    $versionFile = __DIR__ . '/../VERSION';
    if (is_readable($versionFile)) :
        $fileVersion = $sanitize((string) file_get_contents($versionFile));
        if ($fileVersion !== '') :
            $msg->logMessage('[DEBUG]', "Version (VERSION): '$fileVersion'");
            return $fileVersion;
        endif;
    endif;

    $fallback = $sanitize($fallback);
    $msg->logMessage('[DEBUG]', "Version fallback: '$fallback'");
    return $fallback !== '' ? $fallback : 'dev';
}

$currentVersion = getAppVersion();

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

    // Remove trailing newlines.
    $output = rtrim($output, "\r\n");
    if ($output === '') :
        return [];
    endif;

    // Split on any newline type
    $allLines = preg_split("/\r\n|\n|\r/", $output);
    if (!is_array($allLines)) :
        return [];
    endif;

    return array_slice($allLines, -$maxLines);
}

function isPathWritable($path)
{
    if (!is_string($path)) :
        return false;
    endif;

    $path = trim($path);
    if ($path === '') :
        return false;
    endif;

    // If it's an existing directory, check writability directly
    if (is_dir($path)) :
        return is_writable($path);
    endif;

    // If it's an existing file, check writability directly
    if (is_file($path)) :
        return is_writable($path);
    endif;

    // Otherwise, treat it as a file that may not exist yet
    $directory = dirname($path);
    if ($directory === '.' || $directory === '' || !is_dir($directory)) :
        return false;
    endif;

    return is_writable($directory);
}

//Check if user is logged in, if not redirect to login.php
$msg->logMessage('[DEBUG]', "Admin page called by user $userName ($userEmail) Admin result: " . $admin);
if ($admin !== 1) :
    require('reject.php');
endif;

//Get date for update form
$dateObject = new DateYMD();
$date = $dateObject->getToday();

$scryAction = filter_input(INPUT_POST, 'scryfalljson_action', FILTER_UNSAFE_RAW);

if ($scryAction === 'wipe') :
    requireCsrfToken();

    if ($db->query('TRUNCATE TABLE scryfalljson') === true) :
        $msg->logMessage('[NOTICE]', "JSON data removed");
        $_SESSION['config_save_message'] = 'Scryfall JSON data removed.';
        $_SESSION['config_save_status'] = 'success';
        header('Location: admin.php');
        exit();
    else :
        throw new Exception("[ERROR] admin.php: JSON removal failed: " . $db->error);
    endif;
endif;

$cssAction = filter_input(INPUT_POST, 'css_action', FILTER_UNSAFE_RAW);

if ($cssAction !== null) :
    requireCsrfToken();

    if ($cssAction === 'unminify') :
        $msg->logMessage('[DEBUG]', 'Turning off minimised CSS...');
        $cssQuery = 0;
    elseif ($cssAction === 'minify') :
        $msg->logMessage('[DEBUG]', 'Turning on minimised CSS...');
        $cssQuery = 1;
    else :
        $cssQuery = null;
    endif;

    if ($cssQuery !== null) :
        $query = 'UPDATE admin SET usemin=?';

        if ($db->execute_query($query, [$cssQuery]) === true) :
            $msg->logMessage('[NOTICE]', 'CSS minification state updated');
            $_SESSION['config_save_message'] = 'CSS setting updated.';
            $_SESSION['config_save_status'] = 'success';
        else :
            $msg->logMessage('[ERROR]', 'CSS toggle failed: ' . $db->error);
            $_SESSION['config_save_message'] = 'CSS setting update failed. Check logs.';
            $_SESSION['config_save_status'] = 'error';
        endif;

        // Redirect to avoid resubmission on refresh
        header('Location: admin.php');
        exit();
    else :
        // Unknown action - treat as error and redirect
        $_SESSION['config_save_message'] = 'Invalid CSS action.';
        $_SESSION['config_save_status'] = 'error';
        header('Location: admin.php');
        exit();
    endif;
endif;

$mtceAction = filter_input(INPUT_POST, 'mtce_action', FILTER_UNSAFE_RAW);

if ($mtceAction !== null) :
    requireCsrfToken();

    $ok = false;

    if ($mtceAction === 'on') :
        $ok = setMtceMode('on');
    elseif ($mtceAction === 'off') :
        $ok = setMtceMode('off');
    endif;

    if ($ok) :
        $_SESSION['config_save_message'] = 'Maintenance mode updated.';
        $_SESSION['config_save_status']  = 'success';
    else :
        $_SESSION['config_save_message'] = 'Maintenance mode update failed. Check logs.';
        $_SESSION['config_save_status']  = 'error';
    endif;

    header('Location: admin.php');
    exit();
endif;

if (isset($_POST['update']) && $_POST['update'] === 'ADD') :
    requireCsrfToken();

    $update = 1;

    // Date (allow override but validate)
    $dateRaw = trim((string) filter_input(INPUT_POST, 'date', FILTER_UNSAFE_RAW));
    if ($dateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) :
        $date = $dateRaw;
    else :
        $date = $dateObject->getToday();
    endif;

    // Author: ALWAYS from session/user context (not POST)
    $name = strtolower(trim((string) $userName));

    // Update text
    $updateText = trim((string) filter_input(INPUT_POST, 'updatetext', FILTER_UNSAFE_RAW));

    if ($updateText === '') :
        $_SESSION['config_save_message'] = 'Update notice cannot be empty.';
        $_SESSION['config_save_status'] = 'error';
        header('Location: admin.php');
        exit();
    endif;

    if (strlen($updateText) > 1000) :
        $updateText = substr($updateText, 0, 1000);
    endif;

    $stmt = $db->prepare(
        "INSERT INTO updatenotices (`date`, `author`, `update`) VALUES (?, ?, ?)"
    );

    if ($stmt) :
        $bound = $stmt->bind_param("sss", $date, $name, $updateText);
        if ($bound === false) :
            $stmt->close();
            throw new Exception("[ERROR] admin.php: bind failed: " . $stmt->error);
        endif;
        $exec = $stmt->execute();
        if ($exec === true) :
            $msg->logMessage(
                '[NOTICE]',
                "Adding update notice: Insert ID: " . $stmt->insert_id
                . " Author (session): " . $name
            );
            $_SESSION['config_save_message'] = 'Update notice added.';
            $_SESSION['config_save_status'] = 'success';
            header('Location: admin.php');
            exit();
        else :
            throw new Exception(
                "[ERROR] admin.php: Adding update notice: failed " . $stmt->error
            );
        endif;
        $stmt->close();
    else :
        throw new Exception(
            "[ERROR] admin.php: Adding update notice: failed (prepare statement) " . $db->error
        );
    endif;
endif;

$deleteMode = filter_input(INPUT_POST, 'deleteMigrations', FILTER_UNSAFE_RAW);

if ($deleteMode === 'TEST' || $deleteMode === 'DELETE') :
    requireCsrfToken();

    $msg->logMessage('[DEBUG]', "Migrations {$deleteMode} requested");

    /*
     * Analyse what WOULD be affected
     * - how many migration rows are marked db_match=1
     * - how many matching cards_scry rows actually exist
     */
    $analysisSql = "
        SELECT
            COUNT(DISTINCT m.old_scryfall_id) AS migration_count,
            COUNT(DISTINCT c.id) AS cards_scry_count
        FROM migrations m
        LEFT JOIN cards_scry c
            ON c.id = m.old_scryfall_id
        WHERE m.db_match = 1
    ";

    $analysisResult = $db->query($analysisSql);
    if ($analysisResult === false) :
        throw new Exception(
            "[ERROR] admin.php: Migration analysis failed: " . $db->error
        );
    endif;

    $analysis = $analysisResult->fetch_assoc();
    $analysisResult->free();

    $migrationCount = (int) $analysis['migration_count'];
    $cardsScryCount = (int) $analysis['cards_scry_count'];

    $msg->logMessage(
        '[NOTICE]',
        "Migration analysis: migrations={$migrationCount}, cards_scry={$cardsScryCount}"
    );

    if ($deleteMode === 'TEST') :
        $_SESSION['migrations_test_count'] = $cardsScryCount;

        $_SESSION['config_save_message'] =
            "TEST result: {$migrationCount} migrations matched, "
            . "{$cardsScryCount} cards_scry rows would be deleted.";
        $_SESSION['config_save_status'] = 'success';

        header('Location: admin.php#migrationcards');
        exit();
    endif;

    if ($deleteMode === 'DELETE') :
        if ($migrationCount === 0) :
            $_SESSION['config_save_message'] = 'No migrations to delete.';
            $_SESSION['config_save_status'] = 'success';
            header('Location: admin.php');
            exit();
        endif;

        $db->begin_transaction();

        try {
            // 1) Delete matching cards_scry rows
            $deleteSql = "
                DELETE c
                FROM cards_scry c
                INNER JOIN migrations m
                    ON c.id = m.old_scryfall_id
                WHERE m.db_match = 1
            ";
            if ($db->query($deleteSql) === false) :
                throw new RuntimeException("cards_scry delete failed: " . $db->error);
            endif;
            $deletedCards = $db->affected_rows;

            // 2) Mark migrations as processed
            $updateSql = "UPDATE migrations SET db_match = 0 WHERE db_match = 1";
            if ($db->query($updateSql) === false) :
                throw new RuntimeException("migrations update failed: " . $db->error);
            endif;
            $updatedMigrations = $db->affected_rows;

            $db->commit();

            $msg->logMessage(
                '[NOTICE]',
                "Migrations delete committed: "
                . "deleted cards_scry={$deletedCards}, "
                . "updated migrations={$updatedMigrations}"
            );

            $_SESSION['config_save_message'] =
                "Deleted {$deletedCards} cards and cleared {$updatedMigrations} migrations.";
            $_SESSION['config_save_status'] = 'success';

            header('Location: admin.php');
            exit();
        } catch (Throwable $e) {
            $db->rollback();

            $msg->logMessage(
                '[ERROR]',
                "Migrations delete rolled back: " . $e->getMessage()
            );

            $_SESSION['config_save_message'] =
                'Delete failed; no changes applied. Check logs.';
            $_SESSION['config_save_status'] = 'error';

            header('Location: admin.php');
            exit();
        }
    endif;
endif;

$configEditUnlocked = false;
$configAuthRequested = false;
$configEditMessage = $_SESSION['config_save_message'] ?? '';
$configEditMessageType = $_SESSION['config_save_status'] ?? 'success';
if ($configEditMessageType !== 'error') :
    $configEditMessageType = 'success';
endif;
$configEditError = '';
$configEditErrorTarget = '';
$configAuthWindowSeconds = 600;
$configAction = filter_input(INPUT_POST, 'config_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$logLevelIni = $iniArray['general']['Loglevel'];
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
    'SMTPPassword' => $smtpPasswordIni,
    'SMTPSecure' => $smtpSecureIni,
    'SMTPHelo' => $smtpHeloIni,
    'SMTPVerifySSL' => $smtpVerifyIni,
    'SMTPDebug' => $smtpDebugIni,
    'globalDebug' => $logLevelIni
];
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
$titleValue = $iniArray['general']['title'] ?? '';

if (isset($_SESSION['config_edit_expires'])) :
    if ($_SESSION['config_edit_expires'] > time()) :
        $configEditUnlocked = true;
    else :
        unset($_SESSION['config_edit_expires']);
    endif;
endif;

if (isset($_POST['test_email']) && $_POST['test_email'] === 'send') :
    if (!$configEditUnlocked) :
        $_SESSION['config_save_message'] = 'Unlock config editing to run test email.';
        $_SESSION['config_save_status'] = 'error';
        header('Location: admin.php#inisettings');
        exit();
    endif;
    requireCsrfToken();

    if (!empty($serverEmail) && !empty($adminEmail)) :
        $mailer = new MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile, $siteTitle);
        $subject = "Test email from {$siteTitle}";
        $bodyHtml = "<p>This is a test email confirming SMTP settings are working.</p>";
        $bodyText = strip_tags($bodyHtml);

        $testOk = $mailer->sendEmail($adminEmail, true, $subject, $bodyHtml, $bodyText);
    else :
        $testOk = false;
    endif;

    $_SESSION['config_save_message'] = $testOk
        ? 'Test email sent successfully.'
        : 'Test email failed. Check SMTP settings.';
    $_SESSION['config_save_status'] = $testOk ? 'success' : 'error';

    header('Location: admin.php#inisettings');
    exit();
endif;

if ($configAction !== null) :
    requireCsrfToken();
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
    // Start from current config and selectively overwrite from POST below
    $updatedIni = $iniArray;

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
    $dbPasswordChanged = filter_input(INPUT_POST, 'database_password_changed', FILTER_VALIDATE_INT);
    if ($dbPasswordChanged === 1) :
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
    $turnstilePlaceholder = "N/A - Tier is 'dev'";
    $turnstileSiteKey = getPostedValue('security_turnstile_site_key', $turnstileSiteKeyIni);
    if ($turnstileSiteKey !== '' && $turnstileSiteKey !== $turnstilePlaceholder) :
        $updatedIni['security']['Turnstile_site_key'] = $turnstileSiteKey;
    endif;
    $turnstileSecretKey = getPostedValue('security_turnstile_secret_key', $turnstileSecretKeyIni);
    if ($turnstileSecretKey !== '' && $turnstileSecretKey !== $turnstilePlaceholder) :
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
    $previousEmailStatus = $iniArray['email']['Email'] ?? 'enabled';
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
    $emailPasswordChanged = filter_input(INPUT_POST, 'email_password_changed', FILTER_VALIDATE_INT);
    if ($emailPasswordChanged === 1) :
        $updatedIni['email']['Password'] = getPostedValue('email_password', $smtpPasswordIni);
    endif;
    $smtpSecureChoice = getPostedValue('email_secure', $smtpSecureChoice);
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
            $_SESSION['config_save_status'] = 'success';
            if ($previousEmailStatus === 'enabled' && $updatedIni['email']['Email'] === 'disabled') :
                $msg->logMessage('[NOTICE]', "Email disabled; clearing 2FA for all users");
                if (
                    $db->execute_query(
                        "UPDATE users SET tfa_enabled = 0, tfa_method = NULL, tfa_backup_codes = NULL, "
                        . "tfa_app_secret = NULL WHERE tfa_enabled = 1"
                    )
                ) :
                    $cleared = $db->affected_rows;
                    $_SESSION['config_save_message'] .= " 2FA disabled for $cleared users.";
                else :
                    $msg->logMessage('[ERROR]', "Failed to clear 2FA when disabling email: " . $db->error);
                    $_SESSION['config_save_message'] .= " (2FA clear failed; check logs.)";
                endif;
            endif;
            // Update runtime values to reflect saved config for the next request
            $iniArray = $updatedIni;
            $logfile = $updatedIni['general']['Logfile'];
            $logLevelIni = $updatedIni['general']['Loglevel'] ?? $logLevelIni;
            $msg = new Message($logfile);
            header('Location: admin.php');
            exit();
        else :
            $configEditError = 'Saving configuration failed. Check ini file permissions.';
            $configEditMessage = $configEditError;
            $configEditMessageType = 'error';
        endif;
    else :
        $messages = array_map(function ($err) {
            return $err['message'];
        }, $pathErrors);
        $configEditError = "<div class='alert-box error'><span>error: </span>"
            . htmlspecialchars(
                implode(' ', $messages),
                ENT_NOQUOTES,
                'UTF-8'
            )
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
    <title><?php echo $siteTitleEsc;?> - admin (site)</title>
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
                if (($('#updatetext').val() === '') || ($('#updatedate').val() === '')) {
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

            // --- Email UI: single source of truth ---
            function applyEmailUiState() {
                var emailEnabled = ($('#email_status').val() === 'enabled');
                var authEnabled  = ($('#email_auth').val() === 'enabled');

                // 1) Fields controlled by email status
                toggleDependent(
                    '#email_status',
                    [
                        '#email_server',
                        '#email_admin',
                        '#email_smtp_debug',
                        '#email_host',
                        '#email_helo',
                        '#email_port',
                        '#email_auth',
                        '#email_username',
                        '#email_password_toggle',
                        '#email_secure',
                        '#email_verify'
                    ],
                    ['enabled']
                );

                // 2) Fields controlled by SMTP auth (but also respect email status)
                toggleDependent(
                    '#email_auth',
                    ['#email_username', '#email_password_toggle', '#email_secure'],
                    ['enabled']
                );

                if (!emailEnabled) {
                    $('#email_username, #email_password_toggle, #email_secure').prop('disabled', true);
                }

                // 3) Password section visibility + changed flag hygiene
                if (!emailEnabled || !authEnabled) {
                    $('#email_password_section').hide();
                    $('#email_password_changed').val('0');
                    $('#email_password_section').find('input[type="password"]').val('');
                }
                markDisabledFields();
            }

            // Run once on load after setupPasswordSection is configured
            applyEmailUiState();

            // Then wire to both controllers
            $('#email_auth').on('change', applyEmailUiState);
            $('#email_status').on('change', applyEmailUiState);

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
                alert(
                    <?php echo json_encode(
                        strip_tags($configEditError),
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                    ?>
                );
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
            <?php
            $messageClass = ($configEditMessageType === 'error') ? 'error' : 'success';
            ?>
            <div class="alert-box <?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo htmlspecialchars($messageClass, ENT_NOQUOTES, 'UTF-8'); ?>: </span>
                <?php echo htmlspecialchars($configEditMessage, ENT_NOQUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['config_save_message']); ?>
            <?php unset($_SESSION['config_save_status']); ?>
        <?php endif; ?>
        <p><strong>Current version:</strong> <?php echo htmlspecialchars($currentVersion, ENT_NOQUOTES, 'UTF-8'); ?></p>
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
                            <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                            <input class='profilebutton' name='update' type="submit" value="ADD">
                        </td>
                    </tr>
                </table>
            </form>

            <?php
            $logLinesRequested = filter_input(INPUT_GET, 'log_lines', FILTER_SANITIZE_NUMBER_INT);
            $logLinesToShow = ($logLinesRequested !== null && $logLinesRequested !== false && $logLinesRequested > 0)
                ? (int) $logLinesRequested
                : 10;
            $recentLogLines = getLogTailLines($logfile, $logLinesToShow); ?>
            <h3 id="logs">Logs</h3>
            <form action="admin.php#logs" method="get" style="margin-bottom: 8px;">
                <label for="log_lines">
                    Show last
                    <select
                        id="log_lines"
                        name="log_lines"
                        onchange="this.form.submit();"
                    >
                        <?php
                        $logOptions = array(10, 25, 50, 100, 200);
                        foreach ($logOptions as $opt) :
                            $selected = ($opt === $logLinesToShow) ? 'selected' : '';
                            echo "<option value=\"" . (int) $opt . "\" $selected>$opt</option>";
                        endforeach;
                        ?>
                    </select>
                    lines
                </label>
            </form>
            <div id='logbox'>
                <?php
                if (empty($recentLogLines)) :
                    echo 'No log entries available or log file could not be read.<br>';
                else :
                    foreach ($recentLogLines as $line) :
                        echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br>";
                    endforeach;
                endif;
                ?>
            </div>

            <?php $mtceStatus = mtceModeCheck($user); ?>
            <br>
            <h3>Site administration</h3>
            <table>
                <tbody>
                    <tr>
                        <td class="options_left">
                            <h4>CSS</h4>
                            <?php
                            if (strpos($cssver, "min") !== false) :
                                echo "Current CSS status: Using minified";
                            else :
                                    echo
                                        "Current CSS status: Using unminified";
                            endif;?>
                        </td>
                        <td>
                            <?php
                            $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <?php if (strpos($cssver, 'min') !== false) : ?>
                                <form action="/admin/admin.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                    <input type="hidden" name="css_action" value="unminify">
                                    <input class="profilebutton" type="submit" value="UNMINIFY">
                                </form>
                            <?php else : ?>
                                <form action="/admin/admin.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                    <input type="hidden" name="css_action" value="minify">
                                    <input class="profilebutton" type="submit" value="MINIFY">
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h4>Scryfall JSON</h4>
                            <span id="inisettings">Clear all Scryfall data from JSON table</span>
                        </td>
                        <td>
                            <form action="/admin/admin.php" method="post">
                                <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                <input type="hidden" name="scryfalljson_action" value="wipe">
                                <input class='profilebutton' type="submit" value="WIPE JSON" />
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h4>Maintenance Mode</h4>
                            Current Maintenance mode status: <?php
                            if (($mtceStatus == 1) || ($mtceStatus == 2)) :
                                echo "On";
                            else :
                                echo "Off";
                            endif; ?>
                        </td>
                        <td> <?php
                            $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
                        if (($mtceStatus == 1) || ($mtceStatus == 2)) : ?>
                            <form action="/admin/admin.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                <input type="hidden" name="mtce_action" value="off">
                                <input class="profilebutton" id="mtce" type="submit" value="MTCE OFF">
                            </form>
                        <?php else : ?>
                            <form action="/admin/admin.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                <input type="hidden" name="mtce_action" value="on">
                                <input class="profilebutton" id="mtce" type="submit" value="MTCE ON">
                            </form>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="options_left">
                            <h3>Configuration settings</h3>
                        </td>
                        <td>
                            <?php
                            if ($configEditUnlocked) :
                                if ($configEditExpiry) :
                                    echo '<div>Editing unlocked until ' . date('H:i', $configEditExpiry) . '</div>';
                                endif; ?>
                                <form
                                    method="post"
                                    action="/admin/admin.php#inisettings"
                                    style="display:inline-block; margin-right: 10px;"
                                >
                                    <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                    <input type="hidden" name="config_action" value="cancel_config_edit">
                                    <input class='profilebutton' type="submit" value="CANCEL" />
                                </form>
                                <?php if ($configEditUnlocked) : ?>
                                    <button
                                        class="profilebutton"
                                        type="submit"
                                        form="configedit"
                                        name="config_action"
                                        value="save_ini"
                                    >SAVE</button>
                                <?php endif; ?>
                                <?php
                            else : ?>
                                <form method="post" action="/admin/admin.php#inisettings">
                                    <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                    <input type="hidden" name="config_action" value="start_reauth">
                                    <input class='profilebutton' type="submit" value="SHOW/EDIT" />
                                </form> <?php
                            endif; ?>
                        </td>
                    </tr>
                    <?php if ($configAuthRequested && !$configEditUnlocked) : ?>
                    <tr>
                        <td class="options_left" colspan="2">
                            <form method="post" action="/admin/admin.php#inisettings" class="config-reauth-form">
                                <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
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
                                <button 
                                    class='profilebutton'
                                    type="submit"
                                    name="config_action"
                                    value="reauth_submit"
                                >
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
                                $imgLocationValue    = $iniArray['general']['ImgLocation'] ?? '';
                                $copyrightValue      = $iniArray['general']['Copyright'] ?? '';
                                $tierValue           = $iniArray['general']['tier'] ?? '';
                                $logfileValue        = $iniArray['general']['Logfile'] ?? '';
                                $timezoneValue       = $iniArray['general']['Timezone'] ?? '';
                                $localeValue         = $iniArray['general']['Locale'] ?? '';
                                $urlValue            = $iniArray['general']['URL'] ?? '';
                                $dbServerValue       = $iniArray['database']['DBServer'] ?? '';
                                $dbNameValue         = $iniArray['database']['DBName']   ?? '';
                                $dbUserValue         = $iniArray['database']['DBUser']   ?? '';
                                $badLoginLimitValue  = $iniArray['security']['Badloginlimit'] ?? '';
                                $adminIpValue        = $iniArray['security']['AdminIP'] ?? '';
                                ?>
                                <form id="configedit" method="post" action="/admin/admin.php">
                                    <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo $csrfEsc; ?>"
                                    >
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
                                                <?php $titleValueEsc = htmlspecialchars(
                                                    $titleValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_title"
                                                    <?php echo $configInputStyle;?>
                                                    title="Site title shown to users"
                                                    value="<?php
                                                        echo $titleValueEsc; ?>"
                                                >
                                            </label><br>
                                            <label>Tier<br>
                                                <select
                                                    name="general_tier"
                                                    id="general_tier"
                                                    class="textinput"
                                                    <?php echo $configInputStyle;?>
                                                    title="dev uses fixed dev Turnstile keys; prod uses configured keys"
                                                >
                                                    <option value="dev"
                                                        <?php if ($tierValue === 'dev') :
                                                            echo ' selected';
                                                        endif;?>
                                                    >dev</option>
                                                    <option value="prod"
                                                        <?php if ($tierValue === 'prod') :
                                                            echo ' selected';
                                                        endif;?>
                                                    >prod</option>
                                                </select>
                                            </label><br>
                                            <label>Image file path<br>
                                                <?php $imgLocationEsc = htmlspecialchars(
                                                    $imgLocationValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_img_location"
                                                    <?php echo $configInputStyle;?>
                                                    title="Directory where card images are stored (must be writable)"
                                                    value="<?php echo $imgLocationEsc; ?>"
                                                >
                                            </label><br>
                                            <label>Logfile path<br>
                                                <?php $logfileValueEsc = htmlspecialchars(
                                                    $logfileValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_logfile"
                                                    <?php echo $configInputStyle;?>
                                                    title="Full path to application logfile (must be writable)"
                                                    value="<?php echo $logfileValueEsc; ?>"
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
                                                    <?php foreach ($timezoneList as $timezoneItem) :
                                                        $timezoneItemEsc = htmlspecialchars(
                                                            $timezoneItem,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>
                                                        <option
                                                            value="<?php echo $timezoneItemEsc;?>"
                                                            <?php
                                                            if ($timezoneItem === $timezoneValue) :
                                                                echo ' selected';
                                                            endif;
                                                            ?>
                                                        >
                                                            <?php echo $timezoneItemEsc;?>
                                                        </option>
                                                    <?php endforeach;?>
                                                </select>
                                            </label><br>
                                            <label>Locale<br>
                                                <?php $localeValueEsc = htmlspecialchars(
                                                    $localeValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_locale"
                                                    <?php echo $configInputStyle;?>
                                                    title="Locale used for formatting numbers and dates"
                                                    value="<?php
                                                     echo $localeValueEsc; ?>"
                                                >
                                            </label><br>
                                            <label>Copyright<br>
                                                <?php $copyrightValueEsc = htmlspecialchars(
                                                    $copyrightValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_copyright"
                                                    <?php echo $configInputStyle;?>
                                                    title="Copyright text shown in footer"
                                                    value="<?php echo $copyrightValueEsc; ?>"
                                                >
                                            </label><br>
                                            <label>URL<br>
                                                <?php $urlValueEsc = htmlspecialchars(
                                                    $urlValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="general_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Base site URL (no trailing slash!)"
                                                    value="<?php
                                                        echo $urlValueEsc; ?>"
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
                                                            echo ' selected';
                                                        endif;?>
                                                    >1 - Error</option>
                                                    <option value="2"
                                                        <?php if ($logLevelIni == 2) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >2 - Notice</option>
                                                    <option value="3"
                                                        <?php if ($logLevelIni == 3) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >3 - Debug</option>
                                                </select>
                                            </label>
                                        </div>
                                        <div class="config-section">
                                            <h4 class="email-settings-header">
                                                <span>Email settings</span>
                                                <button
                                                    class="profilebutton"
                                                    type="submit"
                                                    name="test_email"
                                                    value="send"
                                                >TEST</button>
                                            </h4>
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
                                                            echo ' selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$emailEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>Server email<br>
                                                <?php $serverEmailEsc = htmlspecialchars(
                                                    $serverEmail,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="email"
                                                    id="email_server"
                                                    name="email_server"
                                                    <?php echo $configInputStyle;?>
                                                    title="From/Reply-To address used by emails"
                                                    value="<?php echo $serverEmailEsc; ?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Admin email<br>
                                                <?php $adminEmailEsc = htmlspecialchars(
                                                    $adminEmail,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="email"
                                                    id="email_admin"
                                                    name="email_admin"
                                                    <?php echo $configInputStyle;?>
                                                    title="Administrative contact email"
                                                    value="<?php echo $adminEmailEsc; ?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo ' disabled';
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
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                                    <option value="enabled"
                                                        <?php if ($smtpDebugEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$smtpDebugEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>SMTP host<br>
                                                <?php $smtpHostEsc = htmlspecialchars(
                                                    $smtpParameters['SMTPHost'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="email_host"
                                                    name="email_host"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP server hostname"
                                                    value="<?php echo $smtpHostEsc; ?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP HELO name<br>
                                            <?php
                                            $smtpHeloValue = htmlspecialchars(
                                                $smtpParameters['SMTPHelo'] ?? gethostname(),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="email_helo"
                                                    name="email_helo"
                                                    <?php echo $configInputStyle;?>
                                                    title="Hostname sent in SMTP HELO/EHLO"
                                                    value="<?php echo $smtpHeloValue; ?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>SMTP port<br>
                                                <?php $smtpPortEsc = htmlspecialchars(
                                                    $smtpParameters['SMTPPort'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="number"
                                                    id="email_port"
                                                    name="email_port"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP server port"
                                                    value="<?php echo $smtpPortEsc; ?>"
                                                    <?php if (!$emailEnabled) :
                                                        echo ' disabled';
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
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                                    <option value="enabled"
                                                        <?php if ($emailAuthEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$emailAuthEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>SMTP username<br>
                                                <?php $smtpUsernameEsc = htmlspecialchars(
                                                    $smtpParameters['SMTPUsername'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="email_username"
                                                    name="email_username"
                                                    <?php echo $configInputStyle;?>
                                                    title="SMTP username"
                                                    value="<?php
                                                        echo $smtpUsernameEsc; ?>"
                                                    <?php if (!$emailAuthEnabled || !$emailEnabled) :
                                                        echo ' disabled';
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
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                                    <option value="smtps"
                                                        <?php if ($smtpSecureIni === 'PHPMailer::ENCRYPTION_SMTPS') :
                                                            echo ' selected';
                                                        endif;?>
                                                    >SMTPS</option>
                                                    <option value="starttls"
                                                        <?php
                                                        if (
                                                            $smtpSecureIni === 'PHPMailer::ENCRYPTION_STARTTLS'
                                                        ) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >STARTTLS</option>
                                                    <option value="none"
                                                    <?php
                                                    if ($smtpSecureIni === 'none') :
                                                        echo ' selected';
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
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                                    <option value="verify"
                                                        <?php if ($smtpVerifyIni && $smtpVerifyIni !== '0') :
                                                            echo ' selected';
                                                        endif;?>
                                                    >Require valid certificate</option>
                                                    <option value="allow"
                                                        <?php if (!$smtpVerifyIni || $smtpVerifyIni === '0') :
                                                            echo ' selected';
                                                        endif;?>
                                                    >Allow self-signed/invalid</option>
                                                </select>
                                            </label>
                                            <button id="email_password_toggle" type="button" class="profilebutton"
                                                <?php if (!$emailAuthEnabled || !$emailEnabled) :
                                                    echo ' disabled';
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
                                                <?php $AdminIpValueEsc = htmlspecialchars(
                                                    $adminIpValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="security_admin_ip"
                                                    <?php echo $configInputStyle;?>
                                                    title="Restrict admin login to this IP (disabled if empty)"
                                                    value="<?php
                                                        echo $AdminIpValueEsc; ?>"
                                                >
                                            </label><br>
                                            <label>Bad login limit<br>
                                                <?php $badLoginLimitValueEsc = htmlspecialchars(
                                                    $badLoginLimitValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="number"
                                                    min="1"
                                                    name="security_badloginlimit"
                                                    <?php echo $configInputStyle;?>
                                                    title="Lock account after this many failed logins"
                                                    value="<?php echo $badLoginLimitValueEsc;?>"
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
                                                            echo ' selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$turnstileEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>Turnstile site key<br>
                                                <?php $turnstileSiteKeyIniEsc = htmlspecialchars(
                                                    $turnstileSiteKeyIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="security_turnstile_site_key"
                                                    name="security_turnstile_site_key"
                                                    <?php echo $configInputStyle;?>
                                                    title="Turnstile site key (prod tier only)"
                                                    value="<?php
                                                    if ($tierValue === 'dev') :
                                                        echo 'N/A - Tier is \'dev\'';
                                                    else :
                                                        echo $turnstileSiteKeyIniEsc;
                                                    endif;
                                                    ?>"
                                                    data-realvalue="<?php
                                                        echo $turnstileSiteKeyIniEsc; ?>"
                                                    <?php
                                                    if (!$turnstileEnabled || $tierValue === 'dev') :
                                                        echo ' disabled';
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
                                                    if ($tierValue === 'dev') :
                                                        echo 'N/A - Tier is \'dev\'';
                                                    endif;
                                                    ?>"
                                                    placeholder="Leave blank to keep existing"
                                                    <?php echo $configInputStyle;?>
                                                    title="Turnstile secret key (prod tier only)"
                                                    <?php
                                                    if (!$turnstileEnabled || $tierValue === 'dev') :
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Trusted device duration (days)<br>
                                                <?php $trustDurationIniEsc = htmlspecialchars(
                                                    $trustDurationIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="number"
                                                    min="1"
                                                    name="security_trust_duration"
                                                    <?php echo $configInputStyle;?>
                                                    title="How long trusted devices remain valid"
                                                    value="<?php echo $trustDurationIniEsc;?>"
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
                                                            echo ' selected';
                                                        endif;?>
                                                    >enabled</option>
                                                    <option value="disabled"
                                                        <?php if (!$commentsEnabled) :
                                                            echo ' selected';
                                                        endif;?>
                                                    >disabled</option>
                                                </select>
                                            </label><br>
                                            <label>Dev URL<br>
                                                <?php $disqusDevUrlIniEsc = htmlspecialchars(
                                                    $disqusDevUrlIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="comments_dev_url"
                                                    name="comments_dev_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Disqus shortname/URL for dev tier"
                                                    value="<?php echo $disqusDevUrlIniEsc; ?>"
                                                    <?php if (!$commentsEnabled) :
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                            </label><br>
                                            <label>Prod URL<br>
                                                <?php $disqusProdUrlIniEsc = htmlspecialchars(
                                                    $disqusProdUrlIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    id="comments_prod_url"
                                                    name="comments_prod_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Disqus shortname/URL for production tier"
                                                    value="<?php echo $disqusProdUrlIniEsc; ?>"
                                                    <?php if (!$commentsEnabled) :
                                                        echo ' disabled';
                                                    endif;?>
                                                >
                                            </label>
                                        </div>
                                        <div class="config-section">
                                            <h4>Database settings</h4>
                                            <label>Host<br>
                                                <?php $dbServerValueEsc = htmlspecialchars(
                                                    $dbServerValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="database_host"
                                                    <?php echo $configInputStyle;?>
                                                    title="Database host/server name"
                                                    value="<?php echo $dbServerValueEsc;?>"
                                                >
                                            </label><br>
                                            <label>Database<br>
                                                <?php $dbNameValueEsc = htmlspecialchars(
                                                    $dbNameValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="database_name"
                                                    <?php echo $configInputStyle;?>
                                                    title="Database name"
                                                    value="<?php
                                                        echo $dbNameValueEsc; ?>"
                                                >
                                            </label><br>
                                            <label>User<br>
                                                <?php $dbUserValueEsc = htmlspecialchars(
                                                    $dbUserValue,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="database_user"
                                                    <?php echo $configInputStyle;?>
                                                    title="Database user name"
                                                    value="<?php
                                                        echo $dbUserValueEsc; ?>"
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
                                                <?php $fxApiIniEsc = htmlspecialchars(
                                                    $fxApiIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="fx_api_key"
                                                    <?php echo $configInputStyle;?>
                                                    title="Freecurrency API key"
                                                    value="<?php echo $fxApiIniEsc; ?>"
                                                >
                                            </label><br>
                                            <label>Freecurrency URL<br>
                                                <?php $fxUrlIniEsc = htmlspecialchars(
                                                    $fxUrlIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="fx_api_url"
                                                    <?php echo $configInputStyle;?>
                                                    title="Endpoint URL for Freecurrency API"
                                                    value="<?php echo $fxUrlIniEsc; ?>"
                                                >
                                            </label><br>
                                            <label>Local currency<br>
                                                <?php $fxTargetCurrencyIniEsc = htmlspecialchars(
                                                    $fxTargetCurrencyIni,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                                <input
                                                    class="textinput"
                                                    type="text"
                                                    name="fx_target_currency"
                                                    <?php echo $configInputStyle;?>
                                                    title="Default local currency code"
                                                    value="<?php echo $fxTargetCurrencyIniEsc; ?>"
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

            <h3 id="migrationcards">Migration cards (Scryfall corrections)</h3> <?php
            $migrationsResult = $db->execute_query(
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
            if ($migrationsResult === false) :
                throw new Exception(
                    "[ERROR] admin.php:" . __LINE__ . " - SQL failure: " . $db->error
                );
            else :
                if ($migrationsResult->num_rows > 0) :
                    // Load users once (used for owned-card checks)
                    $userResultArray = array();
                    $sql3 = "SELECT usernumber, username FROM users";
                    $stmt3 = $db->prepare($sql3);
                    if ($stmt3) :
                        $stmt3->execute();
                        $stmt3->bind_result($userNumberRow, $userNameRow);
                        while ($stmt3->fetch()) :
                            $userResultArray[] = array(
                                'usernumber' => $userNumberRow,
                                'username'   => $userNameRow
                            );
                        endwhile;
                        $stmt3->close();
                    else :
                        throw new Exception(
                            "[ERROR] admin.php: Wrong SQL: ($sql3) Error: " . $db->error
                        );
                    endif; ?>
                    <script>
                        function confirmTestDelete() {
                            // Display a confirmation dialog
                            if (confirm("Are you sure you want to test delete all migrations?")) {
                                // If the user confirms, submit the form
                                document.getElementById("testDeleteForm").submit();
                            }
                        }
                    </script>
                    <script>
                        function confirmDelete() {
                            // Display a confirmation dialog
                            if (confirm("Are you sure you want to delete all migrations?")) {
                                // If the user confirms, submit the form
                                document.getElementById("deleteForm").submit();
                            }
                        }
                    </script>
                    <!-- Conditional display of buttons based on the $countSql variable -->
                    <?php
                        $totalMatchesInCardsScry = (int) ($_SESSION['migrations_test_count'] ?? 0);
                        unset($_SESSION['migrations_test_count']);
                    if ($totalMatchesInCardsScry > 0) : ?>
                            <!-- Display the quantity of rows found in the test -->
                            <p>Rows that would be deleted: <?php echo $totalMatchesInCardsScry; ?></p>

                            <!-- Display the DELETE button -->
                            <form id="deleteForm" method="post" action="/admin/admin.php">
                                <input type="hidden" name="deleteMigrations" value="DELETE">
                                <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                <button
                                    type="button"
                                    onclick="confirmDelete()"
                                >
                                    Delete ALL migrations (<?php echo $totalMatchesInCardsScry; ?>)
                                </button>
                            </form>
                            <?php
                    else : ?>
                            <!-- Display the TEST DELETE button with the $countSql variable -->
                            <form id="testDeleteForm" method="post" action="/admin/admin.php">
                                <input type="hidden" name="deleteMigrations" value="TEST">
                                <?php $csrfEsc = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfEsc; ?>">
                                <button 
                                    type="button"
                                    onclick="confirmTestDelete()"
                                >
                                    Test migrations deletion
                                </button>
                            </form>
                            <?php
                    endif; ?>

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
                    <?php
                    $rowNumber = 0;
                    while ($row = $migrationsResult->fetch_assoc()) :
                        $rowNumber = $rowNumber + 1;

                        // Find decks and owners of cards needing migration
                        $collectionResultArray = $resultArray = array();
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
                            throw new Exception("[ERROR] admin.php: Wrong SQL: ($sql2) Error: " . $db->error);
                        endif;
                        while ($stmt2->fetch()) :
                            $resultArray[] = array('deckname' => $deckName, 'deckowner' => $deckOwner);
                        endwhile;
                        $stmt2->close();

                        foreach ($userResultArray as $userArray) :
                            $table = (int) $userArray['usernumber'] . "collection";
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
                                if ($stmt4->error) :
                                    throw new Exception("[ERROR] admin.php: Bind error: " . $stmt4->error);
                                endif;
                                $stmt4->execute();
                                $stmt4->bind_result($total);
                            else :
                                throw new Exception(
                                    "[ERROR] cards.php: Wrong SQL: ($sql4) Error: " . $db->error
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
                                $oldId = $row['old_scryfall_id'] ?? '';
                                $oldIdText = htmlspecialchars($oldId, ENT_NOQUOTES, 'UTF-8');
                                $oldIdHref = htmlspecialchars(
                                    $myURL . '/carddetail.php?id=' . rawurlencode($oldId),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                                <a href="<?php echo $oldIdHref; ?>"><?php echo $oldIdText; ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($row['object'], ENT_NOQUOTES, 'UTF-8'); ?></td>

                            <td><?php
                                $cardToEdit = $row['old_scryfall_id'] ?? '';
                                $migrationStrategy = $row['migration_strategy'] ?? '';

                                $cardToEditText = htmlspecialchars($migrationStrategy, ENT_NOQUOTES, 'UTF-8');
                                $cardToEditHref = htmlspecialchars(
                                    $myURL . '/admin/cards.php?cardtoedit=' . rawurlencode($cardToEdit),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                                <a href="<?php echo $cardToEditHref; ?>"><?php echo $cardToEditText; ?></a>
                            </td>

                            <td><?php echo htmlspecialchars($row['metadata_name'], ENT_NOQUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['metadata_set_code'], ENT_NOQUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars(
                                    $row['metadata_collector_number'],
                                    ENT_NOQUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['note'], ENT_NOQUOTES, 'UTF-8'); ?></td>

                            <td><?php
                                $newId = $row['new_scryfall_id'] ?? '';

                                $newIdText = htmlspecialchars($newId, ENT_NOQUOTES, 'UTF-8');
                                $newIdHref = htmlspecialchars(
                                    $myURL . '/carddetail.php?id=' . rawurlencode($newId),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                                <a href="<?php echo $newIdHref; ?>"><?php echo $newIdText; ?></a>
                            </td>
                            <td><?php
                            if (!empty($resultArray)) :
                                echo '<table border="1">';
                                echo '<tr><th>Deck Name</th><th>Owner</th></tr>';
                                foreach ($resultArray as $deckresult) :
                                    $deckResultName = htmlspecialchars(
                                        $deckresult['deckname'],
                                        ENT_NOQUOTES,
                                        'UTF-8'
                                    );
                                    $deckResultOwner = htmlspecialchars(
                                        $deckresult['deckowner'] ?? '',
                                        ENT_NOQUOTES,
                                        'UTF-8'
                                    );
                                    echo '<tr>';
                                    echo '<td>' . $deckResultName . '</td>';
                                    echo '<td>' . $deckResultOwner . '</td>';
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
                                    $userResultOwner = htmlspecialchars(
                                        $userresult['owner'] ?? '',
                                        ENT_NOQUOTES,
                                        'UTF-8'
                                    );
                                    $userResultTotal = htmlspecialchars(
                                        (string) ($userresult['total'] ?? 0),
                                        ENT_NOQUOTES,
                                        'UTF-8'
                                    );
                                    echo '<tr>';
                                    echo '<td>' . $userResultOwner . '</td>';
                                    echo '<td>' . $userResultTotal . '</td>';
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
                    endwhile;
                    $migrationsResult->free(); ?>
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
