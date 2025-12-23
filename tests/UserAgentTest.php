<?php

use MTG\Core\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function testBuildsUserAgentFromIniAndVersion()
    {
        $iniPath = tempnam(__DIR__, 'mtgini_');
        $versionPath = tempnam(__DIR__, 'mtgver_');

        $iniContent = "[general]\nURL = \"https://mtg.example.net\"\n\n[email]\nAdminEmail = \"admin@example.net\"\n";
        file_put_contents($iniPath, $iniContent);
        file_put_contents($versionPath, "v0.2.3-dev\n");

        $userAgent = UserAgent::build($iniPath, $versionPath, null);

        $this->assertSame(
            "MtGCollection/0.2.3-dev (https://mtg.example.net; admin@example.net)",
            $userAgent
        );

        if (is_file($iniPath)) :
            unlink($iniPath);
        endif;
        if (is_file($versionPath)) :
            unlink($versionPath);
        endif;
    }
}
