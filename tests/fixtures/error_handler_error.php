<?php

/*
Version:     1.0
Date:        28/04/26
Name:        error_handler_error.php
Purpose:     Fixture script for error handler notice tests.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

require __DIR__ . '/../../vendor/autoload.php';

use MTG\Core\AppConfig;
use MTG\Core\ErrorHandler;

$logPath = getenv('ERROR_LOG_PATH');
if ($logPath === false || $logPath === '') :
    $logPath = sys_get_temp_dir() . '/mtg_error.log';
endif;

$ini = [
    'general' => [
        'Logfile' => $logPath,
        'Loglevel' => 3
    ],
    'security' => [],
    'email' => [
        'Email' => 'disabled',
        'AdminEmail' => '',
        'ServerEmail' => ''
    ],
    'fx' => [],
    'comments' => []
];

$appConfig = AppConfig::fromIni($ini);
$handler = new ErrorHandler($appConfig);
error_reporting(E_ALL);
$handler->handleError(E_USER_NOTICE, 'Test notice', 'file.php', 12);
