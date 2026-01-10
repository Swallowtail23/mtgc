<?php

use PHPUnit\Framework\TestCase;
use MTG\Cards\CardUtils;

class SymbolReplaceTest extends TestCase
{
    public function testReplacesBasicManaSymbols()
    {
        $input = 'Cost: {W}{U}{B}{R}{G}{C}';
        $expected = 'Cost: '
            . '<img src="images/w.png" alt="{W}" class="manaimg">'
            . '<img src="images/u.png" alt="{U}" class="manaimg">'
            . '<img src="images/b.png" alt="{B}" class="manaimg">'
            . '<img src="images/r.png" alt="{R}" class="manaimg">'
            . '<img src="images/g.png" alt="{G}" class="manaimg">'
            . '<img src="images/colourless_mana.png" alt="{C}" class="manaimg">';

        $this->assertSame($expected, CardUtils::symbolReplace($input));
    }

    public function testReplacesHybridAndPhyrexianSymbols()
    {
        $input = '{W/U}{G/P}{CHAOS}{PWk}';
        $expected = '<img src="images/wu.png" alt="{WU}" class="manaimg">'
            . '<img src="images/pg.png" alt="{G/P}" class="manaimg">'
            . '<img src="images/chaos.png" alt="{PG}" class="manaimg">'
            . 'Planeswalk';

        $this->assertSame($expected, CardUtils::symbolReplace($input));
    }

    public function testReplacesNumbersNewlinesAndHalfSymbol()
    {
        $input = "Line 1\n{10}{1/2}?";
        $expected = 'Line 1<br>'
            . '<img src="images/10.png" alt="{10}" class="manaimg">'
            . '<img src="images/half.png" alt="{1/2}" class="manaimg">'
            . '-';

        $this->assertSame($expected, CardUtils::symbolReplace($input));
    }

    public function testPoundAndHashAreHandled()
    {
        $input = 'Line£With#Hash';
        $expected = 'Line<br>WithHash';

        $this->assertSame($expected, CardUtils::symbolReplace($input));
    }
}
