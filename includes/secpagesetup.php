<?php

/*
Version:     3.35
Date:        12/01/26
Name:        secpagesetup.php
Purpose:     Secure page setup helper for session validation.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Auth\SessionUser;
use MTG\Core\AppConfig;
use MTG\Admin\AdminSettings;
use MTG\Core\Message;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

function mtgSecurePageSetup($db, AppConfig $appConfig): array
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
        include APP_ROOT . '/includes/mtcestub.php';
        session_destroy();
        exit();
    endif;

    $sessionUser = new SessionUser($userArray, $userEmail);
    // TODO: Legacy keys are for backwards compatibility; migrate pages to $ctx->sessionUser() accessors.
    $legacy = [
        'user' => $userNumber,
        'userName' => (string) ($userArray['username'] ?? ''),
        'mytable' => (string) ($userArray['table'] ?? ''),
        'collection_view' => (string) ($userArray['collection_view'] ?? ''),
        'admin' => (int) ($userArray['admin'] ?? 0),
        'grpinout' => (int) ($userArray['grpinout'] ?? 0),
        'groupid' => (int) ($userArray['groupid'] ?? 0),
        'fx' => (bool) ($userArray['fx'] ?? false),
        'targetCurrency' => (string) ($userArray['currency'] ?? ''),
        'rate' => is_numeric($userArray['rate'] ?? null) ? (float) $userArray['rate'] : 0.0,
        'userEmail' => $userEmail,
    ];

    return [
        'sessionUser' => $sessionUser,
        'legacy' => $legacy,
    ];
}
