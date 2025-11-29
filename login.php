<?php

/*
Version:     7.7
Date:        29/11/25
Name:        login.php
Purpose:     Check for existing session, process login.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0         Moved from writelog to Message class; reset bad login count after good login
    3.0         Moved to password-verify
    4.0         Corrected logic around invalid user emails
    5.0         Added Cloudflare Turnstile protection
    6.0 09/12/23 Add redirect capture
    6.1 20/01/24 Move to logMessage
    7.0 28/02/25 Trusted devices capability
    7.1 25/11/25 Update UserStatus method calls to camelCase
    7.2 25/11/25 Standard tidy-up
    7.3 27/11/25 Use dedicated variable for Turnstile client
    7.4 27/11/25 Disable login submit until Turnstile success
    7.5 27/11/25 Enable login submit via JS when Turnstile disabled or success
    7.6 28/11/25 Extract login handling into LoginHandler class for clarity
    7.7 29/11/25 Rename cssVersionCheck usage
*/

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;

startCustomSession();

require 'includes/ini.php';               // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php';         // Includes basic functions for non-secure pages

$msg = new Message($logfile);
$loginHandler = new LoginHandler(
    $db,
    $logfile,
    $turnstile,
    $turnstile_secret_key,
    $Badloglimit,
    $siteTitle,
    $smtpParameters,
    $serverEmail
);

if (!isset($db) || !$db instanceof mysqli) :
    $msg->logMessage('[ERROR]', 'Database connection is null or invalid in login.php');
    die('A database error occurred. Please try again later.');
endif;

$cssver = cssVersionCheck();

// Temporary variable to store a redirection URL
$redirectUrl = $_SESSION['redirect_url'] ?? null;

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
    $loginHandler->renderAlreadyLoggedInPage($siteTitle, $cssver, $trustedDeviceResult['trusted_login']);
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no"
    >
    <title><?php echo htmlspecialchars($siteTitle);?></title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo htmlspecialchars($cssver);?>.css">
    <?php include 'includes/googlefonts.php'; ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body id="loginbody" class="body">
<?php include_once 'includes/analyticstracking.php'; ?>
    <div id="loginheader">
        <h2 id="h2"><?php echo htmlspecialchars($siteTitle);?></h2>
        <?php
        $loginHandler->handleTurnstileCheck($_POST, $_SERVER['REMOTE_ADDR']);
        $loginHandler->handleTurnstileFailureFlag($_GET);

        $loginData = $loginHandler->processLoginSubmission($_POST);
        if ($loginData !== null) :
            $loginHandler->completeLogin($loginData, $_SESSION['redirect_url'] ?? 'index.php');
        else :
            echo '<br><form action="login.php" method="post"><input type="hidden" name="ac" value="log"> ';
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
            <?php endif; ?>
    </div>
</body>
</html>
