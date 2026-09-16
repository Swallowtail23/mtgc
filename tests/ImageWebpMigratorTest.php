<?php

/*
Version:     1.4
Date:        16/09/26
Name:        ImageWebpMigratorTest.php
Purpose:     Tests batched and resumable WebP image migration orchestration.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImageManager;
use MTG\Cards\ImageWebpMigrator;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/MTG/Cards/ImageManager.php';
require_once __DIR__ . '/../src/MTG/Cards/ImageWebpMigrator.php';

class FakeDbForImageWebpMigration
{
    /** @param array<int, string> $ids */
    public function __construct(private array $ids)
    {
    }

    public int $queryCount = 0;
    public int $updateCount = 0;
    public bool $failUpdate = false;
    public string $error = 'stub update error';
    public string $lastUpdateSql = '';
    /** @var array<int, string> */
    public array $lastUpdateParams = [];

    public function execute_query(string $sql, array $params): object|bool
    {
        if (stripos($sql, 'UPDATE `cards_scry`') !== false) :
            $this->updateCount++;
            $this->lastUpdateSql = $sql;
            $this->lastUpdateParams = $params;
            return $this->failUpdate ? false : true;
        endif;

        $after = (string) ($params[0] ?? '');
        $this->queryCount++;
        $rows = [];
        foreach ($this->ids as $id) :
            if ($id > $after) :
                $rows[] = ['id' => $id];
            endif;
        endforeach;

        preg_match('/LIMIT\s+(\d+)/i', $sql, $limitMatch);
        $limit = isset($limitMatch[1]) ? (int) $limitMatch[1] : count($rows);
        $rows = array_slice($rows, 0, $limit);

        return new class ($rows) {
            /** @param array<int, array{id: string}> $rows */
            public function __construct(private array $rows)
            {
            }

            /** @return array{id: string}|false */
            public function fetch_assoc(): array|false
            {
                return array_shift($this->rows) ?? false;
            }

            public function free(): void
            {
            }
        };
    }
}

class TestImageWebpMigrationManager extends ImageManager
{
    /** @var array<string, array{front: array<string, mixed>, back: array<string, mixed>}> */
    public array $cardResults = [];
    public int $cleanupCalls = 0;

    /** @return array{front: array<string, mixed>, back: array<string, mixed>} */
    public function migrateCardToWebp(
        string $cardId,
        bool $deleteJpeg = false,
        bool $dryRun = false
    ): array {
        unset($deleteJpeg, $dryRun);
        return $this->cardResults[$cardId];
    }

    /** @return array{deleted: bool, failed: bool} */
    public function cleanupMigratedJpeg(array $faceResult): array
    {
        $this->cleanupCalls++;
        return $faceResult['test_cleanup'] ?? ['deleted' => false, 'failed' => false];
    }
}

class ImageWebpMigratorTest extends TestCase
{
    public function testProcessesRecordsInBatchesAndAggregatesFaceResults(): void
    {
        $db = new FakeDbForImageWebpMigration(['card-a', 'card-b', 'card-c']);
        $config = AppConfig::fromIni([
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
            ],
        ]);
        $rules = new GameRules(['twoCardDetailSections' => []]);
        $manager = new TestImageWebpMigrationManager($db, $config, $rules);
        $manager->cardResults = [
            'card-a' => $this->cardResult(
                'converted',
                true,
                100,
                60,
                'https://cards.scryfall.io/grid/front/a.webp',
                'image_uri'
            ),
            'card-b' => $this->cardResult(
                'already_webp',
                false,
                0,
                60,
                'https://cards.scryfall.io/grid/front/b.webp',
                'f1_image_uri'
            ),
            'card-c' => $this->cardResult('download_failed', false, 100, 0),
        ];
        $migrator = new ImageWebpMigrator($db, $manager, new Message($config));

        $stats = $migrator->run('', 2, true);

