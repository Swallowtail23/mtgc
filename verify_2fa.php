<?php
/* Version:     1.3
    Date:       01/03/25
    Name:       verify_2fa.php
    Purpose:    Complete the second step of two-factor authentication
    
    @author     Simon Wilson
    @copyright  2025 MTG Collection

    Notes:      
    To do:      

    1.1         28/02/25
                Minor fixes, CSRF protection

    1.2         28/02/25
                Restored styling and missing includes

    1.3         01/03/25
                Code tweaks/tidy-up
*/

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();

// **Prevent session fixation attacks**
session_regenerate_id(true);

// Load required files
require_once('includes/ini.php');               // Include ini file
require_once('includes/error_handling.php');    // Include error handler
require_once('includes/functions.php');         // Include needed functions

// Initialize message object for logging
$msg = new Message($logfile);

// Get CSS Version
$cssver = cssver();

// Redirect to login.php if user has not completed first factor
if (!isset($_SESSION['user_pending_2fa'])):
    $msg->logMessage('[ERROR]', "Access to verify_2fa.php attempted without completing first factor authentication");
    header("Location: login.php");
    exit();
endif;

// Get user details from session
$user_id = (int) $_SESSION['user_pending_2fa']; // **Ensure (int) type safety**
$email = $_SESSION['useremail_pending_2fa'];
$is_admin = $_SESSION['admin_pending_2fa'] ?? false;
$pwd_change_required = $_SESSION['chgpwd_pending_2fa'] ?? false;

// Ensure `$db` is valid before proceeding
if (!isset($db) || !$db instanceof mysqli):
    $msg->logMessage('[ERROR]', "Database connection is invalid in verify_2fa.php");
    die("A database error occurred, please try again later");
endif;

// Generate CSRF token if not set
if (!isset($_SESSION["csrf_token"])):
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
endif;

// Process verification code submission
$verification_attempted = false;
$verification_error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify'])):
    $verification_attempted = true;
    $code = trim($_POST['code']);

    // **Check CSRF token**
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]):
        $msg->logMessage('[ERROR]', "CSRF token mismatch in verify_2fa.php");
        die("Invalid request");
    endif;

    if (empty($code)):
        $verification_error = 'Please enter a verification code';
    else:
        // Verify code
        $tfaManager = new TwoFactorManager($db, $smtpParameters, $serveremail, $logfile);
        if ($tfaManager->verify($user_id, $code)):
            // Code is valid, complete authentication
            $msg->logMessage('[NOTICE]', "2FA verification successful for user ID: $user_id ($email)");

            // **Regenerate session to prevent fixation**
            session_regenerate_id(true);
            $_SESSION["logged"] = TRUE;
            $_SESSION["user"] = $user_id;
            $_SESSION["useremail"] = $email;
            $_SESSION['admin'] = $is_admin ? TRUE : FALSE;

            // Set password change flag if applicable
            if ($pwd_change_required):
                $_SESSION["chgpwd"] = TRUE;
                $_SESSION['just_logged_in'] = TRUE;
            endif;

            // Update login timestamp
            if (!loginstamp($email)) {
                $msg->logMessage('[ERROR]', "Failed to update last login timestamp for $email");
            }

            // **Unset pending 2FA session variables**
            unset($_SESSION['user_pending_2fa']);
            unset($_SESSION['useremail_pending_2fa']);
            unset($_SESSION['admin_pending_2fa']);
            unset($_SESSION['chgpwd_pending_2fa']);

            // **Handle redirects**
            if ($pwd_change_required):
                header("Location: profile.php");
            else:
                // Redirect to trust_device.php with optional redirect URL
                if (isset($_SESSION['redirect_url_after_2fa'])):
                    $redirect = $_SESSION['redirect_url_after_2fa'];
                    unset($_SESSION['redirect_url_after_2fa']); // **Prevent future misdirects**
                    header("Location: trust_device.php?redirect_to=" . urlencode($redirect));
                else:
                    header("Location: trust_device.php");
                endif;
            endif;
            exit();
        else:
            $verification_error = 'Invalid verification code. Please try again.';
            $msg->logMessage('[NOTICE]', "Failed 2FA verification attempt for user ID: $user_id ($email)");
        endif;
    endif;
endif;

// Resend code if requested
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend'])):
    $tfaManager = new TwoFactorManager($db, $smtpParameters, $serveremail, $logfile);
    $tfaManager->startVerification($user_id, $email);
    $msg->logMessage('[NOTICE]', "Verification code resent for user ID: $user_id ($email)");
endif;

// Cancel verification if requested
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancel'])):
    // **Clear 2FA session data**
    unset($_SESSION['user_pending_2fa']);
    unset($_SESSION['useremail_pending_2fa']);
    unset($_SESSION['admin_pending_2fa']);
    unset($_SESSION['chgpwd_pending_2fa']);
    unset($_SESSION['redirect_url_after_2fa']);

    // Destroy session and redirect
    session_destroy();
    $msg->logMessage('[NOTICE]', "2FA verification cancelled for user ID: $user_id ($email)");
    header("Location: login.php");
    exit();
endif;

?>
<!DOCTYPE html>
    <head>
        <title><?php echo $siteTitle;?> - Verification</title>
        <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver ?>.css">
        <?php include('includes/googlefonts.php'); ?>
        <meta name="viewport" content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no">
    </head>
    <body id="loginbody" class="body">
        <?php include_once("includes/analyticstracking.php") ?>
        <div id="loginheader">    
            <h2 id='h2'><?php echo $siteTitle;?> - Verification</h2>

            <div style="text-align: center; margin-bottom: 20px;">
                <p>A verification code has been sent to your email address.</p>
                <p>Please enter the code to complete your login.</p>
            </div>

            <?php if ($verification_attempted && !empty($verification_error)): ?>
                <div style="color: red; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($verification_error); ?>
                </div>
            <?php endif; ?>

            <form action="verify_2fa.php" method="post">
                <input class='textinput loginfield' type='text' name='code' autofocus placeholder='VERIFICATION CODE' style="text-align: center; letter-spacing: 8px; font-size: 1.5em;"/>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">
                <br><br>
                <input type="submit" name="verify" id="loginsubmit" value="VERIFY" />
            </form>

            <div style="display: flex; justify-content: center; margin-top: 20px;">
                <form action="verify_2fa.php" method="post" style="margin-right: 10px;">
                    <input type="submit" name="resend" class="profilebutton" value="RESEND CODE" />
                </form>

                <form action="verify_2fa.php" method="post">
                    <input type="submit" name="cancel" class="profilebutton" value="CANCEL" />
                </form>
            </div>
        </div>
    </body>
</html>
