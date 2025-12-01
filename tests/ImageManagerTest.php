<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../classes/imagemanager.class.php';

class FakeDbForImages
{
    // Minimal stub to satisfy constructor; not used directly in these tests.
}

class TestImageManager extends ImageManager
{
    public $diffReturn = false;
    public $diffCalled = 0;

    public function diffImage($remoteUrl, $localFilePath)
    {
        $this->diffCalled++;
        return $this->diffReturn;
    }
}

class ImageManagerTest extends TestCase
{
    private $tempDir;
    private $imgRoot;
    private $remoteUrl;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/imgmgr_' . uniqid();
        $this->imgRoot = $this->tempDir . '/cardimg/';
        mkdir($this->imgRoot, 0777, true);

        $GLOBALS['imgLocation'] = $this->imgRoot;
        $GLOBALS['twoCardDetailSections'] = [];
        $GLOBALS['serverEmail'] = 'server@example.test';
        $GLOBALS['adminEmail'] = 'admin@example.test';
        $this->remoteUrl = 'http://example.test/front.jpg';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir($dir): void
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

    private function createLocalImage($setcode, $cardId, $ageSeconds = 0): string
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

        $manager = new TestImageManager(new FakeDbForImages(), $GLOBALS['logfile'], $GLOBALS['serverEmail'], $GLOBALS['adminEmail']);
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

        $manager = new TestImageManager(new FakeDbForImages(), $GLOBALS['logfile'], $GLOBALS['serverEmail'], $GLOBALS['adminEmail']);
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

        $manager = new TestImageManager(new FakeDbForImages(), $GLOBALS['logfile'], $GLOBALS['serverEmail'], $GLOBALS['adminEmail']);

        $fetch = new ReflectionMethod(ImageManager::class, 'fetchAndStoreImage');
        $fetch->setAccessible(true);

        // Use a local file URL to avoid flaky network binding issues during tests
        $remoteDir = $this->tempDir . '/remote';
        mkdir($remoteDir, 0777, true);
        $remoteFile = $remoteDir . '/remote_front.jpg';
        file_put_contents($remoteFile, 'bytes');

        $fileUrl = 'file://' . $remoteFile;

        $this->assertTrue(checkRemoteFile($fileUrl));
        $result = $fetch->invoke($manager, $fileUrl, $this->imgRoot, $setcode, $dest);

        $this->assertFileExists($dest);
        $this->assertSame('cardimg/dir/new-card.jpg', $result);
    }

    public function testDiffImageTouchOnMatch()
    {
        $manager = new class (new FakeDbForImages(), $GLOBALS['logfile'], $GLOBALS['serverEmail'], $GLOBALS['adminEmail']) extends ImageManager {
            public function diffImage($remoteUrl, $localFilePath)
            {
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
            private $fileUrl;
            public function __construct($fileUrl)
            {
                $this->fileUrl = $fileUrl;
            }
            public function execute_query($sql, $params)
            {
                return new class ($this->fileUrl) {
                    private $fileUrl;
                    public function __construct($fileUrl)
                    {
                        $this->fileUrl = $fileUrl;
                    }
                    public function fetch_array()
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

        $manager = new TestImageManager($db, $GLOBALS['logfile'], $GLOBALS['serverEmail'], $GLOBALS['adminEmail']);
        $manager->diffReturn = false; // treat as same size

        $result = $manager->checkAndRefreshImage($cardId);
        $this->assertSame('cardimg/abc/check-card.jpg', $result['front']);
        $this->assertSame('', $result['back']);
        $this->assertSame(1, $manager->diffCalled);
    }
}
