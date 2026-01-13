<?php

/*
Version:     1.21
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

if (!function_exists('mtgSecurePageSetup')) :
    function mtgSecurePageSetup($db, AppConfig $appConfig): array
    {
        if (!isset($_SESSION['user']) or !$_SESSION["logged"]) :
            // capture entered URL
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            // check if user is logged in; else redirect to login.php
            header("Location: /login.php");
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

        return [
            'sessionUser' => new SessionUser($userArray, $userEmail),
            'mtceStatus' => $mtceStatus,
        ];
    }
endif;

$_secureContext = mtgSecurePageSetup($ctx->db(), $ctx->config());
$_sessionUser = $_secureContext['sessionUser'];
$_mtceStatus = $_secureContext['mtceStatus'];
$ctx = $ctx->withSessionUser($_sessionUser)->withMeta(['mtceStatus' => $_mtceStatus]);
unset($_sessionUser, $_mtceStatus, $_secureContext);

// Don't enforce password change on page to change password!
$_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($_script !== 'profile.php') :
    SessionManager::forcePasswordChange($ctx->config());
endif;

return $ctx;
