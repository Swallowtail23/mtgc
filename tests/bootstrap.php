<?php

// Basic bootstrap for tests
$GLOBALS['logfile'] = sys_get_temp_dir() . '/phpunit.log';
$GLOBALS['loglevelini'] = 0;
$GLOBALS['logLevelIni'] = 0;

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

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) :
    require_once $autoload;
endif;
require_once __DIR__ . '/../includes/functions.php';
