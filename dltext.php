<?php

/*
Version:     4.15
Date:        11/01/26
Name:        dltext.php
Purpose:     Text file export page.
Notes:       Call with Post 'text' and optionally 'filename'.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;

// Bootstrap
if (!defined('APP_ROOT')) :
    define('APP_ROOT', __DIR__);
endif;

$appContext = require APP_ROOT . '/bootstrap_secure.php';

// Content
if (isset($_POST['decknumber'])) :
    $deckNumber = filter_input(
        INPUT_POST,
        'decknumber',
        FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        FILTER_FLAG_NO_ENCODE_QUOTES
    );
    $deckNumber = htmlspecialchars_decode($deckNumber, ENT_QUOTES);
    $obj = new DeckManager(
        $db,
        $appConfig,
        $gameRules,
        $userEmail
    );
    $obj->exportDeck($deckNumber, "download");
elseif (isset($_POST['text'])) :
    $textdata = filter_input(
        INPUT_POST,
        'text',
        FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        FILTER_FLAG_NO_ENCODE_QUOTES
    );
    $textdata = htmlspecialchars_decode($textdata, ENT_QUOTES);

    // Check for filename in POST or use a default
    $filename = isset($_POST['filename'])
        ? mb_ereg_replace(
            "([^\\w\\s\\d\\-_~,;\\[\\]\\(\\).])",
            '',
            $_POST['filename']
        ) . '.txt'
        : 'dltext.txt';

    // Instantiate DeckManager and use the exportMissing function to handle the export
    $obj = new DeckManager(
        $db,
        $appConfig,
        $gameRules,
        $userEmail
    );
    $obj->exportMissing($textdata, $filename);
else :
    trigger_error('[ERROR] dltext.php: Error, no POST data', E_USER_WARNING);
endif;
