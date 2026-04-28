<?php

/*
Version:     1.1
Date:        28/04/26
Name:        ajax_session.php
Purpose:     Shared session-user loader for AJAX endpoints without full secure bootstrap.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Auth\SessionUser;
use MTG\Core\AppConfig;
use MTG\Core\Message;
use MTG\Core\Http\AjaxResponse;

if (!function_exists('requireAjaxSessionUser')) :
    function requireAjaxSessionUser(mixed $db, AppConfig $appConfig, Message $msg): SessionUser
    {
        if (!isset($_SESSION['logged'], $_SESSION['user']) || $_SESSION['logged'] !== true) :
            AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>");
        endif;

        $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
        $userArray = $sessionManager->getUserInfo();
        if ($userArray === false) :
            $msg->logMessage('[ERROR]', 'AJAX session user missing or invalid');
            AjaxResponse::json(['error' => 'User not found'], 500);
        endif;

        $userEmail = $_SESSION['useremail'] ?? '';
        return new SessionUser($userArray, $userEmail);
    }
endif;
