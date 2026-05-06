<?php

/*
Version:     1.22
Date:        06/05/26
Name:        valueupdate.php
Purpose:     PHP script to update topvalue across collection.
Notes:       Currently called after import function is run.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\PriceManager;
use MTG\Core\Validation;

// Bootstrap

$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$sessionUser                = $ctx->sessionUser();

$userEmail                  = $sessionUser->email();
$mytable                    = $sessionUser->table();

// Content
$msg->logMessage('[DEBUG]', 'Loading valueupdate.php...');

if (Validation::validTableName($mytable, $appConfig) === false) :
    throw new Exception('[ERROR] valueupdate.php: Invalid session table format');
endif;

if (isset($_GET['table'])) :
    $requestedTable = filter_input(INPUT_GET, 'table', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($requestedTable !== $mytable) :
        $msg->logMessage(
            '[ERROR]',
            "valueupdate.php: rejected table update request for '$requestedTable' by $userEmail"
        );
        http_response_code(403);
        throw new Exception('[ERROR] valueupdate.php: Collection table access denied');
    endif;
endif;

if ($mytable !== '') :
    $obj = new PriceManager($db, $appConfig, $userEmail);
    $obj->updateCollectionValues($mytable);
else :
    throw new Exception('[ERROR] valueupdate.php: Missing session table');
endif;
