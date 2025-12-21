<?php

/*
Version:     1.6
Date:        21/12/25
Name:        deckimage.php
Purpose:     PHP script to get and output raw jpg.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 04/12/23 Initial version
    1.1 14/01/24 Move session.name to include
    1.2 20/01/24 Move to logMessage
    1.3 06/10/24 MTGC-131 - fix path comparison to work with URL parameters
    1.4 13/10/24 MTGC-132 - standardise Ajax page calling check code
    1.5 25/11/25 Standard tidy-up
    1.6 21/12/25 Replace E_USER_ERROR trigger_error with exceptions for PHP 8.4 compatibility
*/

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;
startCustomSession();
require 'includes/ini.php'; // Initialise and load ini file
require 'includes/error_handling.php';
require 'includes/functions.php'; // Includes basic functions for non-secure pages
require 'includes/secpagesetup.php'; // Setup page variables
$msg = new Message($logfile);

$msg->logMessage('[DEBUG]', "Called to generate jpg...");

// Valid pages to call this (array)
$expectedReferringPages = [$myURL . '/deckdetail.php'];

// Standard check code
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$normalizedReferringPage = str_replace('www.', '', $referringPage);
$isValidReferrer = false;
foreach ($expectedReferringPages as $page) :
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false) :
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if ($isValidReferrer) :
    // Access is OK
    $msg->logMessage('[DEBUG]', "Called from deckdetail.php");

    if (isset($_GET['deck']) && ($_GET['deck']) !== '') :
        $deckNumber = filter_input(INPUT_GET, 'deck', FILTER_SANITIZE_SPECIAL_CHARS);
        $imageFilePath = $imgLocation . 'deck_photos/' . $deckNumber . '.jpg'; // Filesystem path

        // Check if the file exists
        if (file_exists($imageFilePath)) :
            // Output the image file
            header('Content-Type: image/jpeg');
            readfile($imageFilePath);
        else :
            http_response_code(404);
            echo 'Image not found';
        endif;
    else :
        throw new Exception("[ERROR] deckimage.php: Called with no parameters");
    endif;
else :
    // Otherwise forbid access
    $expectedList = implode(', ', $expectedReferringPages);
    $msg->logMessage(
        '[ERROR]',
        "Not called from deckdetail.php (referrer: $referringPage, expected: $expectedList)"
    );
    http_response_code(403);
    echo 'Access forbidden';
endif;
