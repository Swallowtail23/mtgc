<?php

/*
Version:     1.0
Date:        27/03/26
Name:        QuickSearchServiceTest.php
Purpose:     Regression tests for quick-search SQL build behavior.
Notes:       -
Author:      Codex
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\Text\QuickSearchService;
use PHPUnit\Framework\TestCase;

class QuickSearchServiceTest extends TestCase
{
    public function testBuildSearchSpecCreatesMatchingPlaceholderCount()
    {
        $spec = QuickSearchService::buildSearchSpec('bal', '%bal%', '', '', true);

        $this->assertSame(39, substr_count($spec['query'], '?'));
        $this->assertCount(39, $spec['params']);
    }

    public function testBuildSearchSpecAddsPrimaryCardFilterWhenRequested()
    {
        $spec = QuickSearchService::buildSearchSpec('bal', '%bal%', '', '', true);

        $this->assertStringContainsString('AND (primary_card = 1)', $spec['query']);
    }

    public function testBuildSearchSpecOmitsPrimaryCardFilterForFallback()
    {
        $spec = QuickSearchService::buildSearchSpec('bal', '%bal%', '', '', false);

        $this->assertStringNotContainsString('AND (primary_card = 1)', $spec['query']);
    }
}
