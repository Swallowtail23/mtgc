<?php

/*
Version:     1.0
Date:        17/09/26
Name:        ImageCacheCleanerTest.php
Purpose:     Tests stale card-image cache validation and guarded deletion.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImageCacheCleaner;
use MTG\Core\AppConfig;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class FakeDbForImageCacheCleanup
{
    /** @param array<string, string> $records id => setcode */
    public function __construct(private array $records)
    {
    }

    public int $queryCount = 0;
    public ?int $failOnQuery = null;
    public string $error = 'stub cache-validation error';

    /** @param list<string> $params */
    public function execute_query(string $sql, array $params): object|bool
    {
        if (stripos($sql, 'SELECT id, setcode') === false) :
            throw new RuntimeException('Unexpected cleanup query');
        endif;

        $this->queryCount++;
        if ($this->failOnQuery === $this->queryCount) :
            return false;
        endif;

        $rows = [];
        foreach ($params as $id) :
            if (isset($this->records[$id])) :
                $rows[] = ['id' => $id, 'setcode' => $this->records[$id]];
            endif;
        endforeach;

        return new class ($rows) {
            /** @param list<array{id: string, setcode: string}> $rows */
            public function __construct(private array $rows)
            {
            }

            /** @return array{id: string, setcode: string}|false */
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

class ImageCacheCleanerTest extends TestCase
{
    private const CARD_A = '11111111-1111-4111-8111-111111111111';
    private const CARD_B = '22222222-2222-4222-8222-222222222222';
    private const CARD_C = '33333333-3333-4333-8333-333333333333';
    private const CARD_D = '44444444-4444-4444-8444-444444444444';

    private string $imageRoot;

    protected function setUp(): void
    {
        $this->imageRoot = sys_get_temp_dir() . '/mtg-image-cleaner-' . bin2hex(random_bytes(6));
        mkdir($this->imageRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->imageRoot);
    }

    public function testDryRunClassifiesCacheFilesAndExcludesDeckPhotos(): void
    {
        $validFront = $this->createFile('abc/' . self::CARD_A . '.jpg', 'front');
        $validBack = $this->createFile('abc/' . self::CARD_A . '_b.webp', 'back');
        $missing = $this->createFile('abc/' . self::CARD_B . '.webp', 'missing');
        $wrongSet = $this->createFile('old/' . self::CARD_C . '.jpg', 'wrong-set');
        $invalidName = $this->createFile('abc/not-a-uuid.jpg', 'invalid-name');
        $invalidDepth = $this->createFile('abc/nested/' . self::CARD_D . '.webp', 'invalid-depth');
        $skipped = $this->createFile('abc/readme.txt', 'not-an-image');
        $deckPhoto = $this->createFile('deck_photos/not-a-card.jpg', 'deck-photo');
        $db = new FakeDbForImageCacheCleanup([
            self::CARD_A => 'abc',
            self::CARD_C => 'new',
            self::CARD_D => 'abc',
        ]);

        $stats = $this->cleaner($db)->run(2, false);

        $this->assertSame(7, $stats['files_seen']);
        $this->assertSame(6, $stats['image_files_checked']);
        $this->assertSame(2, $stats['files_valid']);
        $this->assertSame(4, $stats['files_stale']);
        $this->assertSame(1, $stats['stale_missing_card']);
        $this->assertSame(1, $stats['stale_wrong_set']);
        $this->assertSame(2, $stats['stale_invalid_path']);
        $this->assertSame(1, $stats['files_skipped']);
        $this->assertSame(0, $stats['files_deleted']);
        $this->assertSame(2, $db->queryCount);
        foreach ([$validFront, $validBack, $missing, $wrongSet, $invalidName, $invalidDepth, $skipped, $deckPhoto] as $path) :
            $this->assertFileExists($path);
        endforeach;
    }

    public function testDeleteModeRemovesOnlyFilesProvenStale(): void
    {
        $valid = $this->createFile('abc/' . self::CARD_A . '.webp', 'valid');
        $missing = $this->createFile('abc/' . self::CARD_B . '.jpg', 'missing');
        $wrongSet = $this->createFile('old/' . self::CARD_C . '.webp', 'wrong-set');
        $invalid = $this->createFile('abc/not-a-uuid.jpg', 'invalid');
        $deckPhoto = $this->createFile('deck_photos/' . self::CARD_B . '.jpg', 'deck-photo');
        $db = new FakeDbForImageCacheCleanup([
            self::CARD_A => 'abc',
            self::CARD_C => 'new',
        ]);

        $stats = $this->cleaner($db)->run(100, true);

        $this->assertSame(1, $stats['files_valid']);
        $this->assertSame(3, $stats['files_stale']);
        $this->assertSame(3, $stats['files_deleted']);
        $this->assertSame(0, $stats['delete_failures']);
        $this->assertFileExists($valid);
        $this->assertFileExists($deckPhoto);
        $this->assertFileDoesNotExist($missing);
        $this->assertFileDoesNotExist($wrongSet);
        $this->assertFileDoesNotExist($invalid);
    }

    public function testDatabaseFailurePreventsAllDeletion(): void
    {
        $first = $this->createFile('abc/' . self::CARD_B . '.jpg', 'first');
        $second = $this->createFile('abc/' . self::CARD_D . '.webp', 'second');
        $db = new FakeDbForImageCacheCleanup([]);
        $db->failOnQuery = 2;

        try {
            $this->cleaner($db)->run(1, true);
            $this->fail('Expected cache validation to fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unable to validate cached card images', $exception->getMessage());
        }

        $this->assertFileExists($first);
        $this->assertFileExists($second);
    }

    public function testLimitBoundsTheNumberOfImageFilesChecked(): void
    {
        $this->createFile('abc/' . self::CARD_A . '.jpg', 'a');
        $this->createFile('abc/' . self::CARD_B . '.jpg', 'b');
        $this->createFile('abc/' . self::CARD_C . '.jpg', 'c');
        $db = new FakeDbForImageCacheCleanup([
            self::CARD_A => 'abc',
            self::CARD_B => 'abc',
            self::CARD_C => 'abc',
        ]);

        $stats = $this->cleaner($db)->run(100, false, 2);

        $this->assertSame(2, $stats['image_files_checked']);
        $this->assertSame(2, $stats['files_valid']);
    }

    private function cleaner(FakeDbForImageCacheCleanup $db): ImageCacheCleaner
    {
        $config = AppConfig::fromIni([
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                'ImgLocation' => $this->imageRoot . '/',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
            ],
        ]);

        return new ImageCacheCleaner($db, new Message($config), $this->imageRoot);
    }

    private function createFile(string $relativePath, string $contents): string
    {
        $path = $this->imageRoot . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory)) :
            mkdir($directory, 0775, true);
        endif;
        file_put_contents($path, $contents);
        return $path;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) :
            return;
        endif;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) :
            if ($entry->isDir()) :
                rmdir($entry->getPathname());
            else :
                unlink($entry->getPathname());
            endif;
        endforeach;
        rmdir($path);
    }
}
