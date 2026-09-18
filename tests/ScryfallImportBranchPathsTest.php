<?php

/*
Version:     1.1
Date:        04/07/26
Name:        ScryfallImportBranchPathsTest.php
Purpose:     Tests Scryfall import update path classification.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallImport;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class ScryfallBranchQueryStub
{
    public int $num_rows;

    public function __construct(int $numRows)
    {
        $this->num_rows = $numRows;
    }

    public function free(): void
    {
    }
}

class ScryfallBranchState
{
    public mixed $contentHashRef = null;
    public mixed $priceHashRef = null;
}

class ScryfallBranchInsertStmt
{
    public int $affected_rows = 2;
    private ScryfallBranchState $state;
    private array $affectedQueue;

    public function __construct(ScryfallBranchState $state, array $affectedQueue)
    {
        $this->state = $state;
        $this->affectedQueue = $affectedQueue;
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        unset($types);
        $count = count($vars);
        $this->state->contentHashRef = &$vars[$count - 5];
        $this->state->priceHashRef = &$vars[$count - 4];
        return true;
    }

    public function execute(): bool
    {
        if (!empty($this->affectedQueue)) :
            $this->affected_rows = array_shift($this->affectedQueue);
        endif;
        return true;
    }

    public function close(): void
    {
    }
}

class ScryfallBranchHashStmt
{
    public int $num_rows = 1;
    private ScryfallBranchState $state;
    private array $modes;
    private mixed $contentRef = null;
    private mixed $priceRef = null;

    public function __construct(ScryfallBranchState $state, array $modes)
    {
        $this->state = $state;
        $this->modes = $modes;
    }

    public function bind_param(string $types, mixed &...$vars): bool
    {
        unset($types, $vars);
        return true;
    }

    public function execute(): bool
    {
        return true;
    }

    public function store_result(): bool
    {
        $this->num_rows = 1;
        return true;
    }

    public function bind_result(mixed &...$vars): bool
    {
        $this->contentRef = &$vars[0];
        $this->priceRef = &$vars[1];
        return true;
    }

    public function fetch(): bool
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

    public function free_result(): void
    {
    }

    public function close(): void
    {
    }
}

class ScryfallBranchDbStub
{
    public string $error = '';
    private int $prepareCount = 0;
    private ScryfallBranchInsertStmt $insertStmt;
    private ScryfallBranchHashStmt $hashStmt;

    public function __construct(ScryfallBranchInsertStmt $insertStmt, ScryfallBranchHashStmt $hashStmt)
    {
        $this->insertStmt = $insertStmt;
        $this->hashStmt = $hashStmt;
    }

    public function query(string $sql): ScryfallBranchQueryStub
    {
        unset($sql);
        return new ScryfallBranchQueryStub(1);
    }

    public function prepare(string $sql): ScryfallBranchInsertStmt|ScryfallBranchHashStmt
    {
        unset($sql);
        $this->prepareCount++;
        if ($this->prepareCount === 1) :
            return $this->insertStmt;
        endif;
        return $this->hashStmt;
    }

    public function begin_transaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollback(): bool
    {
        return true;
    }

    /**
     * @param array<int, string>|null $params
     * @return \mysqli_result|false
     */
    public function execute_query(string $query, ?array $params = null)
    {
        unset($params);
        if (stripos($query, 'scryfall_game_types') !== false) {
            $rows = [
                ['code' => 'arena', 'label' => 'MtG Arena', 'sort_order' => 10, 'selected' => 1],
                ['code' => 'mtgo', 'label' => 'MtG Online', 'sort_order' => 20, 'selected' => 0],
                ['code' => 'paper', 'label' => 'Paper', 'sort_order' => 30, 'selected' => 1],
            ];
            if (stripos($query, 'selected = 1') !== false) {
                $rows = array_filter($rows, fn($r) => $r['selected'] === 1);
                $rows = array_values($rows);
            }
            if (stripos($query, 'COUNT') !== false || stripos($query, 'count(*)') !== false) {
                $count = count(array_filter($rows, fn($r) => $r['selected'] === 1));
                return new class (['cnt' => $count]) extends \mysqli_result {
                    private array $row;
                    private bool $fetched = false;
                    public function __construct(array $row)
                    {
                        $this->row = $row;
                    }
                    public function fetch_assoc(): ?array
                    {
                        if ($this->fetched) {
                            return null;
                        }
                        $this->fetched = true;
                        return $this->row;
                    }
                    public function free(): void
                    {
                    }
                };
            }
            return new class ($rows) extends \mysqli_result {
                private array $rows;
                private int $index = 0;
                public function __construct(array $rows)
                {
                    $this->rows = $rows;
                }
                public function fetch_assoc(): ?array
                {
                    if ($this->index >= count($this->rows)) {
                        return null;
                    }
                    return $this->rows[$this->index++];
                }
                public function free(): void
                {
                    $this->index = 0;
                }
            };
        }
        $trimmed = trim($query);
        if (in_array(strtolower($trimmed), ['begin', 'commit', 'rollback'], true)) {
            return true;
        }
        $this->error = 'Unmocked execute_query: ' . $query;
        return false;
    }
}

class ScryfallImportBranchPathsTest extends TestCase
{
    public function testScryfallImportTracksContentAndPriceUpdates()
    {
        $fixturePath = __DIR__ . '/test_data/bulk_sample_10.json';
        $cards = json_decode((string) file_get_contents($fixturePath), true);
        $subset = array_slice($cards, 0, 3);
        $tempFile = tempnam(sys_get_temp_dir(), 'scryfall_branch_') . '.jsonl';
        $jsonl = implode(
            "\n",
            array_map(
                static fn (array $card): string => json_encode($card, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $subset
            )
        );
        file_put_contents($tempFile, $jsonl . "\n");

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
