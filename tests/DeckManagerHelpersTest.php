<?php

/*
Version:     1.0
Date:        28/04/26
Name:        DeckManagerHelpersTest.php
Purpose:     Tests deck manager helper methods.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class DeckManagerHelpersTest extends TestCase
{
    private GameRules $gameRules;

    protected function setUp(): void
    {
        $anyQuantity = ['You may have any number of cards named'];
        $this->gameRules = new GameRules([
            'any_quantity' => $anyQuantity,
            'commander_decktypes' => [],
            'commander_multiples' => [],
            'noQuickAddLayouts' => [],
            'deck_legality_map' => [
            [
                'decktype' => 'Standard',
                'db_field' => 'legal_standard'
            ],
            [
                'decktype' => 'Commander',
                'db_field' => 'legal_commander'
            ]
            ]
        ]);
    }

    public function testMtgCardCopyLimitReturnsNullForWishlist()
    {
        $manager = $this->buildDeckManager();

        $this->assertNull($manager->mtgCardCopyLimit('Creature', 'Rules', null, null, 'Wishlist'));
    }

    public function testMtgCardCopyLimitReturnsNullForBasicLand()
    {
        $manager = $this->buildDeckManager();

        $this->assertNull($manager->mtgCardCopyLimit('Basic Land — Plains', 'Rules'));
    }

    public function testMtgCardCopyLimitReturnsNullForAnyQuantityRule()
    {
        $manager = $this->buildDeckManager();
        $ability = 'You may have any number of cards named Persistent Petitioners.';

        $this->assertNull($manager->mtgCardCopyLimit('Creature', $ability));
    }

    public function testMtgCardCopyLimitReturnsWordBasedLimit()
    {
        $manager = $this->buildDeckManager();
        $ability = 'A deck can have up to seven cards named Seven Dwarves.';

        $this->assertSame(7, $manager->mtgCardCopyLimit('Creature', $ability));
    }

    public function testMtgCardCopyLimitDefaultsToFour()
    {
        $manager = $this->buildDeckManager();

        $this->assertSame(4, $manager->mtgCardCopyLimit('Creature', 'No limit here'));
    }

    public function testCardLegalDBFieldReturnsMappedField()
    {
        $manager = $this->buildDeckManager();

        $this->assertSame('legal_commander', $manager->cardLegalDBField('Commander'));
    }

    public function testDeckLegalListReturnsLegalityEntries()
    {
        $db = new DeckManagerHelpersDb(['card-a', 'card-b'], [
            'card-a' => 'legal',
            'card-b' => 'banned'
        ]);
        $manager = $this->buildDeckManager($db);

        $result = $manager->deckLegalList(1, 'Commander', 'legal_commander');

        $this->assertSame(
            [
                ['id' => 'card-a', 'legality' => 'legal'],
                ['id' => 'card-b', 'legality' => 'banned']
            ],
            $result
        );
    }

    public function testDeckLegalListThrowsOnQueryFailure()
    {
        $db = new DeckManagerHelpersDb(['card-a'], ['card-a' => 'legal'], 'deckcards');
        $manager = $this->buildDeckManager($db);

        $this->expectException(Exception::class);
        $manager->deckLegalList(1, 'Commander', 'legal_commander');
    }

    private function buildDeckManager(mixed $db = null): DeckManager
    {
        $db = $db ?: new DeckManagerHelpersDb([], []);
        return new DeckManager(
            $db,
            $GLOBALS['appConfig'],
            $this->gameRules,
            'user@example.test'
        );
    }
}

class DeckManagerHelpersDb
{
    public string $error = 'stub error';
    private array $cards;
    private array $legality;
    private ?string $failOn;

    public function __construct(array $cards, array $legality, ?string $failOn = null)
    {
        $this->cards = $cards;
        $this->legality = $legality;
        $this->failOn = $failOn;
    }

    public function execute_query(string $sql, array $params): DeckManagerHelpersResult|false
    {
        if (strpos($sql, 'FROM deckcards') !== false) {
            if ($this->failOn === 'deckcards') {
                return false;
            }
            return new DeckManagerHelpersResult($this->cards);
        }

        if (strpos($sql, 'FROM cards_scry') !== false) {
            if ($this->failOn === 'cards_scry') {
                return false;
            }
            $field = DeckManagerHelpersResult::extractField($sql);
            $id = $params[0] ?? '';
            $value = $this->legality[$id] ?? null;
            return new DeckManagerHelpersResult($value, $field);
        }

        return false;
    }
}

class DeckManagerHelpersResult
{
    private array $cards = [];
    private int $index = 0;
    private ?string $field;
    private mixed $value = null;

    public function __construct(mixed $data, ?string $field = null)
    {
        if (is_array($data)) {
            $this->cards = $data;
        } else {
            $this->value = $data;
        }
        $this->field = $field;
    }

    public static function extractField(string $sql): string
    {
        if (preg_match('/SELECT\\s+([a-zA-Z0-9_]+)\\s+FROM/i', $sql, $matches)) {
            return $matches[1];
        }
        return 'legality';
    }

    public function fetch_assoc(): ?array
    {
        if ($this->index >= count($this->cards)) {
            return null;
        }
        $card = $this->cards[$this->index];
        $this->index++;
        return ['cardnumber' => $card];
    }

    public function fetch_array(int $mode): array
    {
        unset($mode);
        return [$this->field => $this->value];
    }
}
