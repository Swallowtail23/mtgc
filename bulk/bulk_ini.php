<?php

/*
Version:     3.19
Date:        13/01/26
Name:        bulk_ini.php
Purpose:     Ini settings for bulk files
Notes:       Wrapper for shared bootstrap
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

// Bootstrap (shared app init)

$ctx = require dirname(__DIR__) . '/bootstrap.php';

return $ctx;
