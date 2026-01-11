<?php

use MTG\Cards\PriceDisplay;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class PriceDisplayTest extends TestCase
{
    public function testComputePricesUsesScryfallWhenPresent()
    {
        $scryfall = [
            'price' => '1.23',
            'price_foil' => null,
            'price_etched' => null
        ];
        $row = [
            'price' => '0.50',
            'price_foil' => '2.00',
            'price_etched' => '3.00'
        ];

        $prices = PriceDisplay::computePrices($scryfall, $row, 'normalonly', 2, $GLOBALS['appConfig']);

        $this->assertSame('1.23', $prices['normalprice']);
        $this->assertSame('2.46', $prices['localnormal']);
        $this->assertFalse($prices['foilprice']);
        $this->assertFalse($prices['etchprice']);
    }

    public function testRenderTableShowsNoPricesWhenEmpty()
    {
        $prices = [
            'normalprice' => false,
            'localnormal' => null,
            'foilprice' => false,
            'localfoil' => null,
            'etchprice' => false,
            'localetched' => null
        ];

        $html = PriceDisplay::renderTable($prices, false, 'aud');

        $this->assertStringContainsString('No prices available', $html);
    }

    public function testRenderTableIncludesTargetCurrencyWhenFxEnabled()
    {
        $prices = [
            'normalprice' => '1.00',
            'localnormal' => '1.60',
            'foilprice' => false,
            'localfoil' => null,
            'etchprice' => false,
            'localetched' => null
        ];

        $html = PriceDisplay::renderTable($prices, true, 'AUD');

        $this->assertStringContainsString('USD', $html);
        $this->assertStringContainsString('(AUD)', $html);
        $this->assertStringContainsString('1.00 (1.60)', $html);
    }
}
