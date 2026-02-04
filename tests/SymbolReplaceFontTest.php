<?php

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class SymbolReplaceFontTest extends TestCase
{
    public function testReplacesBasicManaSymbols()
    {
        $input = 'Cost: {W}{U}{B}{R}{G}{C}';
        $expected = 'Cost: '
            . '<i class="ms ms-w ms-cost" aria-label="{W}" role="img"></i>'
            . '<i class="ms ms-u ms-cost" aria-label="{U}" role="img"></i>'
            . '<i class="ms ms-b ms-cost" aria-label="{B}" role="img"></i>'
            . '<i class="ms ms-r ms-cost" aria-label="{R}" role="img"></i>'
            . '<i class="ms ms-g ms-cost" aria-label="{G}" role="img"></i>'
            . '<i class="ms ms-c ms-cost" aria-label="{C}" role="img"></i>';

        $this->assertSame($expected, CardUtils::symbolReplaceFont($input));
    }

    public function testReplacesHybridAndPhyrexianSymbols()
    {
        $input = '{W/U}{G/P}{CHAOS}{PWk}';
        $expected = '<i class="ms ms-wu ms-cost" aria-label="{W/U}" role="img"></i>'
            . '<i class="ms ms-gp ms-cost" aria-label="{G/P}" role="img"></i>'
            . '<i class="ms ms-chaos" aria-label="{CHAOS}" role="img"></i>'
            . '<i class="ms ms-planeswalker ms-fw" aria-label="{PWk}" role="img"></i>';

        $this->assertSame($expected, CardUtils::symbolReplaceFont($input));
    }

    public function testReplacesNumbersNewlinesAndHalfSymbol()
    {
        $input = "Line 1\n{10}{1/2}?";
        $expected = 'Line 1<br>'
            . '<i class="ms ms-10 ms-cost" aria-label="{10}" role="img"></i>'
            . '<i class="ms ms-1-2 ms-cost" aria-label="{1/2}" role="img"></i>'
            . '-';

        $this->assertSame($expected, CardUtils::symbolReplaceFont($input));
    }

    public function testNullInputReturnsNull()
    {
        $this->assertNull(CardUtils::symbolReplaceFont(null));
    }
}
