<?php 
/* Version:     1.4
    Date:       13/10/24
    Name:       deckimage.php
    Purpose:    PHP script to get and output raw jpg
    Notes:      -
        
    1.0         04/12/23
                Initial version
                
    1.1         14/01/24
                Move session.name to include
 * 
 *  1.2         20/01/24
 *              Move to logMessage
 * 
 *  1.3         06/10/24
 *              MTGC-131 - fix path comparison to work with URL parameters
 * 
 *  1.4         13/10/24
 *              MTGC-132 - Standardise Ajax page calling check code
 * 
 */

if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
require ('includes/ini.php');                //Initialise and load ini file
require ('includes/error_handling.php');
require ('includes/functions.php');          //Includes basic functions for non-secure pages
require ('includes/secpagesetup.php');       //Setup page variables
$msg = new Message($logfile);

$msg->logMessage('[DEBUG]',"Called to generate jpg...");

// Valid pages to call this (array)
$expectedReferringPages = [$myURL . '/deckdetail.php'];

// Standard check code
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$normalizedReferringPage = str_replace('www.', '', $referringPage);
$isValidReferrer = false;
foreach ($expectedReferringPages as $page):
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false):
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if ($isValidReferrer):
    // Access is OK
    $msg->logMessage('[DEBUG]',"Called from deckdetail.php");

    if(isset($_GET['deck']) AND ($_GET['deck']) !== ''):
        $decknumber = filter_input(INPUT_GET, 'deck', FILTER_SANITIZE_SPECIAL_CHARS);
        $imageFilePath = $ImgLocation.'deck_photos/'.$decknumber.'.jpg';    // Filesystem path
        
        // Check if the file exists
        if (file_exists($imageFilePath)):
            // Output the image file
            header('Content-Type: image/jpeg');
            readfile($imageFilePath);
        else:
            http_response_code(404); 
            echo 'Image not found';
        endif;
    else:
        trigger_error("[ERROR] deckimage.php: Called with no parameters", E_USER_ERROR);
    endif;

else:
    //Otherwise forbid access
    $msg->logMessage('[ERROR]',"Not called from deckdetail.php (referring page is $referringPage, expectedreferringpage is $expectedReferringPage, referringpagepath is $referringPagePath, expectedreferringpagepath is $expectedReferringPagePath)");
    http_response_code(403);
    echo 'Access forbidden';
endif;
?>
