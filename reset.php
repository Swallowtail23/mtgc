<?php

/*
Version:     3.6
Date:        04/12/25
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

$cssver = cssVersionCheck();

$pwReset = new PasswordCheck($db, $logfile, $siteTitle);
$emailEnabledSetting = $iniArray['email']['Email'] ?? 'enabled';
$emailEnabledFlag = ($emailEnabledSetting === 'enabled');
$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$tokenEmail = $_POST['email'] ?? ($_GET['email'] ?? '');
$resetUserId = null;
$twofaRequired = false;
$twofaMethod = '';
$message = '';

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
        $message = "Password does not meet complexity requirements.";
    elseif (!empty($currentHash) && password_verify($newPassword, $currentHash)) :
        $message = "New password must be different from the current password.";
    else :
        $tfaManager = new TwoFactorManager($db, $smtpParameters, $serverEmail, $logfile);
        $twofaCode = trim($_POST['twofa_code'] ?? '');
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
    <title><?php echo htmlspecialchars($siteTitle);?> - reset</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo htmlspecialchars($cssver);?>.css">
    <?php include 'includes/googlefonts.php';?>
</head>
<body id="loginbody" class="body">
<div id="loginheader">
    <h2 id="h2"><?php echo htmlspecialchars($siteTitle);?></h2>

<?php if ($message !== '') : ?>
    <div class="alert-box notice" style="margin: 20px;">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php if (!empty($redirectLogin)) : ?>
        <meta http-equiv='refresh' content='3;url=login.php'>
    <?php endif; ?>
<?php endif; ?>

<?php if ($emailEnabledFlag && empty($redirectLogin) && !empty($token) && !empty($tokenEmail)) : ?>
    <form  action="?" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token);?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($tokenEmail);?>">
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
