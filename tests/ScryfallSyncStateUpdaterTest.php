<?php

/*
Version:     1.0
Date:        08/07/26
Name:        ScryfallSyncStateUpdaterTest.php
Purpose:     Tests Scryfall sync-state updater helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallSyncStateUpdater;
use MTG\Core\AppConfig;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

class ScryfallSyncStateStmtStub
{
    public ?string $boundId = null;
    public array $executedIds = [];
    public bool $closed = false;

    public function bind_param(string $types, &...$vars): bool
    {
        unset($types);
        $this->boundId = &$vars[0];
        return true;
    }

    public function execute(): bool
    {
        $this->executedIds[] = $this->boundId;
        return true;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

class ScryfallSyncStateDbStub
{
    public string $error = '';
    public int $affected_rows = 0;
    public ?string $preparedSql = null;
    public ?string $querySql = null;

    public function __construct(public ScryfallSyncStateStmtStub $stmt)
    {
    }

    public function prepare(string $sql): ScryfallSyncStateStmtStub
    {
        $this->preparedSql = $sql;
        return $this->stmt;
    }

    public function query(string $sql): bool
    {
        $this->querySql = $sql;
        return true;
    }
}

class ScryfallSyncStateUpdaterTest extends TestCase
{
    private function buildMessage(): Message
    {
        $ini = [
            'general' => [
                'URL' => '',
                'title' => '',
                'tier' => 'dev',
                'Loglevel' => '',
                'Logfile' => '',
                'ImgLocation' => '',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
                'MaxCardDataAge' => 0,
            ],
            'security' => [],
            'email' => [],
            'fx' => [],
            'comments' => [],
        ];

        return new Message(AppConfig::fromIni($ini));
    }

    public function testPrepareForCardsTableSkipsTestTable(): void
    {
        $db = new ScryfallSyncStateDbStub(new ScryfallSyncStateStmtStub());

        $updater = ScryfallSyncStateUpdater::prepareForCardsTable('cards_scry_test', $db);

        $this->assertNull($updater);
        $this->assertNull($db->preparedSql);
    }

    public function testUpdateExecutesPreparedStatementWithLatestId(): void
    {
        $stmt = new ScryfallSyncStateStmtStub();
        $db = new ScryfallSyncStateDbStub($stmt);

        $updater = ScryfallSyncStateUpdater::prepareForCardsTable('cards_scry', $db);
        $this->assertNotNull($updater);
        $updater->update('first-id', 'added card');
        $updater->update('second-id', 'content update');
        $updater->close();

        $this->assertStringContainsString('scryfall_sync_state', (string) $db->preparedSql);
        $this->assertSame(['first-id', 'second-id'], $stmt->executedIds);
        $this->assertTrue($stmt->closed);
    }

    public function testBackfillDataRunsExpectedStatementAndReturnsAffectedRows(): void
    {
        $db = new ScryfallSyncStateDbStub(new ScryfallSyncStateStmtStub());
        $db->affected_rows = 12;

        $affected = ScryfallSyncStateUpdater::backfillData($db, $this->buildMessage());

        $this->assertSame(12, $affected);
        $this->assertStringContainsString('scryfall_sync_state', (string) $db->querySql);
        $this->assertStringContainsString('cards_scry', (string) $db->querySql);
    }
}
