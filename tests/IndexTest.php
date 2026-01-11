<?php

use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private $originalSession;
    private $originalDb;
    private $originalLookups;
    private $originalLogfile;
    private $originalSendmailPath;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION ?? [];
        $this->originalDb = $GLOBALS['db'] ?? null;
        $this->originalLogfile = $GLOBALS['logfile'] ?? null;
        $this->originalSendmailPath = ini_get('sendmail_path');
        $this->originalLookups = [
            'valid_tribe' => $GLOBALS['valid_tribe'] ?? null,
            'search_langs_codes' => $GLOBALS['search_langs_codes'] ?? null
        ];
        @session_start();
        $_SESSION = [
            'user' => 1,
            'logged' => true,
            'useremail' => 'test@example.com'
        ];

        $this->stubDocumentRoot();
        require_once __DIR__ . '/index_stubs.php';
        $this->stubDatabase();
        $this->stubLookups();
        ini_set('sendmail_path', '/bin/true');
        set_include_path(__DIR__ . '/stubs' . PATH_SEPARATOR . get_include_path());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        $GLOBALS['db'] = $this->originalDb;
        $GLOBALS['logfile'] = $this->originalLogfile;
        if ($this->originalLookups['valid_tribe'] !== null) {
            $GLOBALS['valid_tribe'] = $this->originalLookups['valid_tribe'];
        }
        if ($this->originalLookups['search_langs_codes'] !== null) {
            $GLOBALS['search_langs_codes'] = $this->originalLookups['search_langs_codes'];
        }
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

    private function stubLookups(): void
    {
        $GLOBALS['valid_tribe'] = [];
        $GLOBALS['search_langs_codes'] = ['en'];
        $valid_tribe = [];
        $search_langs_codes = ['en'];
        // Make the variables available in the global namespace
        $GLOBALS['valid_tribe'] = $valid_tribe;
        $GLOBALS['search_langs_codes'] = $search_langs_codes;
    }
}
