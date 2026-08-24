<?php

/*
Version:     1.0
Date:        25/08/26
Name:        CardNavigationTest.php
Purpose:     Regression tests for card detail previous/next navigation queries.
Notes:       -
Author:      Codex
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\CardNavigation;
use PHPUnit\Framework\TestCase;

class CardNavigationTest extends TestCase
{
    public function testPrimaryCardNavigationFollowsPrimarySequenceAcrossLanguages()
    {
        $spec = CardNavigation::buildQuerySpec('hoc', 'en', true);

        $this->assertSame(['hoc'], $spec['params']);
        $this->assertStringContainsString('AND primary_card = 1', $spec['query']);
        $this->assertStringNotContainsString('AND lang = ?', $spec['query']);
    }

    public function testNonPrimaryCardNavigationFollowsLanguageAcrossPrimaryStatuses()
    {
        $spec = CardNavigation::buildQuerySpec('hoc', 'ja', false);

        $this->assertSame(['hoc', 'ja'], $spec['params']);
        $this->assertStringContainsString('AND lang = ?', $spec['query']);
        $this->assertStringNotContainsString('AND primary_card = 1', $spec['query']);
        $this->assertMatchesRegularExpression('/ORDER BY\s+primary_card DESC,\s+number ASC/', $spec['query']);
    }

    public function testTheListNavigationUsesTheListAutomaticOrder()
    {
        $spec = CardNavigation::buildQuerySpec('plst', 'en', true);

        $this->assertMatchesRegularExpression('/SUBSTRING\(\s*cards_scry\.number_import,/', $spec['query']);
        $this->assertStringContainsString(") DESC,\n                    SUBSTRING(number_import", $spec['query']);
    }

    public function testSecretLairNavigationUsesReleaseDateOrder()
    {
        $spec = CardNavigation::buildQuerySpec('sld', 'en', true);

        $this->assertMatchesRegularExpression('/ORDER BY\s+release_date DESC,/', $spec['query']);
    }
}
