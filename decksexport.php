<?php

/*
Version:     1.0
Date:        15/01/26
Name:        decksexport.php
Purpose:     Export selected decks from decks list.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
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

$submittedToken = $_POST['csrf_token'] ?? '';
if (!SessionManager::validateCsrfToken($submittedToken)) :
    $msg->logMessage('[ERROR]', 'Deck export rejected: CSRF failure');
    header('Location: decks.php');
    exit;
endif;

$decktoexportRaw = $_POST['decktoexport'] ?? [];
if (!is_array($decktoexportRaw)) :
    $decktoexportRaw = [$decktoexportRaw];
endif;

$deckNumbers = [];
foreach ($decktoexportRaw as $deckToExportRaw) :
    $deckToExport = (int) filter_var($deckToExportRaw, FILTER_SANITIZE_NUMBER_INT);
    if ($deckToExport <= 0) :
        $msg->logMessage('[DEBUG]', "Deck export skipped invalid id: '$deckToExportRaw'");
        continue;
    endif;
    $deckNumbers[] = $deckToExport;
endforeach;
$deckNumbers = array_values(array_unique($deckNumbers));

if (empty($deckNumbers)) :
    $msg->logMessage('[NOTICE]', 'Deck export requested with no selected decks');
    header('Location: decks.php');
    exit;
endif;

$deckManager = new DeckManager(
    $db,
    $appConfig,
    $gameRules,
    $userEmail
);

$exportDecks = [];
foreach ($deckNumbers as $deckNumber) :
    if ($deckManager->assertDeckOwner($deckNumber, $user, 'decksexport.php') === false) :
        $msg->logMessage('[ERROR]', "Deck export ownership failed for deck $deckNumber");
        continue;
    endif;
    $exportDecks[] = $deckNumber;
endforeach;

if (empty($exportDecks)) :
    $msg->logMessage('[NOTICE]', 'Deck export had no valid owned decks to process');
    header('Location: decks.php');
    exit;
endif;

if (count($exportDecks) === 1) :
    $deckNumber = $exportDecks[0];
    $msg->logMessage('[NOTICE]', "Deck export single download for deck $deckNumber");
    $deckManager->exportDeck($deckNumber, "download");
    exit;
endif;

$msg->logMessage('[NOTICE]', 'Deck export bulk download requested for ' . count($exportDecks) . ' decks');
$zipFilePath = '';
foreach ($exportDecks as $index => $deckNumber) :
    if ($index === 0) :
        $zipFilePath = $deckManager->exportDeck($deckNumber, "bulk");
    else :
        $zipFilePath = $deckManager->exportDeck($deckNumber, "bulk", $zipFilePath);
    endif;
    if ($zipFilePath === false) :
        $msg->logMessage('[ERROR]', "Deck export bulk failed for deck $deckNumber");
        $zipFilePath = '';
        break;
    endif;
endforeach;

if ($zipFilePath === '' || !file_exists($zipFilePath)) :
    $msg->logMessage('[ERROR]', 'Deck export bulk failed to generate zip file');
    header('Location: decks.php');
    exit;
endif;

$filename = basename($zipFilePath);
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($zipFilePath));

ob_clean();
flush();
readfile($zipFilePath);

unlink($zipFilePath);
exit;
