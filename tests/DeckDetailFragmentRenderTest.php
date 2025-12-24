<?php

use PHPUnit\Framework\TestCase;

class DeckDetailFragmentRenderTest extends TestCase
{
    public function testManaValueFragmentRendersCounts()
    {
        $show_mana_block = true;
        $cmc = [0, 1, 2, 3, 4, 5, 6];
        $avgcmc = 2.5;

        ob_start();
        include __DIR__ . '/../includes/fragments/deckdetail_mana_value.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('deck-mana-value-fragment', $html);
        $this->assertStringContainsString('data-show-chart="1"', $html);
        $this->assertStringContainsString('data-cmc-counts="[0,1,2,3,4,5,6]"', $html);
        $this->assertStringContainsString('Average mana value = 2.5', $html);
    }

    public function testDeckValueFragmentRendersCurrency()
    {
        $show_mana_block = true;
        $deckvalue = 4.52;
        $rate = 1.25;
        $targetCurrency = 'USD';
        $msg = new class {
            public function logMessage($level, $message)
            {
            }
        };

        $currencyFormatter = new NumberFormatter('en-US', NumberFormatter::CURRENCY);
        $expectedValue = $currencyFormatter->format($deckvalue);

        $localFormatter = new NumberFormatter('en-US', NumberFormatter::CURRENCY);
        $localFormatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $targetCurrency);
        $expectedLocal = $localFormatter->format($deckvalue * $rate);

        ob_start();
        include __DIR__ . '/../includes/fragments/deckdetail_deck_value.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('deck-value-fragment', $html);
        $this->assertStringContainsString($expectedValue, $html);
        $this->assertStringContainsString($expectedLocal, $html);
    }

    public function testFragmentResponseStructure()
    {
        require_once __DIR__ . '/../ajax/ajaxdeckfragments_lib.php';

        $fragmentMap = [
            'decklist' => __DIR__ . '/stubs/fragments/decklist.php',
            'warnings' => __DIR__ . '/stubs/fragments/warnings.php'
        ];

        $response = deckdetailBuildFragmentResponse(['warnings'], $fragmentMap);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('fragments', $response);
        $this->assertArrayHasKey('decklist', $response['fragments']);
        $this->assertArrayHasKey('warnings', $response['fragments']);
    }
}
