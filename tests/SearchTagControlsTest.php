<?php

/*
Version:     1.2
Date:        08/07/26
Name:        SearchTagControlsTest.php
Purpose:     Tests advanced-search Scryfall tag control wiring.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class SearchTagControlsTest extends TestCase
{
    public function testSearchFormIncludesOracleAndImageTagControls(): void
    {
        $source = file_get_contents(APP_ROOT . '/includes/search.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('<h4 class="h4" style="margin-top:30px;">Tags</h4>', $source);
        $this->assertStringContainsString('name="searchoracletag"', $source);
        $this->assertStringContainsString('name="searchimagetag"', $source);
        $this->assertStringContainsString('class="tagcheckbox checkbox"', $source);
        $this->assertStringContainsString('tagdisabledsearchcheckbox', $source);
    }

    public function testTagControlJavascriptIsMutuallyExclusiveAndOneWayAgainstMainSearch(): void
    {
        $source = file_get_contents(APP_ROOT . '/index.php');

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "setupMutualExclusion('#searchoracletag', '#searchimagetag');",
            $source
        );
        $this->assertStringContainsString(
            "setupMutualExclusion('#searchimagetag', '#searchoracletag');",
            $source
        );
        $this->assertStringContainsString("$('.tagcheckbox').click(function ()", $source);
        $this->assertStringContainsString("$('.mainsearchcheckbox').prop('checked', false)", $source);
        $this->assertStringContainsString(
            "$('.mainsearchcheckbox').not('#cb1, #searchnew').click(function ()",
            $source
        );
        $this->assertStringContainsString("$('.tagcheckbox').prop('checked', false)", $source);
        $this->assertStringContainsString('function updateTagSearchState()', $source);
        $this->assertStringContainsString("$('.tagdisabledsearchcheckbox')", $source);
        $this->assertStringContainsString(".prop('disabled', true)", $source);
        $this->assertStringContainsString(".prop('disabled', false)", $source);
        $this->assertStringContainsString("$('.tagcheckbox').change(updateTagSearchState);", $source);
        $this->assertStringNotContainsString("setupMutualExclusion('#cb1', '.tagcheckbox');", $source);
    }

    public function testTagDisabledSearchControlsUseDisabledLabelStyling(): void
    {
        $source = file_get_contents(APP_ROOT . '/css/style.css');

        $this->assertIsString($source);
        $this->assertStringContainsString('.tagdisabledsearchcheckbox:disabled + label', $source);
    }
}
