<?php

/*
Version:     1.2
Date:        26/08/26
Name:        ImageManagerTest.php
Purpose:     Tests dual-format Scryfall image caching and explicit refresh behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImageManager;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/MTG/Cards/ImageManager.php';

class FakeDbForImages
{
    /** @param array<string, mixed>|null $row */
    public function __construct(private ?array $row = null, private bool $fail = false)
    {
    }

    public function execute_query(string $sql, array $params): object|false
    {
        unset($sql, $params);
        if ($this->fail || $this->row === null) {
            return false;
        }

        return new class ($this->row) {
            /** @param array<string, mixed> $row */
            public function __construct(private array $row)
            {
            }

            /** @return array<string, mixed> */
            public function fetch_array(int $mode = MYSQLI_BOTH): array
            {
                unset($mode);
                return $this->row;
            }
        };
    }
}

class TestImageManager extends ImageManager
{
    public bool $forceUnreadable = false;
    public bool $forceExists = true;
    /** @var array<string, bool> */
    public array $unreadablePaths = [];

    protected function isReadable(string $path): bool
    {
        if (($this->unreadablePaths[$path] ?? false) || $this->forceUnreadable) {
            return false;
        }
        return parent::isReadable($path);
    }

    protected function fileExists(string $path): bool
    {
        if (($this->unreadablePaths[$path] ?? false) || $this->forceUnreadable) {
            return $this->forceExists;
        }
        return parent::fileExists($path);
    }
}

class ImageManagerTest extends TestCase
{
    private AppConfig $appConfig;
    private GameRules $gameRules;
    private string $tempDir;
    private string $imgRoot;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/imgmgr_' . bin2hex(random_bytes(6));
        $this->imgRoot = $this->tempDir . '/cardimg/';
        mkdir($this->imgRoot, 0777, true);

