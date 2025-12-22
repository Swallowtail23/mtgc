<?php

use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testLogMessageWritesWhenLevelAllows()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtglog_');
        $message = new Message($logfile, 3);

        $message->logMessage('[DEBUG]', 'Test message', $logfile);

        $this->assertFileExists($logfile);
        $contents = file_get_contents($logfile);
        $this->assertStringContainsString('Test message', $contents);
    }

    public function testLogMessageSkippedWhenLevelTooLow()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtglog_');
        unlink($logfile);
        $message = new Message($logfile, 1);

        $message->logMessage('[DEBUG]', 'Should not write', $logfile);

        $this->assertFileDoesNotExist($logfile);
    }
}
