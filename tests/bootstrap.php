<?php
// Basic bootstrap for tests
$GLOBALS['logfile'] = sys_get_temp_dir() . '/phpunit.log';
$GLOBALS['loglevelini'] = 0;

$db = new class {
    public function real_escape_string($str) { return $str; }
};
$GLOBALS['db'] = $db;

$bracketsInNames = [];
$importLinestoIgnore = [];

require __DIR__ . '/../classes/message.class.php';
require __DIR__ . '/../includes/functions.php';
