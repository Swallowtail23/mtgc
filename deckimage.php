<?php

/*
Version:     1.13
Date:        10/01/26
Name:        deckimage.php
Purpose:     PHP script to get and output raw jpg.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;
use MTG\Core\Message;

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
$msg = new Message($appConfig);

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
        $deckManager = new DeckManager(
            $db,
            $appConfig,
            $userEmail,
            $importLinestoIgnore,
            $nonPreferredSetCodes,
            $any_quantity,
        );
        $ownerCheck = $deckManager->assertDeckOwner($deckNumber, $user, 'deckimage.php');
        if ($ownerCheck === false) :
            $msg->logMessage('[ERROR]', "deckimage.php: deck ownership check failed for deck $deckNumber");
            http_response_code(403);
            echo 'Access forbidden';
            exit();
        endif;
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
