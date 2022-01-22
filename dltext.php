<?php
/* Version:     1.0
    Date:       19/10/16
    Name:       dltext.php
    Purpose:    Text file export page 
    Notes:      Call with Post 'text' and optionally 'filename'.
    
    1.0
                Initial version
*/
session_start();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions_new.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password

if(isset($_POST['text'])):
    $textdata = filter_input(INPUT_POST, 'text', FILTER_SANITIZE_STRING);
    $textdata = htmlspecialchars_decode($textdata,ENT_QUOTES);
else:
    trigger_error('[ERROR] dltext.php: Error, no POST data');
endif;
if(isset($_POST['filename'])):
    $filename = filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_STRING).'.txt';
else:
    $filename = 'dltext.txt';
endif;
$tmpName = tempnam(sys_get_temp_dir(), 'data');
$file = fopen($tmpName, 'w');

fwrite($file, $textdata);
fclose($file);

header('Content-Description: File Transfer');
header('Content-Type: text/txt');
header("Content-Disposition: attachment; filename=$filename");
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($tmpName));

ob_clean();
flush();
readfile($tmpName);

unlink($tmpName);