<?php

/*
Version:     4.19
Date:        06/05/26
Name:        dltext.php
Purpose:     Text file export page.
Notes:       Call with Post 'text' and optionally 'filename'.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;

// Bootstrap

$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$sessionUser                = $ctx->sessionUser();

$user                       = $sessionUser->id();
$userEmail                  = $sessionUser->email();

// Content
if (isset($_POST['decknumber'])) :
    $deckNumber = filter_input(
        INPUT_POST,
        'decknumber',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $obj = new DeckManager(
        $db,
        $appConfig,
        $gameRules,
        $userEmail
    );
    if ($deckNumber === false || $deckNumber === null) :
        $msg->logMessage('[ERROR]', 'dltext.php: invalid deck export id');
        header('Location: decks.php');
        exit;
    endif;
    if ($obj->assertDeckOwner($deckNumber, $user, 'dltext.php') === false) :
        $msg->logMessage('[ERROR]', "dltext.php: deck export ownership failed for deck $deckNumber");
        http_response_code(403);
        header('Location: decks.php');
        exit;
    endif;
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
