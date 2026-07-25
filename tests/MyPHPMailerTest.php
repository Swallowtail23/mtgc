<?php

/*
Version:     1.3
Date:        25/07/26
Name:        MyPHPMailerTest.php
Purpose:     Tests PHPMailer configuration wrapper behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;
use MTG\Core\AppConfig;
use MTG\Core\MyPHPMailer;

require_once __DIR__ . '/../src/MTG/Core/MyPHPMailer.php';

class MyPHPMailerTest extends TestCase
{
    private string $tempLog;
    private AppConfig $appConfig;

    protected function setUp(): void
    {
        $this->tempLog = tempnam(sys_get_temp_dir(), 'mailer_');
        $this->appConfig = $this->buildConfig($this->tempLog);
    }

    protected function tearDown(): void
    {
        if ($this->tempLog && file_exists($this->tempLog)) {
            unlink($this->tempLog);
        }
    }

    private function buildParams(array $overrides = []): array
    {
        return array_merge(
            [
                'SMTPHost' => 'smtp.example.com',
                'SMTPHelo' => 'helo.example.com',
                'SMTPPort' => 2525,
                'SMTPAuth' => true,
                'SMTPUsername' => 'user',
                'SMTPPassword' => 'pass',
                'SMTPSecure' => 'tls',
                'SMTPVerifySSL' => 1,
                'SMTPDebug' => 'SMTP::DEBUG_OFF',
                'globalDebug' => 3
            ],
            $overrides
        );
    }

    private function buildConfig(
        string $logfile,
        array $smtpOverrides = [],
        array $emailOverrides = [],
        int $logLevel = 0
    ): AppConfig {
        $smtpParameters = array_merge($this->buildParams(), $smtpOverrides);
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
            'email' => array_merge([
                'Email' => 'disabled',
                'AdminEmail' => 'admin@example.test',
                'ServerEmail' => 'server@example.test',
                'SMTPDebug' => $smtpParameters['SMTPDebug'],
                'Host' => $smtpParameters['SMTPHost'],
                'SMTPAuth' => $smtpParameters['SMTPAuth'],
                'Username' => $smtpParameters['SMTPUsername'],
                'Password' => $smtpParameters['SMTPPassword'],
                'SMTPSecure' => $smtpParameters['SMTPSecure'],
                'Port' => $smtpParameters['SMTPPort'],
                'SMTPHelo' => $smtpParameters['SMTPHelo'],
                'SMTPVerifySSL' => $smtpParameters['SMTPVerifySSL']
            ], $emailOverrides),
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

    public function testHeloIsConfiguredFromParameters()
    {
        $appConfig = $this->buildConfig($this->tempLog, ['SMTPHelo' => 'custom.helo']);
        $mailer = new MyPHPMailer(true, $appConfig);

        $this->assertSame('custom.helo', $mailer->Helo);
    }

    public function testSslVerificationOptionsAreDisabledWhenRequested()
    {
        $appConfig = $this->buildConfig($this->tempLog, ['SMTPVerifySSL' => false]);
        $mailer = new MyPHPMailer(true, $appConfig);

        $this->assertArrayHasKey('ssl', $mailer->SMTPOptions);
        $this->assertFalse($mailer->SMTPOptions['ssl']['verify_peer']);
        $this->assertFalse($mailer->SMTPOptions['ssl']['verify_peer_name']);
        $this->assertTrue($mailer->SMTPOptions['ssl']['allow_self_signed']);
    }

    public function testSslVerificationOptionsRemainDefaultWhenNotDisabled()
    {
        $mailer = new MyPHPMailer(true, $this->appConfig);

        $this->assertSame([], $mailer->SMTPOptions);
    }

    public function testSenderUsesConfiguredAddress(): void
    {
        $appConfig = $this->buildConfig(
            $this->tempLog,
            [],
            ['SenderEmail' => 'bounce@example.test']
        );
        $mailer = new MyPHPMailer(true, $appConfig);

        $this->assertSame('bounce@example.test', $mailer->Sender);
    }

    public function testSenderFallsBackToReplyToAddress(): void
    {
        $mailer = new MyPHPMailer(true, $this->appConfig);

        $this->assertSame('server@example.test', $mailer->Sender);
    }

    public function testSmtpDebugLogsProtocolTraceAtDebugSiteLevel(): void
    {
        $appConfig = $this->buildConfig(
            $this->tempLog,
            ['SMTPDebug' => 'server'],
            [],
            3
        );
        $mailer = new MyPHPMailer(true, $appConfig);

        $this->assertSame(2, $mailer->SMTPDebug);
        $this->assertIsCallable($mailer->Debugoutput);
        call_user_func($mailer->Debugoutput, "SERVER -> CLIENT: 250 SMTP ready\r\n", 2);

        $log = (string) file_get_contents($this->tempLog);
        $this->assertStringContainsString('[SMTP:2] SERVER -> CLIENT: 250 SMTP ready', $log);
        $this->assertStringNotContainsString('{closure}', $log);
    }

    public function testLegacySmtpDebugConstantIsMappedToNumericLevel(): void
    {
        $appConfig = $this->buildConfig(
            $this->tempLog,
            ['SMTPDebug' => 'SMTP::DEBUG_CONNECTION'],
            [],
            3
        );
        $mailer = new MyPHPMailer(true, $appConfig);

        $this->assertSame(3, $mailer->SMTPDebug);
    }
}
