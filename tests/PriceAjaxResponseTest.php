<?php

use MTG\Cards\PriceDisplay;
use PHPUnit\Framework\TestCase;

class PriceAjaxResponseTest extends TestCase
{
    public function testBuildAjaxResponseIncludesExpectedKeys()
    {
        $response = PriceDisplay::buildAjaxResponse('<table></table>', 'https://example.test');

        $this->assertTrue($response['success']);
        $this->assertSame('<table></table>', $response['price_html']);
        $this->assertSame('https://example.test', $response['tcg_link']);

        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertSame($response, $decoded);
    }
}
