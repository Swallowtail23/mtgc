<?php

/*
Version:     1.0
Date:        06/05/26
Name:        CriteriaSetFilterSecurityTest.php
Purpose:     Tests advanced-search set filter SQL parameterization.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CriteriaSetFilterSecurityTest extends TestCase
{
    private array $originalGet = [];
    private string $originalPhpSelf = '';

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalPhpSelf = (string) ($_SERVER['PHP_SELF'] ?? '');
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SERVER['PHP_SELF'] = $this->originalPhpSelf;
    }

    public function testAdvancedSearchSetFilterUsesQueryParameter(): void
    {
        $_GET = ['complex' => 'yes'];
        $_SERVER['PHP_SELF'] = '/index.php';

        $adv = 'yes';
        $sortBy = 'auto';
        $selectedSets = ["x' OR '1'='1"];
        $selectAll = 'SELECT * FROM cards_scry WHERE ';
        $sorting = '';
        $mytable = 'collection_test';

        require APP_ROOT . '/includes/criteria.php';

        $this->assertSame('true', $validsearch);
        $this->assertStringContainsString('cards_scry.setcode LIKE ?', $criteria);
        $this->assertStringNotContainsString("x' OR '1'='1", $criteria);
        $this->assertSame(["x' OR '1'='1"], $params);
        $this->assertStringContainsString('cards_scry.setcode LIKE ?', $query);
        $this->assertStringNotContainsString("x' OR '1'='1", $query);
    }
}
