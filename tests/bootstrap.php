<?php
// Basic bootstrap for tests
$logfile = sys_get_temp_dir() . '/phpunit.log';
$loglevelini = 0;
$db = new class {
    public function real_escape_string($str) { return $str; }
};
$bracketsInNames = [];
$importLinestoIgnore = [];
require __DIR__ . '/../classes/message.class.php';
require __DIR__ . '/../includes/functions.php';
