<?php

/*
Version:     4.5
Date:        26/11/25
Name:        colour.php
Purpose:     Return colour name for a colour code.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CardUtils;
use MTG\Core\Message;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

function colourFunction($colourcode)
{
    global $logfile;
    $msg = new Message($logfile);
    return CardUtils::colourFunction($colourcode, $msg);
}
