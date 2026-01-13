<?php

/*
Version:     1.17
Date:        13/01/26
Name:        bootstrap_secure.php
Purpose:     Secure bootstrap wrapper that runs session setup.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Auth\SessionUser;
use MTG\Core\AppConfig;
use MTG\Admin\AdminSettings;
use MTG\Core\Message;

if (!defined('APP_ROOT')) :
    define('APP_ROOT', __DIR__);
endif;

$ctx = require APP_ROOT . '/bootstrap.php';

function mtgSecurePageSetup($db, AppConfig $appConfig): SessionUser
{
    if (!isset($_SESSION['user']) or !$_SESSION["logged"]) :
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];  // capture entered URL
        header("Location: /login.php");                       // check if user is logged in; else redirect to login.php
        exit();
    endif;

    // Session information \\
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    if ($userArray === false) :
        $msg = new Message($appConfig);
        $msg->logMessage('[ERROR]', "User array returned false - user no longer exists?");
        session_destroy();
        echo "<meta http-equiv='refresh' content='1;url=login.php'>";
        exit();
    endif;

    $userEmail = $_SESSION['useremail'] ?? '';              // get email address of user, available in SESSION
    $userNumber = (int) ($userArray['usernumber'] ?? 0);

    $mtceStatus = AdminSettings::checkMaintenanceMode($userNumber, $db, $appConfig);
    if ($mtceStatus == 1) :                           // check if site is in maintenance mode
        include APP_ROOT . '/mtcestub.php';
        session_destroy();
        exit();
    endif;

    return new SessionUser($userArray, $userEmail);
}

$sessionUser = mtgSecurePageSetup($ctx->db(), $ctx->config());
if (!$sessionUser instanceof SessionUser) :
    // Should not happen if mtgSecurePageSetup enforces auth, but keep contract strict.
    if (!headers_sent()) :
        header('Location: /login.php', true, 302);
    else :
        echo "<meta http-equiv='refresh' content='0;url=/login.php'>";
    endif;
    exit;
endif;
// Inject SessionUser info into $ctx
$ctx = $ctx->withSessionUser($sessionUser);
unset($sessionUser);

// Don't enforce password change on page to change password!
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($script !== 'profile.php') :
    SessionManager::forcePasswordChange($ctx->config());
endif;

return $ctx;
