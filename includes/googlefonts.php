<?php 
/* Version:     1.1
    Date:       11/06/25
    Name:       googlefonts.php
    Purpose:    PHP script to link to Google Roboto fonts
    Notes:      {none}
        
    1.0
                Initial version
    1.1         11/06/25
                Removed html output after ?> (using echo)
 */

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

echo "<link href='https://fonts.googleapis.com/css?family=Roboto+Condensed:300,300italic%7CRoboto:400,300,300italic,500' rel='stylesheet' type='text/css'>\n";
echo "<link href='https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200' rel='stylesheet' />\n";
