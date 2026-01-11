<?php

use MTG\Cards\DeckManager;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class DeckManagerCopyLimitTest extends TestCase
{
    private function buildManager($db): DeckManager
    {
        $gameRules = new GameRules([
            'any_quantity' => [],
            'commander_decktypes' => [],
            'commander_multiples' => [],
            'deck_legality_map' => [],
            'noQuickAddLayouts' => [],
        ]);

        return new DeckManager(
            $db,
            $GLOBALS['appConfig'],
            $gameRules,
            'user@example.test'
        );
    }

    public function testNormalCardLimitReached()
    {
        $db = new DeckManagerCopyLimitDb([
            'cardName' => 'Test Card',
            'cardType' => 'Creature',
            'ability' => 'No limit here',
            'deckType' => 'Standard',
            'existingNames' => [],
            'existingTotalQty' => 4,
            'cardRowCount' => 0
        ]);

        $manager = $this->buildManager($db);
        $status = $manager->addDeckCard(1, 'card-1', 'main', 1);

        $this->assertSame('limitreached', $status);
    }

    public function testBasicLandAllowsLargeQuantity()
    {
        $db = new DeckManagerCopyLimitDb([
            'cardName' => 'Plains',
            'cardType' => 'Basic Land — Plains',
            'ability' => '',
            'deckType' => 'Standard',
            'existingNames' => [],
            'existingTotalQty' => 0,
            'cardRowCount' => 0
        ]);

        $manager = $this->buildManager($db);
        $status = $manager->addDeckCard(1, 'card-2', 'main', 10);

        $this->assertSame('+newmain', $status);
    }

    public function testNazgulLimitAllowsUpToNine()
    {
        $db = new DeckManagerCopyLimitDb([
            'cardName' => 'Nazgul',
            'cardType' => 'Creature',
            'ability' => 'A deck can have up to nine cards named Nazgul.',
            'deckType' => 'Standard',
            'existingNames' => [],
            'existingTotalQty' => 8,
            'cardRowCount' => 0
        ]);

        $manager = $this->buildManager($db);
        $status = $manager->addDeckCard(1, 'card-3', 'main', 2);

        $this->assertSame('limitpartial:1', $status);
    }
}

class DeckManagerCopyLimitDb
{
    public $error = 'stub error';
    private $cardName;
    private $cardType;
    private $ability;
    private $deckType;
    private $existingNames;
    private $existingTotalQty;
    private $cardRowCount;

    public function __construct(array $config)
    {
        $this->cardName = $config['cardName'];
        $this->cardType = $config['cardType'];
        $this->ability = $config['ability'];
        $this->deckType = $config['deckType'];
        $this->existingNames = $config['existingNames'];
        $this->existingTotalQty = $config['existingTotalQty'];
        $this->cardRowCount = $config['cardRowCount'];
    }

    public function execute_query($sql, $params = [])
    {
        if (strpos($sql, 'FROM cards_scry') !== false && strpos($sql, 'name,type') !== false) {
            return new DeckManagerCopyLimitResult([[
                'name' => $this->cardName,
                'type' => $this->cardType,
                'f1_type' => null,
                'f2_type' => null,
                'ability' => $this->ability,
                'f1_ability' => null,
                'f2_ability' => null
            ]]);
        }

        if (strpos($sql, 'FROM decks') !== false && strpos($sql, 'SELECT type') !== false) {
            return new DeckManagerCopyLimitResult([[
                'type' => $this->deckType
            ]]);
        }

        if (strpos($sql, 'SUM(IFNULL') !== false) {
            return new DeckManagerCopyLimitResult([[
                'totalqty' => $this->existingTotalQty
            ]]);
        }

        if (strpos($sql, 'FROM deckcards') !== false && strpos($sql, 'LEFT JOIN cards_scry') !== false) {
            $rows = [];
            foreach ($this->existingNames as $name) {
                $rows[] = ['name' => $name];
            }
            return new DeckManagerCopyLimitResult($rows);
        }

        if (strpos($sql, 'SELECT cardqty FROM deckcards') !== false) {
            return new DeckManagerCopyLimitRowCountResult($this->cardRowCount, [
                'cardqty' => 1
            ]);
        }

        return true;
    }
}

class DeckManagerCopyLimitResult
{
    private $rows;
    private $index = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc()
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }
        $row = $this->rows[$this->index];
        $this->index++;
        return $row;
    }
}

class DeckManagerCopyLimitRowCountResult
{
    public $num_rows;
    private $row;

    public function __construct($numRows, array $row)
    {
        $this->num_rows = $numRows;
        $this->row = $row;
    }

    public function fetch_assoc()
    {
        return $this->row;
    }
}
