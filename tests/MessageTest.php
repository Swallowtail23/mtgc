<?php

use MTG\Core\AppConfig;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class MessageTest extends TestCase
{
    private function buildConfig(string $logfile, int $logLevel): AppConfig
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

    public function testLogMessageWritesWhenLevelAllows()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtglog_');
        $message = new Message($this->buildConfig($logfile, 3));

        $message->logMessage('[DEBUG]', 'Test message', $logfile);

        $this->assertFileExists($logfile);
        $contents = file_get_contents($logfile);
        $this->assertStringContainsString('Test message', $contents);
    }

    public function testLogMessageSkippedWhenLevelTooLow()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'mtglog_');
        unlink($logfile);
        $message = new Message($this->buildConfig($logfile, 1));

        $message->logMessage('[DEBUG]', 'Should not write', $logfile);

        $this->assertFileDoesNotExist($logfile);
    }
}
