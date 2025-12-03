<?php

/*
Version:     3.3
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
$token = $_GET['token'] ?? '';
$tokenEmail = $_GET['email'] ?? '';
$message = '';

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
    $newPassword = trim($_POST['new_password'] ?? '');
    if ($pwReset->completeReset($email, $_POST['token'], $newPassword)) :
        session_destroy();
        $message = "Password updated. Please log in with your new password.";
        $redirectLogin = true;
    else :
        $message = "Reset failed. The link may be invalid or expired.";
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
