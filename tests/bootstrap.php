<?php

// Basic bootstrap for tests
$GLOBALS['logfile'] = sys_get_temp_dir() . '/phpunit.log';
$GLOBALS['loglevelini'] = 0;

$db = new class {
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function real_escape_string($str)
    {
        return $str;
    }
};
$GLOBALS['db'] = $db;

$bracketsInNames = [];
$importLinestoIgnore = [];

require_once __DIR__ . '/../classes/message.class.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/colour.php';
