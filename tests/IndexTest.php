<?php

use PHPUnit\Framework\TestCase;

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

    public function testIndexRendersWithStubbedEnvironment()
    {
        $_GET = [];
        ob_start();
        include __DIR__ . '/../index.php';
        $output = ob_get_clean();

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