        $this->assertSame(3, $stats['cards_seen']);
        $this->assertSame(1, $stats['faces_converted']);
        $this->assertSame(1, $stats['faces_already_webp']);
        $this->assertSame(1, $stats['faces_failed']);
        $this->assertSame(1, $stats['jpeg_deleted']);
        $this->assertSame(200, $stats['jpeg_bytes_found']);
        $this->assertSame(200, $stats['jpeg_candidate_bytes']);
        $this->assertSame(100, $stats['jpeg_bytes_converted']);
        $this->assertSame(100, $stats['jpeg_bytes_deleted']);
        $this->assertSame(60, $stats['webp_bytes_existing']);
        $this->assertSame(60, $stats['webp_bytes_downloaded']);
        $this->assertSame('card-c', $stats['last_id']);
        $this->assertSame(2, $db->queryCount);
        $this->assertSame(1, $db->updateCount);
        $this->assertStringContainsString('`image_uri` = CASE id', $db->lastUpdateSql);
        $this->assertStringContainsString('`f1_image_uri` = CASE id', $db->lastUpdateSql);
        $this->assertContains('https://cards.scryfall.io/grid/front/a.webp', $db->lastUpdateParams);
        $this->assertContains('https://cards.scryfall.io/grid/front/b.webp', $db->lastUpdateParams);
        $this->assertSame(2, $stats['database_paths_updated']);
        $this->assertSame(0, $stats['database_update_failures']);
        $this->assertSame(2, $manager->cleanupCalls);
    }

    public function testLimitStopsAfterTheRequestedNumberOfCards(): void
    {
        $db = new FakeDbForImageWebpMigration(['card-a', 'card-b', 'card-c']);
        $config = AppConfig::fromIni([
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
            ],
        ]);
        $rules = new GameRules(['twoCardDetailSections' => []]);
        $manager = new TestImageWebpMigrationManager($db, $config, $rules);
        $manager->cardResults = [
            'card-a' => $this->cardResult('dry_run', false, 100, 0),
            'card-b' => $this->cardResult('dry_run', false, 100, 0),
            'card-c' => $this->cardResult('dry_run', false, 100, 0),
        ];
        $migrator = new ImageWebpMigrator($db, $manager, new Message($config));

        $stats = $migrator->run('', 2, false, true, 2);

        $this->assertSame(2, $stats['cards_seen']);
        $this->assertSame(2, $stats['faces_dry_run']);
        $this->assertSame(0, $stats['database_paths_updated']);
        $this->assertSame('card-b', $stats['last_id']);
        $this->assertSame(1, $db->queryCount);
    }

    public function testDatabaseFailureStopsBeforeJpegCleanup(): void
    {
        $db = new FakeDbForImageWebpMigration(['card-a']);
        $db->failUpdate = true;
        $config = AppConfig::fromIni([
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
            ],
        ]);
        $rules = new GameRules(['twoCardDetailSections' => []]);
        $manager = new TestImageWebpMigrationManager($db, $config, $rules);
        $manager->cardResults = [
            'card-a' => $this->cardResult(
                'converted',
                true,
                100,
                60,
                'https://cards.scryfall.io/grid/front/a.webp',
                'image_uri'
            ),
        ];
        $migrator = new ImageWebpMigrator($db, $manager, new Message($config));

        try {
            $migrator->run('', 10, true);
            $this->fail('Expected the batched image URI update to fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unable to update card WebP image URIs', $exception->getMessage());
        }
        $this->assertSame(0, $manager->cleanupCalls);
    }

    /** @return array{front: array<string, mixed>, back: array<string, mixed>} */
    private function cardResult(
        string $status,
        bool $cleanupDeleted,
        int $jpegBytes,
        int $webpBytes,
        string $source = '',
        ?string $databaseField = null
    ): array {
        $face = [
            'status' => $status,
            'source' => $source,
            'database_field' => $databaseField,
            'jpeg_deleted' => false,
            'cleanup_failed' => false,
            'jpeg_bytes' => $jpegBytes,
            'webp_bytes' => $webpBytes,
            'test_cleanup' => ['deleted' => $cleanupDeleted, 'failed' => false],
        ];
        return ['front' => $face, 'back' => ['status' => 'not_required']];
    }
}
