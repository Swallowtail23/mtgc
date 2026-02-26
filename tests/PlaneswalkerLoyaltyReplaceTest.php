<?php

/*
Version:     1.2
Date:        26/02/26
Name:        PlaneswalkerLoyaltyReplaceTest.php
Purpose:     PHPUnit coverage for planeswalker loyalty symbol replacement.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class PlaneswalkerLoyaltyReplaceTest extends TestCase
{
    public function testReplacesLoyaltyUpAndDown()
    {
        $input = '+1: Draw a card. -2: Deal 3 damage.';
        $expected = '<i class="ms ms-loyalty-up ms-loyalty-1" aria-label="+1" role="img"></i> '
            . 'Draw a card. <i class="ms ms-loyalty-down ms-loyalty-2" aria-label="-2" role="img"></i> '
            . 'Deal 3 damage.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesZeroLoyalty()
    {
        $input = '0: Create a token.';
        $expected = '<i class="ms ms-loyalty-zero ms-loyalty-0" aria-label="0" role="img"></i> '
            . 'Create a token.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesUnicodeMinusLoyalty()
    {
        $input = "−4: Deal 8 damage.";
        $expected = '<i class="ms ms-loyalty-down ms-loyalty-4" aria-label="-4" role="img"></i> '
            . 'Deal 8 damage.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesReplacementCharMinusLoyalty()
    {
        $input = "�7: Exile target permanent.";
        $expected = '<i class="ms ms-loyalty-down ms-loyalty-7" aria-label="-7" role="img"></i> '
            . 'Exile target permanent.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesUtf8GarbleMinusLoyalty()
    {
        $input = "âˆ’7: Exile target permanent.";
        $expected = '<i class="ms ms-loyalty-down ms-loyalty-7" aria-label="-7" role="img"></i> '
            . 'Exile target permanent.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesVariableXLoyalty()
    {
        $input = '-X: Target player mills three times X cards.';
        $expected = '<i class="ms ms-loyalty-down ms-loyalty-x" aria-label="-X" role="img"></i> '
            . 'Target player mills three times X cards.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesPhyrexianRomanNumeralLoyalty()
    {
        $input = "+Ⅰ: Do a thing. -Ⅱ: Do another thing. -Ⅹ: Variable thing.";
        $expected = '<i class="ms ms-loyalty-up ms-loyalty-1" aria-label="+1" role="img"></i> '
            . 'Do a thing. <i class="ms ms-loyalty-down ms-loyalty-2" aria-label="-2" role="img"></i> '
            . 'Do another thing. <i class="ms ms-loyalty-down ms-loyalty-10" aria-label="-10" role="img"></i> '
            . 'Variable thing.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testReplacesHighPhyrexianRomanNumeralLoyalty()
    {
        $input = "+Ⅺ: Add counters. -Ⅻ: Remove counters.";
        $expected = '<i class="ms ms-loyalty-up ms-loyalty-11" aria-label="+11" role="img"></i> '
            . 'Add counters. <i class="ms ms-loyalty-down ms-loyalty-12" aria-label="-12" role="img"></i> '
            . 'Remove counters.';

        $this->assertSame($expected, CardUtils::planeswalkerLoyaltyReplace($input));
    }

    public function testNullInputReturnsNull()
    {
        $this->assertNull(CardUtils::planeswalkerLoyaltyReplace(null));
    }
}
