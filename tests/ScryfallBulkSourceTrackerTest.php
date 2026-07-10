<?php

use MTG\Bulk\ScryfallBulkSourceTracker;
use PHPUnit\Framework\TestCase;

class ScryfallBulkSourceTrackerResultStub
{
    public function __construct(public int $num_rows)
    {
    }

    public function free(): void
    {
    }
}

class ScryfallBulkSourceTrackerDbStub
{
    /** @var array<int, array<int, mixed>> */
    public array $parameters = [];
    public string $error = '';

    public function execute_query(string $sql, array $parameters): ScryfallBulkSourceTrackerResultStub|bool
    {
        $this->parameters[] = $parameters;
        return new ScryfallBulkSourceTrackerResultStub(str_contains($sql, 'SELECT 1') ? 1 : 0);
    }
}

class ScryfallBulkSourceTrackerTest extends TestCase
{
    public function testTracksSuccessfulSnapshotAndUsesItForSkipChecks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scryfall-source-');
        $this->assertNotFalse($path);
        file_put_contents($path, 'source');
        $db = new ScryfallBulkSourceTrackerDbStub();
        $tracker = new ScryfallBulkSourceTracker($db);

        $this->assertTrue($tracker->isCurrent('oracle_tags', 'https://example.test/source', $path));
        $tracker->markCompleted('oracle_tags', 'https://example.test/source', $path);

        $this->assertSame('oracle_tags', $db->parameters[0][0]);
        $this->assertSame('completed', $db->parameters[1][6]);
        $this->assertSame(hash_file('sha256', $path), $db->parameters[1][5]);
        unlink($path);
    }
}
