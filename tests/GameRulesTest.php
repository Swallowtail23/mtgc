<?php

use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class GameRulesTest extends TestCase
{
    public function testGetLanguageLabelFallbacks()
    {
        $rules = new GameRules([
            'search_langs' => [
                ['code' => 'en', 'pretty' => 'English'],
                ['code' => 'jp', 'pretty' => 'Japanese']
            ]
        ]);

        $this->assertSame('English', $rules->getLanguageLabel('en'));
        $this->assertSame('Japanese', $rules->getLanguageLabel('jp'));
        $this->assertSame('ru', $rules->getLanguageLabel('ru'));
    }

    public function testGetAndAll()
    {
        $rules = new GameRules(['max_card_data_age' => 12]);
        $this->assertSame(12, $rules->get('max_card_data_age'));
        $this->assertSame('fallback', $rules->get('missing', 'fallback'));
        $this->assertSame(['max_card_data_age' => 12], $rules->all());
    }

    public function testFromDefaultsReturnsArray()
    {
        $rules = GameRules::fromDefaults();
        $this->assertIsArray($rules->all());
    }
}
