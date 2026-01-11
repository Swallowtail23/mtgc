<?php

use MTG\Admin\AdminSettings;
use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

class CssVersionCheckTest extends TestCase
{
    private $tempLog;
    private $appConfig;

    protected function setUp(): void
    {
        $this->tempLog = tempnam(sys_get_temp_dir(), 'cssver_');
        $this->appConfig = $this->buildAppConfig($this->tempLog);
    }

    protected function tearDown(): void
    {
        if ($this->tempLog && file_exists($this->tempLog)) {
            unlink($this->tempLog);
        }
    }

    private function buildAppConfig(string $logFile): AppConfig
    {
        $ini = [
            'general' => [
                'URL' => '',
                'title' => '',
                'tier' => 'dev',
                'Loglevel' => '',
                'Logfile' => $logFile,
                'ImgLocation' => '',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
                'MaxCardDataAge' => 0,
            ],
            'security' => [],
            'email' => [
                'Email' => 'enabled',
                'AdminEmail' => '',
                'ServerEmail' => '',
                'SMTPDebug' => '',
                'Host' => '',
                'SMTPAuth' => '',
                'Username' => '',
                'Password' => '',
                'SMTPSecure' => '',
                'Port' => 0,
                'SMTPVerifySSL' => 1,
            ],
            'fx' => [],
            'comments' => [],
        ];

        return AppConfig::fromIni($ini);
    }

    public function testReturnsMinWhenDatabaseUnavailable()
    {
        $db = null;

        $this->assertSame('-min', AdminSettings::getCssVersionSuffix($db, $this->appConfig));
    }

    public function testReturnsMinWhenQueryFails()
    {
        $db = new class {
            public $error = 'fail';
            public function execute_query($sql)
            {
                return false;
            }
        };

        $this->assertSame('-min', AdminSettings::getCssVersionSuffix($db, $this->appConfig));
    }

    public function testReturnsExpectedBasedOnUseminFlag()
    {
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

        $this->assertSame('-min', AdminSettings::getCssVersionSuffix($db, $this->appConfig));

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

        $this->assertSame('', AdminSettings::getCssVersionSuffix($db, $this->appConfig));
    }
}
