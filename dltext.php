<?php
/* Version:     3.0
    Date:       08/09/24
    Name:       dltext.php
    Purpose:    Text file export page 
    Notes:      Call with Post 'text' and optionally 'filename'.
    
    1.0
                Initial version
 *  2.0
 *              PHP 8.1 compatibility
 * 
 *  3.0         08/09/24
 *              MTGC-125 - move export logic to deckManager class
*/

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');               //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');     //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');      //Setup page variables
forcechgpwd();                              //Check if user is disabled or needs to change password

if(isset($_POST['decknumber'])):
    $decknumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    $decknumber = htmlspecialchars_decode($decknumber,ENT_QUOTES);
    $obj = new DeckManager($db, $logfile, $useremail, $serveremail, $importLinestoIgnore);
    $obj->exportDeck($decknumber,"download");
elseif(isset($_POST['text'])):
    $textdata = filter_input(INPUT_POST, 'text', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
    $textdata = htmlspecialchars_decode($textdata,ENT_QUOTES);
    if(isset($_POST['filename'])):
        $filename = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $_POST['filename']).'.txt';
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
else:
    trigger_error('[ERROR] dltext.php: Error, no POST data');
endif;
