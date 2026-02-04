<?php

/*
Version:     1.0
Date:        04/02/26
Name:        ColourIdentityTest.php
Purpose:     PHPUnit coverage for CardUtils colourIdentity.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class ColourIdentityTest extends TestCase
{
    public function testEmptyArrayReturnsEmptyString()
    {
        $expected = '<i class="ms ms-c" aria-label="C" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('[]'));
    }

    public function testSingleColourReturnsSingleIndicator()
    {
        $expected = '<i class="ms ms-ci ms-ci-1 ms-ci-g" aria-label="G" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('["G"]'));
    }

    public function testTwoColourReturnsGuildIndicator()
    {
        $expected = '<i class="ms ms-ci ms-ci-2 ms-ci-gu" aria-label="GU" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('["G","U"]'));
    }

    public function testThreeColourReturnsWedgeIndicator()
    {
        $expected = '<i class="ms ms-ci ms-ci-3 ms-ci-gur" aria-label="GUR" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('["G","U","R"]'));
    }

    public function testThreeColourReturnsShardIndicator()
    {
        $expected = '<i class="ms ms-ci ms-ci-3 ms-ci-wub" aria-label="WUB" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('["W","U","B"]'));
    }

    public function testFourColourReturnsMissingWIndicator()
    {
        $expected = '<i class="ms ms-ci ms-ci-4 ms-ci-ubrg" aria-label="UBRG" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('["U","B","R","G"]'));
    }

    public function testFiveColourReturnsWubrgIndicator()
    {
        $expected = '<i class="ms ms-ci ms-ci-5 ms-ci-wubrg" aria-label="WUBRG" role="img"></i>';
        $this->assertSame($expected, CardUtils::colourIdentity('["W","U","B","R","G"]'));
    }

    public function testNullReturnsEmptyString()
    {
        $this->assertSame('', CardUtils::colourIdentity(null));
    }
}
