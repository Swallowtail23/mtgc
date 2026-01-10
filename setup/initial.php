<?php

/*
Version:     2.6
Date:        25/11/25
Name:        initial.php
Purpose:     Generate a usable password without site access.
Notes:       #### MUST NOT BE SERVED PUBLICLY BY Apache ####
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0 18/03/23 Migrate to password_hash
    2.1 25/11/25 Standard tidy-up
*/

use MTG\Core\INI;

require_once __DIR__ . '/../src/MTG/Core/INI.php';
$ini = new INI('/opt/mtg/mtg_new.ini');
$iniArray = $ini->data;

if (!isset($argv[0]) || !isset($argv[1]) || !isset($argv[2]) || isset($argv[3])) :
    echo "Incorrect number of arguments (Should be 2: username and password), quitting";
    die;
endif;

$argument_loop = 1;
foreach ($argv as $value) :
    if ($argument_loop === 1) :
        // filename; do nothing
    elseif ($argument_loop === 2) :
        $userName = $value;
    elseif ($argument_loop === 3) :
        $password = $value;
    else :
        echo "Incorrect number of arguments (Should just be username and password), quitting";
        die;
    endif;
    $argument_loop = $argument_loop + 1;
endforeach;

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Username: $userName\n";
echo "Hashed password: $hashed_password\n";
