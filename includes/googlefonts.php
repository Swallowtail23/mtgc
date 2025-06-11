<?php 
/* Version:     1.0
    Date:       17/10/16
    Name:       googlefonts.php
    Purpose:    PHP script to link to Google Roboto fonts
    Notes:      {none}
        
    1.0
                Initial version
 */

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
echo <<<HTML
<link 
    href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,300italic%7CRoboto:400,300,300italic,500' 
    rel='stylesheet' 
    type='text/css'>
<link 
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    rel="stylesheet" />
HTML;
