<?php

/*
Version:     1.2
Date:        26/11/25
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
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$fontParameters = "css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200";
?>

<link
    href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,300italic%7CRoboto:400,300,300italic,500'
    rel='stylesheet'
    type='text/css'>
<link
    href="https://fonts.googleapis.com/<?php echo $fontParameters; ?>"
    rel="stylesheet" />
