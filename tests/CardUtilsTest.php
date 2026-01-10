<?php

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class CardUtilsTest extends TestCase
{
    private $originalPromos;

    protected function setUp(): void
    {
        $this->originalPromos = $GLOBALS['promos_to_show'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalPromos !== null) :
            $GLOBALS['promos_to_show'] = $this->originalPromos;
        else :
            unset($GLOBALS['promos_to_show']);
        endif;
    }

    public function testCardTypesNormalFoilEtched()
    {
        $finishes = ['nonfoil', 'foil', 'etched'];

        $this->assertSame('normalfoiletched', CardUtils::cardTypes($finishes));
    }

    public function testCardTypesNormalOnly()
    {
        $finishes = ['nonfoil'];

        $this->assertSame('normalonly', CardUtils::cardTypes($finishes));
    }

    public function testCardTypesFoilOnly()
    {
        $finishes = ['foil'];

        $this->assertSame('foilonly', CardUtils::cardTypes($finishes));
    }

    public function testCardTypesEtchedOnly()
    {
        $finishes = ['etched'];

        $this->assertSame('etchedonly', CardUtils::cardTypes($finishes));
    }

    public function testPromoLookupReturnsDisplay()
    {
        $GLOBALS['promos_to_show'] = [
            ['promotype' => 'bundle', 'display' => 'Bundle promo'],
            ['promotype' => 'prerelease', 'display' => 'Prerelease promo'],
        ];

        $this->assertSame('Bundle promo', CardUtils::promoLookup('bundle'));
    }

    public function testPromoLookupReturnsSkipWhenMissing()
    {
        $GLOBALS['promos_to_show'] = [
            ['promotype' => 'bundle', 'display' => 'Bundle promo'],
        ];

        $this->assertSame('skip', CardUtils::promoLookup('missing'));
    }
}
