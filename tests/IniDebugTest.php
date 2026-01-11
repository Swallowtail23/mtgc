<?php

use MTG\Core\AppConfig;
use MTG\Core\IniDebug;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class IniDebugTest extends TestCase
{
    private function buildConfig(string $logfile, int $logLevel = 3): AppConfig
    {
        $iniArray = [
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => $logLevel,
                'Logfile' => $logfile,
                'ImgLocation' => '',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => ''
            ],
            'security' => [
                'Turnstile' => 'disabled',
                'Turnstile_site_key' => '',
                'Turnstile_secret_key' => '',
                'TrustDuration' => 0,
                'Badloginlimit' => 0,
                'AdminIP' => ''
            ],
            'email' => [
                'Email' => 'disabled',
                'AdminEmail' => '',
                'ServerEmail' => '',
                'SMTPDebug' => 'SMTP::DEBUG_OFF',
                'Host' => '',
                'SMTPAuth' => '',
                'Username' => '',
                'Password' => '',
                'SMTPSecure' => '',
                'Port' => 25,
                'SMTPHelo' => '',
                'SMTPVerifySSL' => 1
            ],
            'fx' => [
                'FreecurrencyAPI' => '',
                'TargetCurrency' => ''
            ],
            'comments' => [
                'Disqus' => 'disabled',
                'DisqusDevURL' => '',
                'DisqusProdURL' => ''
            ],
        ];

        return AppConfig::fromIni($iniArray, [
            'general' => [
                'logLevel' => $logLevel,
                'logFile' => $logfile,
            ],
            'email' => [
                'enabled' => false,
            ],
        ]);
    }

    public function testIniDebugWritesWhenEnabled()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtgdebug_');
        $logger = new IniDebug($this->buildConfig($logfile));

        $logger->inidebugging('Debug message');

        $this->assertFileExists($logfile);
        $contents = file_get_contents($logfile);
        $this->assertStringContainsString('Debug message', $contents);
    }

    public function testToStringReturnsMessage()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtgdebug_');
        $logger = new IniDebug($this->buildConfig($logfile));

        $this->assertSame('Called as a string', (string) $logger);
    }
}
