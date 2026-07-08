<?php

/*
Version:     1.3
Date:        08/07/26
Name:        CardDetailTagDisplayTest.php
Purpose:     Guards card-detail Scryfall tag display wiring.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CardDetailTagDisplayTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../carddetail.php');
    }

    public function testCardQuerySelectsTagConnectionFields(): void
    {
        $this->assertStringContainsString('oracle_id,', $this->source);
        $this->assertStringContainsString('illustration_id,', $this->source);
        $this->assertStringContainsString('f1_illustration_id,', $this->source);
        $this->assertStringContainsString('f2_illustration_id,', $this->source);
    }

    public function testCardDetailUsesParameterizedTagLookups(): void
    {
        $this->assertStringContainsString('FROM scryfall_tag_assignments sta', $this->source);
        $this->assertStringContainsString("sta.tag_type = 'oracle'", $this->source);
        $this->assertStringContainsString("sta.tag_type = 'art'", $this->source);
        $this->assertStringContainsString('sta.subject_id = ?', $this->source);
        $this->assertStringContainsString('sta.subject_id IN ($imageTagPlaceholders)', $this->source);
        $this->assertStringContainsString('[$row[\'oracle_id\']]', $this->source);
        $this->assertStringContainsString('$illustrationIds', $this->source);
    }

    public function testTagRowsRenderConditionallyAndEscapeLabels(): void
    {
        $this->assertStringContainsString('if ($oracleTags !== [] || $imageTags !== [])', $this->source);
        $this->assertStringContainsString("<details class='carddetail-tags'><summary><b>Tags</b></summary>", $this->source);
        $this->assertStringContainsString('if ($oracleTags !== [])', $this->source);
        $this->assertStringContainsString('if ($imageTags !== [])', $this->source);
        $this->assertStringContainsString('<b>Oracle tags: </b>', $this->source);
        $this->assertStringContainsString('<b>Image tags: </b>', $this->source);
        $this->assertStringContainsString('htmlspecialchars(', $this->source);
        $this->assertStringContainsString('rawurlencode($tag)', $this->source);
        $this->assertStringContainsString("class='carddetail-tag-link'", $this->source);
        $this->assertStringContainsString('&amp;searchoracletag=yes', $this->source);
        $this->assertStringContainsString('&amp;searchimagetag=yes', $this->source);
        $this->assertStringContainsString('implode(\'; \', $oracleTagLinks)', $this->source);
        $this->assertStringContainsString('implode(\'; \', $imageTagLinks)', $this->source);
        $this->assertStringContainsString('</details>', $this->source);
    }

    public function testCardDetailTagsHaveExpandableStyling(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../css/style.css');

        $this->assertStringContainsString('.carddetail-tags', $source);
        $this->assertStringContainsString('.carddetail-tags summary', $source);
        $this->assertStringContainsString('.carddetail-tag-link', $source);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $source);
        $this->assertStringContainsString('cursor: pointer;', $source);
        $this->assertStringContainsString('display: list-item;', $source);
        $this->assertStringContainsString('list-style-position: inside;', $source);
    }
}
