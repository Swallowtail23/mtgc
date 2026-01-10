<?php

use MTG\Cards\DeckManager;
use PHPUnit\Framework\TestCase;

class DeckManagerHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['any_quantity'] = ['You may have any number of cards named'];
        $GLOBALS['deck_legality_map'] = [
            [
                'decktype' => 'Standard',
                'db_field' => 'legal_standard'
            ],
            [
                'decktype' => 'Commander',
                'db_field' => 'legal_commander'
            ]
        ];
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

    private function buildDeckManager($db = null)
    {
        $logfile = $GLOBALS['logfile'] ?? sys_get_temp_dir() . '/phpunit.log';
        $anyQuantity = $GLOBALS['any_quantity'] ?? [];
        $db = $db ?: new DeckManagerHelpersDb([], []);
        return new DeckManager(
            $db,
            $logfile,
            'user@example.test',
            'server@example.test',
            [],
            [],
            $anyQuantity,
            'Test Site'
        );
    }
}

class DeckManagerHelpersDb
{
    public $error = 'stub error';
    private $cards;
    private $legality;
    private $failOn;

    public function __construct(array $cards, array $legality, $failOn = null)
    {
        $this->cards = $cards;
        $this->legality = $legality;
        $this->failOn = $failOn;
    }

    public function execute_query($sql, $params)
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
    private $cards = [];
    private $index = 0;
    private $field;
    private $value;

    public function __construct($data, $field = null)
    {
        if (is_array($data)) {
            $this->cards = $data;
        } else {
            $this->value = $data;
        }
        $this->field = $field;
    }

    public static function extractField($sql)
    {
        if (preg_match('/SELECT\\s+([a-zA-Z0-9_]+)\\s+FROM/i', $sql, $matches)) {
            return $matches[1];
        }
        return 'legality';
    }

    public function fetch_assoc()
    {
        if ($this->index >= count($this->cards)) {
            return null;
        }
        $card = $this->cards[$this->index];
        $this->index++;
        return ['cardnumber' => $card];
    }

    public function fetch_array($mode)
    {
        return [$this->field => $this->value];
    }
}
