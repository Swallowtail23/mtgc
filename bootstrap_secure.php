<?php

/*
Version:     1.12
Date:        11/01/26
Name:        bootstrap_secure.php
Purpose:     Secure bootstrap wrapper that runs secpagesetup.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;

if (!defined('APP_ROOT')) :
    define('APP_ROOT', __DIR__);
endif;

$ctx = require APP_ROOT . '/bootstrap.php';
require APP_ROOT . '/includes/secpagesetup.php';

// Don't enforce password change on page to change password!
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') :
    SessionManager::forcePasswordChange($appConfig);
endif;

return $ctx;
