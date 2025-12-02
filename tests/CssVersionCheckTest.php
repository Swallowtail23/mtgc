<?php

use PHPUnit\Framework\TestCase;

class CssVersionCheckTest extends TestCase
{
    private $originalDb;
    private $originalLogfile;
    private $tempLog;

    protected function setUp(): void
    {
        global $db, $logfile;
        $this->originalDb = $db;
        $this->originalLogfile = $logfile;
        $this->tempLog = tempnam(sys_get_temp_dir(), 'cssver_');
        $logfile = $this->tempLog;
    }

    protected function tearDown(): void
    {
        global $db, $logfile;
        $db = $this->originalDb;
        $logfile = $this->originalLogfile;
        if ($this->tempLog && file_exists($this->tempLog)) {
            unlink($this->tempLog);
        }
    }

    public function testReturnsMinWhenDatabaseUnavailable()
    {
        global $db;
        $db = null;

        $this->assertSame('-min', cssVersionCheck());
    }

    public function testReturnsMinWhenQueryFails()
    {
        global $db;
        $db = new class {
            public $error = 'fail';
            public function execute_query($sql)
            {
                return false;
            }
        };

        $this->assertSame('-min', cssVersionCheck());
    }

    public function testReturnsExpectedBasedOnUseminFlag()
    {
        global $db;
        $db = new class {
            public function execute_query($sql)
            {
                return new class {
                    private $returnMin;
                    private $fetched = false;
                    public function __construct($returnMin = true)
                    {
                        $this->returnMin = $returnMin;
                    }
                    public function fetch_assoc()
                    {
                        if ($this->fetched) {
                            return null;
                        }
                        $this->fetched = true;
                        return ['usemin' => $this->returnMin ? 1 : 0];
                    }
                    public function free()
                    {
                    }
                };
            }
        };

        $this->assertSame('-min', cssVersionCheck());

        $db = new class {
            public function execute_query($sql)
            {
                return new class {
                    private $fetched = false;
                    public function fetch_assoc()
                    {
                        if ($this->fetched) {
                            return null;
                        }
                        $this->fetched = true;
                        return ['usemin' => 0];
                    }
                    public function free()
                    {
                    }
                };
            }
        };

        $this->assertSame('', cssVersionCheck());
    }
}
