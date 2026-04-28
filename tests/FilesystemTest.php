<?php

/*
Version:     1.0
Date:        28/04/26
Name:        FilesystemTest.php
Purpose:     Tests filesystem helper behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Core\AppConfig;
use MTG\Core\Filesystem;
use PHPUnit\Framework\TestCase;

class FilesystemTest extends TestCase
{
    private AppConfig $appConfig;

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
