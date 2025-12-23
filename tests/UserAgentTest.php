<?php

use MTG\Core\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function testBuildsUserAgentFromIniAndVersion()
    {
        $iniPath = tempnam(sys_get_temp_dir(), 'mtgini_');
        $versionPath = tempnam(sys_get_temp_dir(), 'mtgver_');

        $iniContent = "[general]\nURL = \"https://mtg.example.net\"\n\n[email]\nAdminEmail = \"admin@example.net\"\n";
        file_put_contents($iniPath, $iniContent);
        file_put_contents($versionPath, "v0.2.3-dev\n");

        $userAgent = UserAgent::build($iniPath, $versionPath, null);

        $this->assertSame(
            "MtGCollection/0.2.3-dev (https://mtg.example.net; admin@example.net)",
            $userAgent
        );
    }
}
