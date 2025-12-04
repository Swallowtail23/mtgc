<?php

/*
Version:     1.3
Date:        04/12/25
Name:        googlefonts.php
Purpose:     PHP script to link to Google Roboto fonts
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    1.1 26/11/25 Standard tidy-up
    1.2 26/11/25 Align header with standard format
    1.3 04/12/25 Restrict Material Symbols to used icons
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$msoIcons = [
    'book_2',
    'book_5',
    'close',
    'help',
    'logout',
    'menu',
    'navigate_before',
    'navigate_next',
    'refresh',
    'save',
    'search',
    'skip_next',
    'skip_previous'
];
$msoIconParam = urlencode(implode(',', $msoIcons));
$msoFontParameters = "css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    . "&icon_names={$msoIconParam}&display=block";
$rcFontParameters  = "css?family=Roboto+Condensed:300,300italic%7CRoboto:400,300,300italic,500";

echo "<link href='https://fonts.googleapis.com/{$rcFontParameters}' rel='stylesheet' type='text/css'>\n";
echo "<link href='https://fonts.googleapis.com/{$msoFontParameters}' rel='stylesheet'>\n";
