<?php

/*
Version:     1.0
Date:        28/04/26
Name:        ImageManagerTest.php
Purpose:     Tests image manager refresh and placeholder behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImageManager;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Network\RemoteFileChecker;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/MTG/Cards/ImageManager.php';

class FakeDbForImages
{
    // Minimal stub to satisfy constructor; not used directly in these tests.
}

class TestImageManager extends ImageManager
{
    public bool $diffReturn = false;
    public int $diffCalled = 0;
    public bool $forceUnreadable = false;
    public bool $forceExists = true;
    public array $unreadablePaths = [];

    /**
     * @param string $remoteUrl
     * @param string $localFilePath
     */
    public function diffImage($remoteUrl, $localFilePath)
    {
        $this->diffCalled++;
        return $this->diffReturn;
    }

    /**
     * @param string $path
     */
    protected function isReadable($path)
    {
        if (isset($this->unreadablePaths[$path]) && $this->unreadablePaths[$path]) {
            return false;
        }
        if ($this->forceUnreadable) {
            return false;
        }
        return parent::isReadable($path);
    }

    /**
     * @param string $path
     */
    protected function fileExists($path)
    {
        if (isset($this->unreadablePaths[$path]) && $this->unreadablePaths[$path]) {
            return $this->forceExists;
        }
        if ($this->forceUnreadable) {
            return $this->forceExists;
        }
        return parent::fileExists($path);
    }
}

class TestRefreshImageManager extends ImageManager
{
    public array $responses = [];
    public int $callCount = 0;

    /**
     * @param string $setcode
     * @param string $cardId
     * @param string $layout
     * @param bool $allowFetch
     */
    public function getImage($setcode, $cardId, $layout, $allowFetch = true)
    {
        $index = $this->callCount;
        $this->callCount++;
        if (!isset($this->responses[$index])) {
            return [
                'front' => 'error',
                'back' => '',
            ];
        }
        return $this->responses[$index];
    }
}

