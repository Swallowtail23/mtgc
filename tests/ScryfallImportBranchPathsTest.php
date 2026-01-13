<?php

use MTG\Bulk\ScryfallImport;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class ScryfallBranchQueryStub
{
    public $num_rows;

    public function __construct($numRows)
    {
        $this->num_rows = $numRows;
    }

    public function free()
    {
    }
}

class ScryfallBranchState
{
    public $contentHashRef;
    public $priceHashRef;
}

class ScryfallBranchInsertStmt
{
    public $affected_rows = 2;
    private $state;
    private $affectedQueue;

    public function __construct(ScryfallBranchState $state, array $affectedQueue)
    {
        $this->state = $state;
        $this->affectedQueue = $affectedQueue;
    }

    public function bind_param($types, &...$vars)
    {
        $count = count($vars);
        $this->state->contentHashRef = &$vars[$count - 5];
        $this->state->priceHashRef = &$vars[$count - 4];
        return true;
    }

    public function execute()
    {
        if (!empty($this->affectedQueue)) :
            $this->affected_rows = array_shift($this->affectedQueue);
        endif;
        return true;
    }

    public function close()
    {
    }
}

class ScryfallBranchHashStmt
{
    public $num_rows = 1;
    private $state;
    private $modes;
    private $contentRef;
    private $priceRef;

    public function __construct(ScryfallBranchState $state, array $modes)
    {
        $this->state = $state;
        $this->modes = $modes;
    }

    public function bind_param($types, &...$vars)
    {
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function store_result()
    {
        $this->num_rows = 1;
        return true;
    }

    public function bind_result(&...$vars)
    {
        $this->contentRef = &$vars[0];
        $this->priceRef = &$vars[1];
        return true;
    }

    public function fetch()
    {
        $mode = array_shift($this->modes) ?? 'both';
        $content = $this->state->contentHashRef ?? '';
        $price = $this->state->priceHashRef ?? '';

        if ($mode === 'content') :
            $this->contentRef = 'diff-content';
            $this->priceRef = $price;
        elseif ($mode === 'price') :
            $this->contentRef = $content;
            $this->priceRef = 'diff-price';
        elseif ($mode === 'both') :
            $this->contentRef = 'diff-content';
            $this->priceRef = 'diff-price';
        else :
            $this->contentRef = $content;
            $this->priceRef = $price;
        endif;

        return true;
    }

    public function free_result()
    {
    }

    public function close()
    {
    }
}

class ScryfallBranchDbStub
{
    public $error = '';
    private $prepareCount = 0;
    private $insertStmt;
    private $hashStmt;

    public function __construct($insertStmt, $hashStmt)
    {
        $this->insertStmt = $insertStmt;
        $this->hashStmt = $hashStmt;
    }

    public function query($sql)
    {
        return new ScryfallBranchQueryStub(1);
    }

    public function prepare($sql)
    {
        $this->prepareCount++;
        if ($this->prepareCount === 1) :
            return $this->insertStmt;
        endif;
        return $this->hashStmt;
    }

    public function begin_transaction()
    {
        return true;
    }

    public function commit()
    {
        return true;
    }

    public function rollback()
    {
        return true;
    }
}

class ScryfallImportBranchPathsTest extends TestCase
{
    public function testScryfallImportTracksContentAndPriceUpdates()
    {
        $fixturePath = __DIR__ . '/test_data/bulk_sample_10.json';
        $cards = json_decode((string) file_get_contents($fixturePath), true);
        $subset = array_slice($cards, 0, 3);
        $tempFile = tempnam(sys_get_temp_dir(), 'scryfall_branch_');
        file_put_contents($tempFile, json_encode($subset));

        $state = new ScryfallBranchState();
        $insertStmt = new ScryfallBranchInsertStmt($state, [2, 2, 2]);
        $hashStmt = new ScryfallBranchHashStmt($state, ['content', 'price', 'both']);
        $db = new ScryfallBranchDbStub($insertStmt, $hashStmt);

        $gameRules = new GameRules([
            'games_to_include' => ['paper'],
            'langs_to_skip' => [],
            'langs_to_skip_all' => [],
            'layouts_to_skip' => [],
        ]);

        $stats = [];
        ScryfallImport::scryfallImport(
            $tempFile,
            'default',
            'cards_scry_test',
            $db,
            $GLOBALS['appConfig'],
            $gameRules,
            $stats
        );

        unlink($tempFile);

        $this->assertSame(3, $stats['updated']);
        $this->assertSame(1, $stats['content_only']);
        $this->assertSame(1, $stats['price_only']);
        $this->assertSame(1, $stats['both']);
    }
}
