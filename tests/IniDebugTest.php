<?php

use MTG\Core\IniDebug;
use PHPUnit\Framework\TestCase;

class IniDebugTest extends TestCase
{
    public function testIniDebugWritesWhenEnabled()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtgdebug_');
        $logger = new IniDebug($logfile);

        $logger->inidebugging('3', $logfile, 'Debug message');

        $this->assertFileExists($logfile);
        $contents = file_get_contents($logfile);
        $this->assertStringContainsString('Debug message', $contents);
    }

    public function testToStringReturnsMessage()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtgdebug_');
        $logger = new IniDebug($logfile);

        $this->assertSame('Called as a string', (string) $logger);
    }
}