        $this->appConfig = AppConfig::fromIni(
            [
                'general' => [
                    'URL' => 'http://localhost',
                    'title' => 'Test',
                    'tier' => 'dev',
                    'Loglevel' => 0,
                    'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                    'ImgLocation' => $this->imgRoot,
                    'Timezone' => 'UTC',
                    'Locale' => 'en_US',
                    'Copyright' => ''
                ],
                'security' => [
                    'Turnstile' => 'disabled',
                    'Turnstile_site_key' => '',
                    'Turnstile_secret_key' => '',
                    'TrustDuration' => 0,
                    'Badloginlimit' => 0,
                    'AdminIP' => ''
                ],
                'email' => [
                    'Email' => 'disabled',
                    'AdminEmail' => 'admin@example.test',
                    'ServerEmail' => 'server@example.test',
                    'SMTPDebug' => '',
                    'Host' => '',
                    'SMTPAuth' => '',
                    'Username' => '',
                    'Password' => '',
                    'SMTPSecure' => '',
                    'Port' => 0,
                    'SMTPHelo' => '',
                    'SMTPVerifySSL' => 1
                ],
                'fx' => [
                    'FreecurrencyAPI' => '',
                    'TargetCurrency' => ''
                ],
                'comments' => [
                    'Disqus' => 'disabled',
                    'DisqusDevURL' => '',
                    'DisqusProdURL' => ''
                ]
            ],
            [
                'general' => [
                    'imageBaseDir' => $this->imgRoot
                ],
                'email' => [
                    'enabled' => false
                ],
            ]
        );
        $this->gameRules = new GameRules([
            'twoCardDetailSections' => []
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testPrefersExistingWebpOverLegacyJpeg(): void
    {
        $row = $this->cardRow('pref', 'normal', 'https://img.example/front.webp');
        $this->createLocalImage('pref', 'card-1', '.jpg', 'legacy');
        $this->createLocalImage('pref', 'card-1', '.webp', 'preferred');

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->getImage('pref', 'card-1', 'normal', false);

        $this->assertSame('cardimg/pref/card-1.webp', $result['front']);
    }

    public function testFallsBackToExistingLegacyJpegWithoutFetchingWebp(): void
    {
        $row = $this->cardRow('legacy', 'normal', 'https://img.example/front.webp');
        $jpeg = $this->createLocalImage('legacy', 'card-2', '.jpg', 'legacy');

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->getImage('legacy', 'card-2', 'normal', false);

        $this->assertSame('cardimg/legacy/card-2.jpg', $result['front']);
        $this->assertFileExists($jpeg);
        $this->assertFileDoesNotExist($this->imgRoot . 'legacy/card-2.webp');
    }

    public function testFetchesMissingImageUsingRemoteWebpExtension(): void
    {
        $remoteUrl = $this->createRemoteImage('front.webp', 'webp-bytes');
        $row = $this->cardRow('new', 'normal', $remoteUrl);

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->getImage('new', 'card-3', 'normal');

        $destination = $this->imgRoot . 'new/card-3.webp';
        $this->assertSame('cardimg/new/card-3.webp', $result['front']);
        $this->assertFileExists($destination);
        $this->assertSame('webp-bytes', file_get_contents($destination));
    }

    public function testUsesJpegDestinationForNormalUrlFallback(): void
    {
        $remoteUrl = $this->createRemoteImage('front.jpg', 'jpeg-bytes');
        $row = $this->cardRow('fallback', 'normal', $remoteUrl);

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->getImage('fallback', 'card-4', 'normal');

        $this->assertSame('cardimg/fallback/card-4.jpg', $result['front']);
        $this->assertFileExists($this->imgRoot . 'fallback/card-4.jpg');
    }

    public function testAsyncCheckDoesNotMigrateLegacyJpeg(): void
    {
        $remoteUrl = $this->createRemoteImage('front.webp', 'new-webp');
        $row = $this->cardRow('async-existing', 'normal', $remoteUrl);
        $jpeg = $this->createLocalImage('async-existing', 'card-5', '.jpg', 'legacy-jpeg');

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->checkAndRefreshImage('card-5');

        $this->assertSame('cardimg/async-existing/card-5.jpg', $result['front']);
        $this->assertFalse($result['front_changed']);
        $this->assertFileExists($jpeg);
        $this->assertFileDoesNotExist($this->imgRoot . 'async-existing/card-5.webp');
    }

    public function testAsyncCheckDownloadsMissingWebpImage(): void
    {
        $remoteUrl = $this->createRemoteImage('async-missing.webp', 'webp-bytes');
        $row = $this->cardRow('async-missing', 'normal', $remoteUrl);

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->checkAndRefreshImage('card-6');

        $this->assertSame('cardimg/async-missing/card-6.webp', $result['front']);
        $this->assertTrue($result['front_changed']);
        $this->assertFileExists($this->imgRoot . 'async-missing/card-6.webp');
        $this->assertSame('webp-bytes', file_get_contents($this->imgRoot . 'async-missing/card-6.webp'));
    }

    public function testAsyncCheckDownloadsBothMissingWebpFaces(): void
    {
        $frontUrl = $this->createRemoteImage('async-front.webp', 'front-webp');
        $backUrl = $this->createRemoteImage('async-back.webp', 'back-webp');
        $row = $this->cardRow('async-faces', 'transform', $frontUrl, $backUrl);
        $rules = new GameRules(['twoCardDetailSections' => ['transform']]);

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $rules);
        $result = $manager->checkAndRefreshImage('card-async-faces');

        $this->assertSame('cardimg/async-faces/card-async-faces.webp', $result['front']);
        $this->assertTrue($result['front_changed']);
        $this->assertSame('cardimg/async-faces/card-async-faces_b.webp', $result['back']);
        $this->assertTrue($result['back_changed']);
        $this->assertFileExists($this->imgRoot . 'async-faces/card-async-faces.webp');
        $this->assertFileExists($this->imgRoot . 'async-faces/card-async-faces_b.webp');
    }

    public function testGetImageUsesPlaceholderWhenCachedFrontIsUnreadable(): void
    {
        $row = $this->cardRow('unreadable', 'normal', 'https://img.example/front.webp');
        $manager = new TestImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $manager->forceUnreadable = true;
        $manager->forceExists = true;

        $result = $manager->getImage('unreadable', 'card-7', 'normal', false);

        $this->assertSame('/images/back.jpg', $result['front']);
    }

    public function testMixedCachedFormatsAreResolvedPerFace(): void
    {
        $row = $this->cardRow(
            'mixed',
            'transform',
            'https://img.example/front.webp',
            'https://img.example/back.webp'
        );
        $this->createLocalImage('mixed', 'card-8', '.jpg', 'front-jpeg');
        $this->createLocalImage('mixed', 'card-8_b', '.webp', 'back-webp');
        $rules = new GameRules(['twoCardDetailSections' => ['transform']]);

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $rules);
        $result = $manager->getImage('mixed', 'card-8', 'transform', false);

        $this->assertSame('cardimg/mixed/card-8.jpg', $result['front']);
        $this->assertSame('cardimg/mixed/card-8_b.webp', $result['back']);
    }

