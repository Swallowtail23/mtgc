<?php

/*
Version:     1.0
Date:        28/04/26
Name:        error_handler_exception.php
Purpose:     Fixture script for error handler exception tests.
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
    $logPath = sys_get_temp_dir() . '/mtg_exception.log';
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
$handler->handleException(new Exception('Test exception'));
