<?php

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class CardUtilsTest extends TestCase
{
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
        $promosToShow = [
            ['promotype' => 'bundle', 'display' => 'Bundle promo'],
            ['promotype' => 'prerelease', 'display' => 'Prerelease promo'],
        ];

        $this->assertSame('Bundle promo', CardUtils::promoLookup('bundle', $promosToShow));
    }

    public function testPromoLookupReturnsSkipWhenMissing()
    {
        $promosToShow = [
            ['promotype' => 'bundle', 'display' => 'Bundle promo'],
        ];

        $this->assertSame('skip', CardUtils::promoLookup('missing', $promosToShow));
    }
}
