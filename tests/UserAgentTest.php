<?php

use MTG\Core\UserAgent;
use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function testBuildsUserAgentFromIniAndVersion()
    {
        $userAgent = UserAgent::buildFromParts(
            '0.2.3-dev',
            'https://mtg.example.net',
            'admin@example.net'
        );

        $this->assertSame(
            "MtGCollection/0.2.3-dev (https://mtg.example.net; admin@example.net)",
            $userAgent
        );
    }

    public function testBuildFromConfigUsesDefaults()
    {
        $config = AppConfig::fromIni([
            'general' => [
                'URL' => '',
                'Logfile' => sys_get_temp_dir() . '/ua.log'
            ],
            'security' => [],
            'email' => [
                'Email' => 'disabled',
                'AdminEmail' => ''
            ],
            'fx' => [],
            'comments' => []
        ]);

        $versionPath = tempnam(sys_get_temp_dir(), 'ua_ver_');
        file_put_contents($versionPath, "v1.2.3\n");

        $userAgent = UserAgent::buildFromConfig($config, $versionPath);
        $this->assertSame('MtGCollection/1.2.3 (unknown; unknown)', $userAgent);

        unlink($versionPath);
    }

    public function testBuildFromConfigHandlesMissingVersionFile()
    {
        $config = AppConfig::fromIni([
            'general' => [
                'URL' => 'https://example.test',
                'Logfile' => sys_get_temp_dir() . '/ua.log'
            ],
            'security' => [],
            'email' => [
                'Email' => 'disabled',
                'AdminEmail' => 'admin@example.test'
            ],
            'fx' => [],
            'comments' => []
        ]);

        $missingPath = sys_get_temp_dir() . '/missing_version_' . uniqid('', true);
        $userAgent = UserAgent::buildFromConfig($config, $missingPath);
        $this->assertSame('MtGCollection/unknown (https://example.test; admin@example.test)', $userAgent);
    }
}
