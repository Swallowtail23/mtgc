<?php

/*
Version:     1.0
Date:        06/05/26
Name:        CardDetailGroupDeckEscapeTest.php
Purpose:     Tests card detail group deck output escaping.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CardDetailGroupDeckEscapeTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../carddetail.php');
    }

    public function testGroupDeckNamesAreEscapedBeforeRendering(): void
    {
        $this->assertStringContainsString('$groupDeckNameEsc = htmlspecialchars(', $this->source);
        $this->assertStringContainsString("(string) \$decksgrprow['deckname']", $this->source);
        $this->assertStringContainsString('ENT_QUOTES', $this->source);
        $this->assertStringContainsString("'UTF-8'", $this->source);
        $this->assertStringContainsString('$grpusernameEsc = htmlspecialchars($grpusername', $this->source);
    }

    public function testGroupDeckRenderUsesEscapedVariables(): void
    {
        $block = $this->groupDeckRenderBlock();

        $this->assertStringContainsString('$grpusernameEsc: $groupDeckNameEsc', $block);
        $this->assertStringContainsString('(main x$groupMainQtyEsc)', $block);
        $this->assertStringContainsString('(sideboard x$groupSideQtyEsc)', $block);
        $this->assertStringNotContainsString('{$decksgrprow[\'deckname\']}', $block);
        $this->assertStringNotContainsString('$grpusername:', $block);
    }

    public function testOwnedDeckRenderUsesEscapedVariables(): void
    {
        $this->assertStringContainsString('$deckNameEsc = htmlspecialchars(', $this->source);
        $this->assertStringContainsString('$deckNumberEsc = htmlspecialchars(', $this->source);
        $this->assertStringContainsString('/deckdetail.php?deck=$deckNumberEsc', $this->source);
        $this->assertStringContainsString('$deckNameEsc</a>', $this->source);
        $this->assertStringNotContainsString('{$decksrow[\'deckname\']}</a>', $this->source);
    }

    private function groupDeckRenderBlock(): string
    {
        $start = strpos($this->source, "foreach (\$ingrpdecks as \$decksgrprow) :");
        $end = strpos($this->source, 'endforeach;', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($this->source, $start, $end - $start);
    }
}
