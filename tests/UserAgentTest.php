<?php

use MTG\Core\UserAgent;
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
}
