<?php

/*
Version:     2.17
Date:        12/01/26
Name:        trust_device.php
Purpose:     Handle trusted device creation separately from the login flow.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Auth\TrustedDeviceManager;
use MTG\Core\Http\UrlHelper;

// Bootstrap

$appContext = require __DIR__ . '/bootstrap.php';

$siteTitle = (string) $appConfig->general('title', '');
$trustDuration = (int) $appConfig->security('trustDuration', 0);

// Regenerate session on privilege transition
if (session_status() === PHP_SESSION_ACTIVE) :
    session_regenerate_id(true);
endif;

// Redirect if not logged in
if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) :
    header('Location: login.php');
    exit();
endif;

// Content
$redirect_candidate = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? $_SESSION['redirect_url'] ?? 'index.php';
$redirect_to = UrlHelper::normalizeRedirectUrl($redirect_candidate) ?? 'index.php';
$msg->logMessage('[DEBUG]', "Resolved trust device redirect target: $redirect_to");

if (empty($_SESSION['trust_device_flow'])) :
    if ($redirect_to !== 'index.php') :
        $_SESSION['trust_device_flow'] = true;
        $msg->logMessage('[NOTICE]', 'Trust device flow flag missing; recovered from redirect target');
    else :
        $msg->logMessage('[ERROR]', 'Direct access to trust_device.php blocked (no flow flag)');
        header('Location: index.php');
        exit();
    endif;
endif;
unset($_SESSION['trust_device_flow']);

$csrfToken = SessionManager::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') :
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SessionManager::validateCsrfToken($submittedToken)) :
        $msg->logMessage('[ERROR]', 'CSRF token mismatch in trust_device.php');
        die('Invalid request.');
    endif;
endif;

$trust_choice = $_POST['trust_device'] ?? 'none';
if ($redirect_to === 'login.php') :
    $redirect_to = 'index.php';
endif;

unset($_SESSION['redirect_url']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') :
    $msg->logMessage('[DEBUG]', "Final redirect destination (POST): $redirect_to");
    $msg->logMessage('[DEBUG]', "Trust choice (POST): $trust_choice, Redirect: $redirect_to");
endif;

if ($trust_choice !== 'none') :
    if ($trust_choice === 'yes') :
        try {
            $user_id = (int) $_SESSION['user'];
            $msg->logMessage('[DEBUG]', "Creating trusted device for user $user_id");
            $deviceManager = new TrustedDeviceManager($db, $appConfig);
            $result = $deviceManager->createTrustedDevice($user_id, $trustDuration);
            $msg->logMessage(
                '[NOTICE]',
                'User ' . $_SESSION['useremail'] . ' trusted device result: ' . ($result ? 'success' : 'failed')
            );
        } catch (Exception $e) {
            $msg->logMessage('[ERROR]', 'Failed to create trusted device: ' . $e->getMessage());
        }
    else :
        $msg->logMessage('[DEBUG]', 'User chose not to trust this device');
    endif;

    $msg->logMessage('[DEBUG]', "Redirecting to $redirect_to");
    header("Location: $redirect_to");
    exit();
else :
    $msg->logMessage('[DEBUG]', 'Trust choice not yet set, display the trust form');
    $siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no"
        >
        <title><?php echo $siteTitleEsc;?> - Trust Device</title>
        <link rel="manifest" href="/manifest.json" />
        <link
            rel="stylesheet"
            type="text/css"
            href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css"
        >
        <?php include APP_ROOT . '/includes/googlefonts.php'; ?>
    </head>
    <body id="loginbody" class="body">
        <?php include_once APP_ROOT . '/includes/analyticstracking.php'; ?>
        <div id="loginheader">
            <h2 id="h2"><?php echo $siteTitleEsc;?></h2>
                <p>You are logged in<?php echo isset($_SESSION['admin']) && $_SESSION['admin'] ? '!' : ''; ?></p>
            <div id="trust-device-prompt" style="text-align: center; margin-top: 20px;">
                <form action="trust_device.php" method="post">
                    <p>Would you like to trust this device for <?php echo $trustDuration; ?> days?</p>
                    <p><small>Clicking the site's logout button will cancel this device trust</small></p>
                    <input type="hidden" name="trust_device" value="yes">
                    <input
                        type="hidden"
                        name="redirect_to"
                        value="<?php echo htmlspecialchars($redirect_to, ENT_NOQUOTES, 'UTF-8'); ?>"
                    >
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button
                        type="submit"
                        class="profilebutton"
                        style="background-color: #4CAF50; margin-right: 10px;"
                    >TRUST</button>
                </form>

                <form action="trust_device.php" method="post" style="margin-top: 10px;">
                    <input type="hidden" name="trust_device" value="no">
                    <input
                        type="hidden"
                        name="redirect_to"
                        value="<?php echo htmlspecialchars($redirect_to, ENT_NOQUOTES, 'UTF-8'); ?>"
                    >
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="profilebutton" style="margin-right: 10px;">NOT NOW</button>
                </form>
            </div>
        </div>
    </body>
</html>
    <?php
endif;
?>
