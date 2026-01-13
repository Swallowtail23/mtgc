<?php

use MTG\Cards\DeckManager;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class DeckManagerInputStub extends DeckManager
{
    public array $calls = [];

    public function quickAdd(
        $deckNumber,
        $getString,
        $sideboardTrigger = false,
        $batch = false,
        $commanderMode = null,
        $rowNumber = null,
        $originalLine = null
    ) {
        $this->calls[] = [
            'deckNumber' => $deckNumber,
            'line' => $getString,
            'sideboard' => $sideboardTrigger,
            'batch' => $batch,
            'commanderMode' => $commanderMode,
            'row' => $rowNumber,
            'original' => $originalLine
        ];

        if (stripos($getString, 'Bad Line') !== false) :
            return false;
        endif;

        return true;
    }
}

class DeckManagerProcessInputTest extends TestCase
{
    public function testProcessInputTracksModesAndWarnings()
    {
        $gameRules = new GameRules([
            'importLinestoIgnore' => ['Sideboard']
        ]);
        $db = new class {
        };

        $manager = new DeckManagerInputStub(
            $db,
            $GLOBALS['appConfig'],
            $gameRules,
            'user@example.test'
        );

        $input = implode("\n", [
            'Deckname: Test Deck',
            'Commander',
            'Plains (mh3) 304',
            'Sideboard',
            'Bad Line'
        ]);

        $result = $manager->processInput(5, $input);

        $this->assertSame('multierror', $result);
        $this->assertCount(2, $manager->calls);
        $this->assertSame(
            [
                'deckNumber' => 5,
                'line' => 'Plains (mh3) 304',
                'sideboard' => false,
                'batch' => true,
                'commanderMode' => 'commander',
                'row' => 3,
                'original' => 'Plains (mh3) 304'
            ],
            $manager->calls[0]
        );
        $this->assertSame(
            [
                'deckNumber' => 5,
                'line' => 'Bad Line',
                'sideboard' => true,
                'batch' => true,
                'commanderMode' => null,
                'row' => 5,
                'original' => 'Bad Line'
            ],
            $manager->calls[1]
        );
    }
}
