<?php

use PHPUnit\Framework\TestCase;

class ColourTest extends TestCase
{
    public function testSingleColourBlack()
    {
        $this->assertSame('black', colourFunction('["B"]'));
    }

    public function testTwoColourPair()
    {
        $this->assertSame('orzhov', colourFunction('["W","B"]'));
    }

    public function testSplitCardSpacingIsNormalised()
    {
        $this->assertSame('orzhov', colourFunction('"B // W"'));
    }

    public function testTriColourTemur()
    {
        $this->assertSame('temur', colourFunction('["U","R","G"]'));
    }

    public function testFourColourDune()
    {
        $this->assertSame('dune', colourFunction('["B","G","R","W"]'));
    }

    public function testFiveColour()
    {
        $this->assertSame('five', colourFunction('["W","U","B","R","G"]'));
    }

    public function testArtifactFiveColour()
    {
        $this->assertSame('artifactfive', colourFunction('["W","U","B","R","G","A"]'));
    }

    public function testUnknownColourFallsBackToOther()
    {
        $this->assertSame('other', colourFunction('["X"]'));
    }
}
