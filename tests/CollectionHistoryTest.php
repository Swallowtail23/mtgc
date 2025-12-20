<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../classes/collectionhistory.class.php';

class CollectionHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['logfile'] = 0;
        $GLOBALS['logLevelIni'] = 0;
        $GLOBALS['siteTitle'] = 'Test Site';
        $GLOBALS['serverEmail'] = 'server@example.com';
    }

    public function testBuildCsvIncludesHeaderAndRows()
    {
        $history = new CollectionHistory(new DbStub([]), $GLOBALS['logfile'], 'Test Site');
        $csv = $history->buildCsv(
            [
                [
                    't' => '2025-12-19',
                    'usd' => 12.5,
                    'local' => 15.25,
                    'rate' => 1.22,
                    'cards' => 42
                ],
                [
                    't' => '2025-12-20',
                    'usd' => 13,
                    'local' => null,
                    'rate' => null,
                    'cards' => 43
                ]
            ]
        );

        $this->assertStringContainsString('collected_at,value_usd,value_local,rate_used,card_count', $csv);
        $this->assertStringContainsString('2025-12-19,12.5,15.25,1.22,42', $csv);
        $this->assertStringContainsString('2025-12-20,13,,,43', $csv);
    }

    public function testGetHistoryDataUsesNullStartDateForAllRange()
    {
        $db = new DbStub([]);
        $history = new CollectionHistory($db, $GLOBALS['logfile'], 'Test Site');

        $data = $history->getHistoryData(5, 'all');

        $this->assertSame([], $data);
        $this->assertSame('iss', $db->lastTypes);
        $this->assertSame(5, $db->lastParams[0]);
        $this->assertNull($db->lastParams[1]);
        $this->assertNull($db->lastParams[2]);
    }

    public function testGetHistoryDataMapsRowsAndNormalizesRange()
    {
        $rows = [
            [
                'collected_at' => '2025-12-19 10:00:00',
                'value_usd' => '12.5',
                'value_local' => '14.75',
                'rate_used' => '1.18',
                'card_count' => '99'
            ]
        ];
        $db = new DbStub($rows);
        $history = new CollectionHistory($db, $GLOBALS['logfile'], 'Test Site');

        $data = $history->getHistoryData(7, 'bogus');

        $this->assertCount(1, $data);
        $this->assertSame('2025-12-19', $data[0]['t']);
        $this->assertSame(12.5, $data[0]['usd']);
        $this->assertSame(14.75, $data[0]['local']);
        $this->assertSame(1.18, $data[0]['rate']);
        $this->assertSame(99, $data[0]['cards']);
        $this->assertNotNull($db->lastParams[1]);
    }
}

class DbStub
{
    public $error = '';
    public $lastTypes = '';
    public $lastParams = [];
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function prepare($query)
    {
        return new StmtStub($this, $this->rows);
    }
}

class StmtStub
{
    private $db;
    private $rows;
    public $error = '';

    public function __construct(DbStub $db, array $rows)
    {
        $this->db = $db;
        $this->rows = $rows;
    }

    public function bind_param($types, &...$params)
    {
        $this->db->lastTypes = $types;
        $this->db->lastParams = $params;
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function get_result()
    {
        return new ResultStub($this->rows);
    }

    public function close()
    {
        return true;
    }
}

class ResultStub
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

        return $this->rows[$this->index++];
    }
}