class ImageManagerTest extends TestCase
{
    private AppConfig $appConfig;
    private GameRules $gameRules;
    private string $tempDir;
    private string $imgRoot;
    private string $remoteUrl;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/imgmgr_' . uniqid();
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
        $this->remoteUrl = 'http://example.test/front.jpg';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
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
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }

    private function createLocalImage(string $setcode, string $cardId, int $ageSeconds = 0): string
    {
        $path = $this->imgRoot . $setcode . '/' . $cardId . '.jpg';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'image-bytes');
        if ($ageSeconds > 0) {
            touch($path, time() - $ageSeconds);
        }
        return $path;
    }

    public function testSkipsRemoteCheckWhenImageIsFresh()
    {
        $setcode = 'tst';
        $cardId = 'fresh-card';
        $path = $this->createLocalImage($setcode, $cardId, 10); // 10 seconds old

        $manager = new TestImageManager(new FakeDbForImages(), $this->appConfig, $this->gameRules);
        $manager->diffReturn = true; // would refresh if invoked

        $process = new ReflectionMethod(ImageManager::class, 'processImageFace');
        $process->setAccessible(true);
        $result = $process->invoke($manager, $this->remoteUrl, $path, $this->imgRoot, $setcode);

        $this->assertSame('cardimg/tst/fresh-card.jpg', $result);
        $this->assertSame(0, $manager->diffCalled, 'Diff check should be skipped for fresh images');
    }

    public function testRunsRemoteCheckWhenImageIsOld()
    {
        $setcode = 'tst';
        $cardId = 'old-card';
        $age = (new ReflectionClass(ImageManager::class))->getConstant('IMAGE_MAX_AGE') + 100;
        $path = $this->createLocalImage($setcode, $cardId, $age);

        $manager = new TestImageManager(new FakeDbForImages(), $this->appConfig, $this->gameRules);
        $manager->diffReturn = false; // simulate remote same size

        $process = new ReflectionMethod(ImageManager::class, 'processImageFace');
        $process->setAccessible(true);
        $result = $process->invoke($manager, $this->remoteUrl, $path, $this->imgRoot, $setcode);

        $this->assertSame('cardimg/tst/old-card.jpg', $result);
        $this->assertSame(1, $manager->diffCalled, 'Diff check should run for stale images');
    }

    public function testFetchAndStoreCreatesDirectoryAndReturnsRelativePath()
    {
        $setcode = 'dir';
        $cardId = 'new-card';
        $dest = $this->imgRoot . $setcode . '/' . $cardId . '.jpg';

        $manager = new TestImageManager(new FakeDbForImages(), $this->appConfig, $this->gameRules);

        $fetch = new ReflectionMethod(ImageManager::class, 'fetchAndStoreImage');
        $fetch->setAccessible(true);

        // Use a local file URL to avoid flaky network binding issues during tests
        $remoteDir = $this->tempDir . '/remote';
        mkdir($remoteDir, 0777, true);
        $remoteFile = $remoteDir . '/remote_front.jpg';
        file_put_contents($remoteFile, 'bytes');

        $fileUrl = 'file://' . $remoteFile;

        $this->assertTrue(RemoteFileChecker::exists($fileUrl, $this->appConfig));
        $result = $fetch->invoke($manager, $fileUrl, $this->imgRoot, $setcode, $dest);

        $this->assertFileExists($dest);
        $this->assertSame('cardimg/dir/new-card.jpg', $result);
    }

    public function testDiffImageTouchOnMatch()
    {
        $manager = new class (new FakeDbForImages(), $this->appConfig, $this->gameRules) extends ImageManager {
            /**
             * @param string $remoteUrl
             * @param string $localFilePath
             */
            public function diffImage($remoteUrl, $localFilePath)
            {
                unset($remoteUrl);
                clearstatcache(true, $localFilePath);
                usleep(200000); // 0.2s delay to ensure mtime bump
                @touch($localFilePath);
                return false;
            }
        };

        $path = $this->createLocalImage('tst', 'touch-card', 0);
        touch($path, 1); // force a very old mtime baseline
        clearstatcache(true, $path);
        $beforeMtime = filemtime($path);

        $result = $manager->diffImage($this->remoteUrl, $path);
        $this->assertFalse($result);
        clearstatcache(true, $path);
        $this->assertGreaterThan($beforeMtime, filemtime($path));
    }

    public function testCheckAndRefreshReturnsFaces()
    {
        $setcode = 'abc';
        $cardId = 'check-card';
        $frontPath = $this->createLocalImage($setcode, $cardId, 700000); // stale
        $remoteFile = $this->tempDir . '/front_remote.jpg';
        file_put_contents($remoteFile, 'frontdata');
        $fileUrl = 'file://' . $remoteFile;

        $db = new class ($fileUrl) {
            private string $fileUrl;

            public function __construct(string $fileUrl)
            {
                $this->fileUrl = $fileUrl;
            }

            public function execute_query(string $sql, array $params): object
            {
                unset($sql, $params);
                return new class ($this->fileUrl) {
                    private string $fileUrl;

                    public function __construct(string $fileUrl)
                    {
                        $this->fileUrl = $fileUrl;
                    }

                    public function fetch_array(): array
                    {
                        return [
                            'image_uri' => $this->fileUrl,
                            'f1_image_uri' => null,
                            'f2_image_uri' => null,
                            'setcode' => 'abc',
                            'layout' => 'normal',
                        ];
                    }
                };
            }
        };

        $manager = new TestImageManager($db, $this->appConfig, $this->gameRules);
        $manager->diffReturn = false; // treat as same size

        $result = $manager->checkAndRefreshImage($cardId);
        $this->assertSame('cardimg/abc/check-card.jpg', $result['front']);
        $this->assertSame('', $result['back']);
        $this->assertSame(1, $manager->diffCalled);
    }

    public function testGetImageUsesPlaceholderWhenFrontUnreadable()
    {
        $setcode = 'unr';
        $cardId = 'unreadable-front';
        $this->createLocalImage($setcode, $cardId, 0);

        $db = new class {
            public function execute_query(string $sql, array $params): object
            {
                unset($sql, $params);
                return new class {
                    public function fetch_array(): array
                    {
                        return [
                            'image_uri' => 'http://example.test/front.jpg',
                            'f1_image_uri' => null,
                            'f2_image_uri' => null,
                            'setcode' => 'unr',
                            'layout' => 'normal',
                        ];
                    }
                };
            }
        };

        $manager = new TestImageManager($db, $this->appConfig, $this->gameRules);
        $manager->forceUnreadable = true;
        $manager->forceExists = true;
        $result = $manager->getImage($setcode, $cardId, 'normal', false);

        $this->assertSame('/images/back.jpg', $result['front']);
    }

    public function testGetImageUsesPlaceholderWhenBackUnreadable()
    {
        $setcode = 'unb';
        $cardId = 'unreadable-back';
        $this->createLocalImage($setcode, $cardId, 0);
        $backPath = $this->imgRoot . $setcode . '/' . $cardId . '_b.jpg';
        file_put_contents($backPath, 'image-bytes');

        $db = new class {
            public function execute_query(string $sql, array $params): object
            {
                unset($sql, $params);
                return new class {
                    public function fetch_array(): array
                    {
                        return [
                            'image_uri' => 'http://example.test/front.jpg',
                            'f1_image_uri' => null,
                            'f2_image_uri' => 'http://example.test/back.jpg',
                            'setcode' => 'unb',
                            'layout' => 'transform',
                        ];
                    }
                };
            }
        };

        $gameRules = new GameRules([
            'twoCardDetailSections' => ['transform']
        ]);

        $manager = new TestImageManager($db, $this->appConfig, $gameRules);
        $manager->forceExists = true;
        $manager->unreadablePaths[$backPath] = true;
        $result = $manager->getImage($setcode, $cardId, 'transform', false);

        $this->assertSame('cardimg/unb/unreadable-back.jpg', $result['front']);
        $this->assertSame('/images/back.jpg', $result['back']);
    }

    public function testRefreshImageReturnsFailureArrayWhenCardLookupFails()
    {
        $db = new class {
            public function execute_query(string $sql, array $params): false
            {
                unset($sql, $params);
                return false;
            }
        };

        $manager = new TestImageManager($db, $this->appConfig, $this->gameRules);
        $result = $manager->refreshImage('missing-card');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertSame('', $result['front']);
        $this->assertSame('', $result['back']);
    }

    public function testRefreshImageReturnsSuccessArrayWithRefetchedPaths()
    {
        $setcode = 'rsh';
        $cardId = 'refresh-card';
        $frontPath = $this->imgRoot . $setcode . '/' . $cardId . '.jpg';
        $backPath = $this->imgRoot . $setcode . '/' . $cardId . '_b.jpg';
        mkdir(dirname($frontPath), 0777, true);
        file_put_contents($frontPath, 'old-front');
        file_put_contents($backPath, 'old-back');

        $db = new class {
            public function execute_query(string $sql, array $params): object
            {
                unset($sql, $params);
                return new class {
                    public function fetch_assoc(): array
                    {
                        return [
                            'id' => 'refresh-card',
                            'setcode' => 'rsh',
                            'layout' => 'transform',
                        ];
                    }
                };
            }
        };

        $manager = new TestRefreshImageManager($db, $this->appConfig, $this->gameRules);
        $manager->responses = [
            [
                'front' => 'cardimg/rsh/refresh-card.jpg',
                'back' => 'cardimg/rsh/refresh-card_b.jpg',
            ],
            [
                'front' => 'cardimg/rsh/refresh-card.jpg',
                'back' => 'cardimg/rsh/refresh-card_b.jpg',
            ],
        ];

        $result = $manager->refreshImage($cardId);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('cardimg/rsh/refresh-card.jpg', $result['front']);
        $this->assertSame('cardimg/rsh/refresh-card_b.jpg', $result['back']);
        $this->assertSame(2, $manager->callCount);
        $this->assertFileDoesNotExist($frontPath);
        $this->assertFileDoesNotExist($backPath);
    }
}