    public function testRefreshImageReturnsFailureArrayWhenCardLookupFails(): void
    {
        $manager = new ImageManager(new FakeDbForImages(null, true), $this->appConfig, $this->gameRules);
        $result = $manager->refreshImage('missing-card');

        $this->assertFalse($result['success']);
        $this->assertSame('', $result['front']);
        $this->assertSame('', $result['back']);
    }

    public function testRefreshImageReplacesBothLegacyFacesWithWebp(): void
    {
        $frontUrl = $this->createRemoteImage('refresh-front.webp', 'front-webp');
        $backUrl = $this->createRemoteImage('refresh-back.webp', 'back-webp');
        $row = $this->cardRow('refresh', 'transform', $frontUrl, $backUrl);
        $frontJpeg = $this->createLocalImage('refresh', 'card-9', '.jpg', 'front-jpeg');
        $backJpeg = $this->createLocalImage('refresh', 'card-9_b', '.jpg', 'back-jpeg');
        $rules = new GameRules(['twoCardDetailSections' => ['transform']]);

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $rules);
        $result = $manager->refreshImage('card-9');

        $this->assertTrue($result['success']);
        $this->assertSame('cardimg/refresh/card-9.webp', $result['front']);
        $this->assertSame('cardimg/refresh/card-9_b.webp', $result['back']);
        $this->assertFileDoesNotExist($frontJpeg);
        $this->assertFileDoesNotExist($backJpeg);
    }

    public function testFailedWebpRefreshKeepsExistingLegacyJpeg(): void
    {
        $missingRemote = 'file://' . $this->tempDir . '/remote/missing.webp';
        $row = $this->cardRow('refresh-failure', 'normal', $missingRemote);
        $legacyJpeg = $this->createLocalImage('refresh-failure', 'card-10', '.jpg', 'legacy-jpeg');

        $manager = new ImageManager(new FakeDbForImages($row), $this->appConfig, $this->gameRules);
        $result = $manager->refreshImage('card-10');

        $this->assertFalse($result['success']);
        $this->assertFileExists($legacyJpeg);
        $this->assertSame('legacy-jpeg', file_get_contents($legacyJpeg));
        $this->assertFileDoesNotExist($this->imgRoot . 'refresh-failure/card-10.webp');
    }

    /** @return array<string, mixed> */
    private function cardRow(string $setcode, string $layout, string $frontUrl, string $backUrl = ''): array
    {
        return [
            'image_uri' => $frontUrl,
            'f1_image_uri' => null,
            'f2_image_uri' => $backUrl === '' ? null : $backUrl,
            'setcode' => $setcode,
            'layout' => $layout,
        ];
    }

    private function createLocalImage(string $setcode, string $name, string $extension, string $contents): string
    {
        $path = $this->imgRoot . $setcode . '/' . $name . $extension;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
        return $path;
    }

    private function createRemoteImage(string $name, string $contents): string
    {
        $path = $this->tempDir . '/remote/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
        return 'file://' . $path;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
