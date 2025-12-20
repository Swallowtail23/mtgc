<?php

/*
Version:     3.9
Date:        21/12/25
Name:        reset.php
Purpose:     Password reset page, called from login.php.
Notes:       Does not run secpagesetup - not a secure page!
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0 05/09/17 Removed hard-coded email address, now uses ini.php
    2.1 25/11/25 Standard tidy-up
    2.2 28/11/25 Use PasswordCheck::passwordReset for reset requests
    2.3 29/11/25 Rename cssVersionCheck usage
    3.0 01/12/25 Token-based password reset flow
    3.1 04/12/25 Improve resilience and security around token management
    3.2 04/12/25 Add cancel button to return to login
    3.3 04/12/25 Hide reset form after password update message
    3.4 04/12/25 Enforce complexity and difference checks for token resets
    3.5 04/12/25 Require 2FA for password reset when enabled and notify user by email
    3.6 04/12/25 Keep token reset form visible after validation errors
    3.7 05/12/25 Preserve token URL on complexity/duplication failures
    3.8 21/12/25 Keep site title raw in email subjects
    3.9 21/12/25 Simplify site title usage
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
require 'classes/message.class.php';
require 'classes/twofactormanager.class.php';

$cssver = cssVersionCheck();
$msg = new Message($logfile);
$msg->logMessage('[DEBUG]', 'reset.php loaded');
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

$pwReset = new PasswordCheck($db, $logfile, $siteTitle);
$emailEnabledSetting = $iniArray['email']['Email'] ?? 'enabled';
$emailEnabledFlag = ($emailEnabledSetting === 'enabled');
$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$tokenEmail = $_POST['email'] ?? ($_GET['email'] ?? '');
$message = $_SESSION['reset_message'] ?? '';
if (!empty($_SESSION['reset_message'])) :
    unset($_SESSION['reset_message']);
endif;
$resetUserId = null;
$twofaRequired = false;
$twofaMethod = '';

if (!empty($tokenEmail)) :
    $resetUserRow = $db->execute_query(
        "SELECT usernumber, tfa_enabled, tfa_method FROM users WHERE email = ? LIMIT 1",
        [$tokenEmail]
    );
    if ($resetUserRow && $resetUserRow->num_rows === 1) :
        $resetRow = $resetUserRow->fetch_assoc();
        $resetUserId = (int) $resetRow['usernumber'];
        $twofaRequired = (bool) $resetRow['tfa_enabled'];
        $twofaMethod = $resetRow['tfa_method'] ?? '';
    endif;
endif;

if (!empty($token) && !empty($tokenEmail)) :
    $record = $pwReset->fetchResetRecord($tokenEmail);
    if (
        $record === null || $record['expires_at'] < date('Y-m-d H:i:s')
        || !password_verify($token, $record['token_hash'])
    ) :
        $message = "Reset link invalid or expired.";
        $token = '';
        $tokenEmail = '';
        if (!empty($_SESSION)) :
            $_SESSION = [];
        endif;
        if (session_status() === PHP_SESSION_ACTIVE) :
            session_regenerate_id(true);
        endif;
    else :
        if (!empty($_SESSION)) :
            $_SESSION = [];
        endif;
        if (session_status() === PHP_SESSION_ACTIVE) :
            session_regenerate_id(true);
        endif;
    endif;
endif;

if (!$emailEnabledFlag) :
    $message = "Password reset is unavailable because email is disabled.";
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) :
    if (isset($_POST['send_twofa_code'])) :
        $tfaManager = new TwoFactorManager($db, $smtpParameters, $serverEmail, $logfile);
        $sent = false;
        if (!empty($resetUserId) && $twofaRequired && $twofaMethod === 'email') :
            $sent = $tfaManager->startVerification($resetUserId, $tokenEmail);
            $msg->logMessage(
                '[DEBUG]',
                "2FA code send requested during reset for $tokenEmail, result: " . ($sent ? 'sent' : 'failed')
            );
        endif;
        header('Content-Type: text/html');
        if ($sent) :
            echo "<div class='alert-box notice' style='margin:20px;'><span>notice: </span>"
                . "Verification code sent to your email.</div>";
        else :
            echo "<div class='alert-box error' style='margin:20px;'><span>error: </span>"
                . "Failed to send verification code.</div>";
        endif;
        exit();
    endif;
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $token = $_POST['token'] ?? $token;
    $tokenEmail = $email;
    $newPassword = trim($_POST['new_password'] ?? '');
    $currentHash = null;
    $pwdRow = $db->execute_query(
        "SELECT usernumber, password, tfa_enabled, tfa_method FROM users WHERE email = ? LIMIT 1",
        [$email]
    );
    if ($pwdRow && $pwdRow->num_rows === 1) :
        $row = $pwdRow->fetch_assoc();
        $currentHash = $row['password'];
        $resetUserId = (int) $row['usernumber'];
        $twofaRequired = (bool) $row['tfa_enabled'];
        $twofaMethod = $row['tfa_method'] ?? '';
    endif;
    if (!validPass($newPassword)) :
        $_SESSION['reset_message'] = "Password does not meet complexity requirements.";
        header(
            'Location: reset.php?token=' . urlencode($token) . '&email=' . urlencode($tokenEmail)
        );
        exit();
    elseif (!empty($currentHash) && password_verify($newPassword, $currentHash)) :
        $_SESSION['reset_message'] = "New password must be different from the current password.";
        header(
            'Location: reset.php?token=' . urlencode($token) . '&email=' . urlencode($tokenEmail)
        );
        exit();
    else :
        $tfaManager = new TwoFactorManager($db, $smtpParameters, $serverEmail, $logfile);
        $twofaCode = trim($_POST['twofa_code'] ?? '');
        if (isset($_POST['send_twofa_code']) && $twofaRequired && $twofaMethod === 'email') :
            $tfaManager->startVerification($resetUserId, $email);
            $message = "Verification code sent to your email.";
        endif;
        if ($twofaRequired) :
            if ($twofaMethod === 'email' && $twofaCode === '') :
                $tfaManager->startVerification($resetUserId, $email);
                $message = "Two-factor code required. A verification code has been sent to your email.";
            elseif ($twofaCode === '') :
                $message = "Two-factor code required to change your password.";
            elseif (!$tfaManager->verify($resetUserId, $twofaCode)) :
                $message = "Invalid two-factor code. Please try again.";
            endif;
        endif;

        if (empty($message) && $pwReset->completeReset($email, $_POST['token'], $newPassword)) :
            session_destroy();
            $message = "Password updated. Please log in with your new password.";
            $redirectLogin = true;
        elseif (empty($message)) :
            $message = "Reset failed. The link may be invalid or expired.";
        endif;
    endif;
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') :
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $pwReset->requestResetToken($email);
    $message = "If the email address exists, a reset link has been sent.";
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
    <title><?php echo $siteTitleEsc;?> - reset</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css"
    >
    <?php include 'includes/googlefonts.php';?>
</head>
<body id="loginbody" class="body">
<div id="loginheader">
    <h2 id="h2"><?php echo $siteTitleEsc;?></h2>

    <?php if ($message !== '') : ?>
    <div class="alert-box notice" style="margin: 20px;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
        <?php if (!empty($redirectLogin)) : ?>
        <meta http-equiv='refresh' content='3;url=login.php'>
        <?php endif; ?>
    <?php endif; ?>

<?php if ($emailEnabledFlag && empty($redirectLogin) && !empty($token) && !empty($tokenEmail)) : ?>
    <form  id="resetform" action="?" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_NOQUOTES, 'UTF-8');?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($tokenEmail, ENT_NOQUOTES, 'UTF-8');?>">
        <br>Set a new password:<br><br>
        <input
            class='textinput loginfield'
            name='new_password'
            type='password'
            placeholder='NEW PASSWORD'
            size='30'
            required
        /><br>
        <?php if ($twofaRequired) : ?>
        <input
            class='textinput loginfield'
            name='twofa_code'
            type='text'
            placeholder='<?php echo ($twofaMethod === "app") ? "AUTHENTICATOR OR BACKUP CODE" : "EMAIL CODE"; ?>'
                size='30'
                autocomplete='one-time-code'
            ><br>
        <?php endif;
        if ($twofaRequired && $twofaMethod === 'email') : ?>
            <button
                class="sendreset"
                type="button"
                name="send_twofa_code"
                value="send"
                style="width: 90px;"
                id="send_twofa_code_btn"
            >SEND CODE</button>
        <?php endif; ?>
        <input class='sendreset' type="submit" value="SAVE"/>
        <button
            class='sendreset'
            type="button"
            onclick="window.location.href='login.php';"
        >CANCEL</button>
    </form>
<?php elseif ($emailEnabledFlag && empty($redirectLogin)) : ?>
    <form  action="?" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="submit">
        <br>Request password reset:<br><br>
        <?php echo "<input class='textinput loginfield' name='email' type='email' "
                   . "placeholder='EMAIL' size='30' required/><br>"; ?>
        <input class='sendreset' type="submit" value="SEND"/>
        <button
            class='sendreset'
            type="button"
            onclick="window.location.href='login.php';"
        >CANCEL</button>
    </form>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sendBtn = document.getElementById('send_twofa_code_btn');
    if (sendBtn) {
        sendBtn.addEventListener('click', function() {
            const form = document.getElementById('resetform');
            const token = form.querySelector('input[name="token"]').value;
            const email = form.querySelector('input[name="email"]').value;
            const data = new URLSearchParams();
            data.append('send_twofa_code', 'send');
            data.append('token', token);
            data.append('email', email);
            fetch('reset.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data.toString()
            })
            .then(r => r.text())
            .then((html) => {
                const container = document.createElement('div');
                container.innerHTML = html;
                const msgBox = container.querySelector('.alert-box.notice, .alert-box.error');
                if (msgBox) {
                    document.querySelector('#loginheader').insertAdjacentHTML('afterbegin', msgBox.outerHTML);
                }
            })
            .catch(() => {
                document.querySelector('#loginheader').insertAdjacentHTML(
                    'afterbegin',
                    "<div class='alert-box error' style='margin:20px;'><span>error: </span>"
                    + "Unable to send verification code right now.</div>"
                );
            });
        });
    }
});
</script>
<?php if (empty($redirectLogin)) : ?>
</div>
</div>
</body>
</html>
<?php else : ?>
</div>
</body>
</html>
<?php endif; ?>
</body>
</html>
