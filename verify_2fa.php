<?php

/*
Version:     1.8
Date:        29/11/25
Name:        verify_2fa.php
Purpose:     Complete the second step of two-factor authentication.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
session_regenerate_id(true);

require 'includes/ini.php';               // Include ini file
require 'includes/error_handling.php';    // Include error handler
require 'includes/functions.php';         // Include needed functions

$msg = new \MTG\Core\Message($logfile);
$cssver = cssVersionCheck();

if (!isset($_SESSION['user_pending_2fa'])) :
    $msg->logMessage('[ERROR]', 'Access to verify_2fa.php attempted without completing first factor authentication');
    header('Location: login.php');
    exit();
endif;

$user_id = (int) $_SESSION['user_pending_2fa'];
$email = $_SESSION['useremail_pending_2fa'];
$is_admin = $_SESSION['admin_pending_2fa'] ?? false;
$pwd_change_required = $_SESSION['chgpwd_pending_2fa'] ?? false;
$tfaManager = new \MTG\Auth\TwoFactorManager($db, $smtpParameters, $serverEmail, $logfile);
$tfa_method = $tfaManager->getMethod($user_id);

if (!isset($db) || !$db instanceof mysqli) :
    $msg->logMessage('[ERROR]', 'Database connection is invalid in verify_2fa.php');
    die('A database error occurred, please try again later');
endif;

if (!isset($_SESSION['csrf_token'])) :
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
endif;

$verification_attempted = false;
$verification_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) :
    $verification_attempted = true;
    $code = trim($_POST['code']);

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) :
        $msg->logMessage('[ERROR]', 'CSRF token mismatch in verify_2fa.php');
        die('Invalid request');
    endif;

    if (empty($code)) :
        $verification_error = 'Please enter a verification code';
    else :
        if ($tfaManager->verify($user_id, $code)) :
            $msg->logMessage('[NOTICE]', "2FA verification successful for user ID: $user_id ($email)");

            session_regenerate_id(true);
            $_SESSION['logged'] = true;
            $_SESSION['user'] = $user_id;
            $_SESSION['useremail'] = $email;
            $_SESSION['admin'] = $is_admin ? true : false;

            if ($pwd_change_required) :
                $_SESSION['chgpwd'] = true;
                $_SESSION['just_logged_in'] = true;
            endif;

            if (!loginStamp($email)) :
                $msg->logMessage('[ERROR]', "Failed to update last login timestamp for $email");
            endif;

            unset($_SESSION['user_pending_2fa']);
            unset($_SESSION['useremail_pending_2fa']);
            unset($_SESSION['admin_pending_2fa']);
            unset($_SESSION['chgpwd_pending_2fa']);

            if ($pwd_change_required) :
                header('Location: profile.php');
            else :
                if (isset($_SESSION['redirect_url_after_2fa'])) :
                    $redirect = $_SESSION['redirect_url_after_2fa'];
                    unset($_SESSION['redirect_url_after_2fa']);
                    header('Location: trust_device.php?redirect_to=' . urlencode($redirect));
                else :
                    header('Location: trust_device.php');
                endif;
            endif;
            exit();
        else :
            $verification_error = 'Invalid verification code. Please try again.';
            $msg->logMessage('[NOTICE]', "Failed 2FA verification attempt for user ID: $user_id ($email)");
        endif;
    endif;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) :
    if ($tfa_method === 'email') :
        $tfaManager->startVerification($user_id, $email);
        $msg->logMessage('[NOTICE]', "Verification code resent for user ID: $user_id ($email)");
    endif;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) :
    unset($_SESSION['user_pending_2fa']);
    unset($_SESSION['useremail_pending_2fa']);
    unset($_SESSION['admin_pending_2fa']);
    unset($_SESSION['chgpwd_pending_2fa']);
    unset($_SESSION['redirect_url_after_2fa']);

    session_destroy();
    $msg->logMessage('[NOTICE]', "2FA verification cancelled for user ID: $user_id ($email)");
    header('Location: login.php');
    exit();
endif;
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no"
    >
    <title><?php echo $siteTitleEsc;?> - Verification</title>
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css"
    >
    <?php include 'includes/googlefonts.php'; ?>
</head>
<body id="loginbody" class="body">
    <?php include_once 'includes/analyticstracking.php'; ?>
    <div id="loginheader">
    <h2 id="h2"><?php echo $siteTitleEsc;?> - Verification</h2>

        <div style="text-align: center; margin-bottom: 20px;">
            <?php if ($tfa_method === 'app') : ?>
                <p>Enter the code from your authenticator app or a backup code.</p>
            <?php else : ?>
                <p>A verification code has been sent to your email address.</p>
                <p>Please enter the code to complete your login.</p>
            <?php endif; ?>
        </div>

        <?php if ($verification_attempted && !empty($verification_error)) : ?>
            <div style="color: red; margin-bottom: 15px;">
                <?php echo htmlspecialchars($verification_error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form id="verifyform" action="verify_2fa.php" method="post">
            <input
                class='textinput loginfield'
                type='text'
                name='code'
                autofocus
                placeholder='VERIFICATION CODE'
                style="text-align: center; letter-spacing: 8px; font-size: 1.5em;"
            />
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <br><br>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <input type="submit" name="verify" id="loginsubmit" value="VERIFY" />
                <input type="submit" name="cancel" id="loginsubmit" value="CANCEL" />
                <?php if ($tfa_method === 'email') : ?>
                    <input type="submit" name="resend" id="loginsubmit" value="RESEND" form="verifyform" />
                <?php endif; ?>
            </div>
        </form>

    </div>
</body>
</html>
