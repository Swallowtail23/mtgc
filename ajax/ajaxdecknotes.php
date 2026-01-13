<?php

/*
Version:     1.27
Date:        13/01/26
Name:        ajaxdecknotes.php
Purpose:     PHP script to save deck notes
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();

$myURL                      = (string) $appConfig->general('url', '');

// Content
$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxdecknotes.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::text('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    // AJAX session context
    require_once APP_ROOT . '/ajax/ajax_session.php';
    $sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
    $ctx                        = $ctx->withSessionUser($sessionUser);
    $user                       = $ctx->sessionUser()->id();
    $mytable                    = $ctx->sessionUser()->table();
    $userEmail                  = $ctx->sessionUser()->email();
    $newnotes = isset($_POST['newnotes']) ? trim($_POST['newnotes']) : '';
    $newsidenotes = isset($_POST['newsidenotes']) ? trim($_POST['newsidenotes']) : '';
    $deckNumber = isset($_POST['decknumber']) ? intval($_POST['decknumber']) : 0;

    $msg->logMessage(
        '[NOTICE]',
        "Called with: Notes: $newnotes, Side notes: $newsidenotes, Deck number: $deckNumber"
    );

    $deckManager = new DeckManager(
        $db,
        $appConfig,
        $gameRules,
        $userEmail
    );
    if ($deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdecknotes.php') === false) :
        AjaxResponse::json(['error' => 'Access forbidden'], 403);
    endif;

    try {
        $query = "UPDATE decks SET notes = ?, sidenotes = ? WHERE decknumber = ?";
        $result = $db->execute_query($query, [$newnotes, $newsidenotes, $deckNumber]);

        if ($result) {
            AjaxResponse::json(['success' => true]);
        } else {
            AjaxResponse::json(['error' => 'No rows updated or SQL error occurred'], 400);
        }
    } catch (Exception $e) {
        throw new Exception(
            "[ERROR] ajaxdecknotes.php: " . $e->getMessage() . " SQLSTATE: " . $db->error
        );
        AjaxResponse::json(['error' => 'Database error'], 400);
    }
endif;
