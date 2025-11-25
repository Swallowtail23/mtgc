<?php

use PHPUnit\Framework\TestCase;

class ColourTest extends TestCase
{
    public function testSingleColourBlack()
    {
        $this->assertSame('black', colourfunction('["B"]'));
    }

    public function testTwoColourPair()
    {
        $this->assertSame('orzhov', colourfunction('["W","B"]'));
    }

    public function testSplitCardSpacingIsNormalised()
    {
        $this->assertSame('orzhov', colourfunction('"B // W"'));
    }

    public function testTriColourTemur()
    {
        $this->assertSame('temur', colourfunction('["U","R","G"]'));
    }

    public function testFourColourDune()
    {
        $this->assertSame('dune', colourfunction('["B","G","R","W"]'));
    }

    public function testFiveColour()
    {
        $this->assertSame('five', colourfunction('["W","U","B","R","G"]'));
    }

    public function testArtifactFiveColour()
    {
        $this->assertSame('artifactfive', colourfunction('["W","U","B","R","G","A"]'));
    }

    public function testUnknownColourFallsBackToOther()
    {
        $this->assertSame('other', colourfunction('["X"]'));
    }
}
