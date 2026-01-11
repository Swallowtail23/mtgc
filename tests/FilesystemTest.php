<?php

use MTG\Core\AppConfig;
use MTG\Core\Filesystem;
use PHPUnit\Framework\TestCase;

class FilesystemTest extends TestCase
{
    private $appConfig;

    protected function setUp(): void
    {
        $ini = [
            'general' => [
                'Logfile' => sys_get_temp_dir() . '/filesystem_test.log'
            ],
            'security' => [],
            'email' => [],
            'fx' => [],
            'comments' => []
        ];
        $this->appConfig = AppConfig::fromIni($ini);
    }

    public function testEnsureDirectoryExistsCreatesDirectory()
    {
        $path = sys_get_temp_dir() . '/mtg_fs_' . uniqid('', true);
        $this->assertFalse(is_dir($path));

        Filesystem::ensureDirectoryExists($path, $this->appConfig);

        $this->assertTrue(is_dir($path));
        rmdir($path);
    }

    public function testEnsureDirectoryExistsThrowsWhenBlocked()
    {
        $path = tempnam(sys_get_temp_dir(), 'mtg_file_');
        $this->expectException(Exception::class);
        try {
            Filesystem::ensureDirectoryExists($path, $this->appConfig);
        } finally {
            unlink($path);
        }
    }
}
