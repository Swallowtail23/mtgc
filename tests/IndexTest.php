<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class IndexTest extends TestCase
{
    private $originalSession;
    private $originalDb;
    private $originalLogfile;
    private $originalSendmailPath;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION ?? [];
        $this->originalDb = $GLOBALS['db'] ?? null;
        $this->originalLogfile = $GLOBALS['logfile'] ?? null;
        $this->originalSendmailPath = ini_get('sendmail_path');
        @session_start();
        $_SESSION = [
            'user' => 1,
            'logged' => true,
            'useremail' => 'test@example.com'
        ];

        $this->stubDocumentRoot();
        require_once __DIR__ . '/index_stubs.php';
        $this->stubDatabase();
        ini_set('sendmail_path', '/bin/true');
        set_include_path(__DIR__ . '/stubs' . PATH_SEPARATOR . get_include_path());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        $GLOBALS['db'] = $this->originalDb;
        $GLOBALS['logfile'] = $this->originalLogfile;
        ini_set('sendmail_path', $this->originalSendmailPath);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexRendersWithStubbedEnvironment()
    {
        $_GET = [];
        $output = '';
        $startingBufferLevel = ob_get_level();
        ob_start();
        try {
            include __DIR__ . '/../index.php';
            $output = (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $startingBufferLevel) :
                ob_end_clean();
            endwhile;
        }

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Search help', $output);
    }

    private function stubDocumentRoot(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/..');
    }

    private function stubDatabase(): void
    {
        $GLOBALS['db'] = new DummyMysqli();
        $GLOBALS['logfile'] = null;
    }
}
