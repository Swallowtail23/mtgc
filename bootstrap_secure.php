<?php

/*
Version:     1.14
Date:        12/01/26
Name:        bootstrap_secure.php
Purpose:     Secure bootstrap wrapper that runs session setup.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Auth\SessionUser;

if (!defined('APP_ROOT')) :
    define('APP_ROOT', __DIR__);
endif;

$ctx = require APP_ROOT . '/bootstrap.php';
$appConfig = $ctx->config();
$db = $ctx->db();

require APP_ROOT . '/includes/secpagesetup.php';
$secureData = mtgSecurePageSetup($db, $appConfig);
// TODO: Legacy session globals are a temporary compatibility layer. Prefer $ctx->sessionUser() and local assignments.
$sessionUser = $secureData['sessionUser'] ?? null;
if ($sessionUser instanceof SessionUser) :
    $ctx = $ctx->withSessionUser($sessionUser);
endif;

$legacy = $secureData['legacy'] ?? [];
$user = (int) ($legacy['user'] ?? 0);
$userName = (string) ($legacy['userName'] ?? '');
$mytable = (string) ($legacy['mytable'] ?? '');
$collection_view = (string) ($legacy['collection_view'] ?? '');
$admin = (int) ($legacy['admin'] ?? 0);
$grpinout = (int) ($legacy['grpinout'] ?? 0);
$groupid = (int) ($legacy['groupid'] ?? 0);
$fx = (bool) ($legacy['fx'] ?? false);
$targetCurrency = (string) ($legacy['targetCurrency'] ?? '');
$rate = is_numeric($legacy['rate'] ?? null) ? (float) $legacy['rate'] : 0.0;
$userEmail = (string) ($legacy['userEmail'] ?? '');

unset($secureData, $legacy, $sessionUser);

// Don't enforce password change on page to change password!
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($script !== 'profile.php') :
    SessionManager::forcePasswordChange($ctx->config());
endif;

return $ctx;
