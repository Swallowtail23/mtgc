<?php

/*
Version:     1.1
Date:        11/01/26
Name:        bootstrap_secure.php
Purpose:     Secure bootstrap wrapper that runs secpagesetup.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;

$ctx = require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/secpagesetup.php';

// Don't enforce password change on page to change password!
if (basename($_SERVER['PHP_SELF']) !== 'profile.php') :
    SessionManager::forcePasswordChange($appConfig);
endif;

return $ctx;
