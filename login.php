<?php

/*
Version:     8.22
Date:        12/01/26
Name:        login.php
Purpose:     Check for existing session, process login.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\LoginHandler;
use MTG\Core\Http\UrlHelper;

// Bootstrap
$ctx                        = require __DIR__ . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$cssver                     = (string) $ctx->meta('cssver', '');
$serviceWorkerVersion       = (string) $ctx->meta('serviceWorkerVersion', 'v6');

$siteTitle                  = (string) $appConfig->general('title', '');
$turnstile                  = (int) $appConfig->security('turnstileEnabled', false);
$turnstile_site_key         = (string) $appConfig->security('turnstileSiteKey', '');
ob_start();

// Content
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

// Temporary variable to store a redirection URL
$redirectUrl = $_SESSION['redirect_url'] ?? null;
$redirectCandidate = null;
if (isset($_GET['redirect_to'])) :
    $redirectCandidate = UrlHelper::normalizeRedirectUrl($_GET['redirect_to']);
elseif (isset($_POST['redirect_to'])) :
    $redirectCandidate = UrlHelper::normalizeRedirectUrl($_POST['redirect_to']);
endif;
if ($redirectCandidate) :
    $redirectUrl = $redirectCandidate;
    $_SESSION['redirect_url'] = $redirectCandidate;
    $msg->logMessage('[DEBUG]', "Captured redirect_to override: $redirectCandidate");
endif;

$loginHandler = new LoginHandler($db, $appConfig);
$loginHandler->logStart();
$trustedDeviceResult = $loginHandler->attemptTrustedDeviceLogin($redirectUrl);

if ($trustedDeviceResult['redirect'] !== null) :
    header("Location: {$trustedDeviceResult['redirect']}");
    exit();
endif;

/*
 *  Check if user is already logged in. If yes, display error and redirect to
 *  index.php. If no - session destroy and display login page.
 */

if ($loginHandler->isLoggedIn()) :
    $msg->logMessage('[DEBUG]', 'User already logged in, showing already logged in page');
    $loginHandler->renderAlreadyLoggedInPage($siteTitleEsc, $cssver, $trustedDeviceResult['trusted_login']);
endif;

session_destroy();
startCustomSession(); // Start a new session after destroying the previous one

// Reassign the redirect URL to the new session
if ($redirectUrl) :
    $_SESSION['redirect_url'] = $redirectUrl;
endif;

/*
 *  User not already logged in (including by trusted device). Continuing to load login page.
 */
header('Cache-Control: max-age=0');

$loginHandler->logPageLoad($_POST, $_SESSION);

$loginHandler->handleTurnstileCheck($_POST, $_SERVER['REMOTE_ADDR']);
$loginHandler->handleTurnstileFailureFlag($_GET);

$loginData = $loginHandler->processLoginSubmission($_POST);
if ($loginData !== null) :
    $loginHandler->completeLogin($loginData, $_SESSION['redirect_url'] ?? 'index.php');
endif;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no"
    >
    <title><?php echo $siteTitleEsc;?></title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css"
    >
    <?php include APP_ROOT . '/includes/googlefonts.php'; ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body id="loginbody" class="body">
<?php include_once APP_ROOT . '/includes/analyticstracking.php'; ?>
    <div id="loginheader">
        <h2 id="h2"><?php echo $siteTitleEsc;?></h2>
        <?php
        echo '<br><form action="login.php" method="post"><input type="hidden" name="ac" value="log"> ';
        if ($redirectUrl) :
            $redirectEsc = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
            echo "<input type='hidden' name='redirect_to' value='{$redirectEsc}'>";
        endif;
        echo "<input class='textinput loginfield' type='email' name='email' autofocus placeholder='EMAIL'/>";
        echo "<br><br>";
        echo "<input class='textinput loginfield' type='password' name='password' placeholder='PASSWORD'/><br>";
        if ($turnstile === 1) :
            echo "<br>";
            echo "<div class='cf-turnstile' data-sitekey='$turnstile_site_key' "
                . "data-theme='light' data-callback='onTurnstileSuccess' "
                . "data-error-callback='onTurnstileError' data-expired-callback='onTurnstileExpired'></div>";
        endif;
        echo '<input type="submit" id="loginsubmit" value="LOGIN" disabled="disabled" />';
        echo '</form><br>'; ?>
            <div class='loginpagebutton'>
                <a href='reset.php'>RESET</a>
            </div>
            <script>
                (function () {
                    var submitButton = document.getElementById('loginsubmit');
                    var turnstileEnabled = <?php echo ($turnstile === 1) ? 'true' : 'false'; ?>;

                    if (!submitButton) {
                        return;
                    }

                    var enableButton = function () {
                        submitButton.disabled = false;
                    };

                    var disableButton = function () {
                        submitButton.disabled = true;
                    };

                    if (!turnstileEnabled) {
                        enableButton();
                        return;
                    }

                    window.onTurnstileSuccess = function () {
                        enableButton();
                    };

                    window.onTurnstileError = function () {
                        disableButton();
                    };

                    window.onTurnstileExpired = function () {
                        disableButton();
                    };
                })();
            </script>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
