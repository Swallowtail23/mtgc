<?php

/*
Version:     1.0
Date:        27/03/26
Name:        SearchTextHelperTest.php
Purpose:     Regression tests for accent-insensitive quick-search display formatting.
Notes:       -
Author:      Codex
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\Text\SearchTextHelper;
use PHPUnit\Framework\TestCase;

class SearchTextHelperTest extends TestCase
{
    public function testHighlightAccentInsensitiveMatchHighlightsOriginalAccentedText()
    {
        $result = SearchTextHelper::highlightAccentInsensitiveMatch('Corazon de acero frio', 'frio');

        $this->assertSame('Corazon de acero <strong>frio</strong>', $result);
    }

    public function testHighlightAccentInsensitiveMatchHighlightsAccentedDisplayText()
    {
        $result = SearchTextHelper::highlightAccentInsensitiveMatch('Corazon de acero frío', 'frio');

        $this->assertSame('Corazon de acero <strong>frío</strong>', $result);
    }

    public function testFormatQuickSearchDisplayLabelAppendsFallbackLanguageSuffix()
    {
        $result = SearchTextHelper::formatQuickSearchDisplayLabel('Corazón de acero frío', 18, 3, 'es', true);

        $this->assertSame('Corazón de acero <strong>frí</strong>o (ES)', $result);
    }

    public function testFormatQuickSearchDisplayLabelSkipsLanguageSuffixWhenNotRequested()
    {
        $result = SearchTextHelper::formatQuickSearchDisplayLabel('Coldsteel Heart', 1, 4, 'es', false);

        $this->assertSame('<strong>Cold</strong>steel Heart', $result);
    }

    public function testHighlightByCharacterOffsetHighlightsMultibyteText()
    {
        $result = SearchTextHelper::highlightByCharacterOffset('西瓦巨龙', 1, 2);

        $this->assertSame('<strong>西瓦</strong>巨龙', $result);
    }
}
