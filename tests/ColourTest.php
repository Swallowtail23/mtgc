<?php

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class ColourTest extends TestCase
{
    public function testSingleColourBlack()
    {
        $this->assertSame('black', CardUtils::colourFunction('["B"]'));
    }

    public function testTwoColourPair()
    {
        $this->assertSame('orzhov', CardUtils::colourFunction('["W","B"]'));
    }

    public function testSplitCardSpacingIsNormalised()
    {
        $this->assertSame('orzhov', CardUtils::colourFunction('"B // W"'));
    }

    public function testTriColourTemur()
    {
        $this->assertSame('temur', CardUtils::colourFunction('["U","R","G"]'));
    }

    public function testFourColourDune()
    {
        $this->assertSame('dune', CardUtils::colourFunction('["B","G","R","W"]'));
    }

    public function testFiveColour()
    {
        $this->assertSame('five', CardUtils::colourFunction('["W","U","B","R","G"]'));
    }

    public function testArtifactFiveColour()
    {
        $this->assertSame('artifactfive', CardUtils::colourFunction('["W","U","B","R","G","A"]'));
    }

    public function testUnknownColourFallsBackToOther()
    {
        $this->assertSame('other', CardUtils::colourFunction('["X"]'));
    }

    public function testNullInputFallsBackToOther()
    {
        $this->assertSame('other', CardUtils::colourFunction(null));
    }
}
