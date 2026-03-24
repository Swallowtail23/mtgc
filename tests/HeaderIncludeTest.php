<?php

/*
Version:     1.0
Date:        24/03/26
Name:        HeaderIncludeTest.php
Purpose:     Regression tests for header include compatibility across entrypoints.
Notes:       -
Author:      Codex
Copyright:   2025 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class HeaderIncludeTest extends TestCase
{
    public function testHeaderRendersWithoutContextObject()
    {
        $_SERVER['PHP_SELF'] = '/error.php';
        $_SESSION = [];

        $siteTitle = 'Test Site';
        $tier = 'dev';
        $validsearch = '';
        $qtyresults = 0;
        $perpage = 100;

        ob_start();
        include APP_ROOT . '/includes/header.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('window.mtgAjaxConfig', $output);
        $this->assertStringContainsString('id="title"', $output);
        $this->assertStringContainsString("id='headerresults'", $output);
    }
}
