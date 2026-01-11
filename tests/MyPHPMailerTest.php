<?php

use PHPUnit\Framework\TestCase;
use MTG\Core\AppConfig;
use MTG\Core\MyPHPMailer;

require_once __DIR__ . '/../src/MTG/Core/MyPHPMailer.php';

class MyPHPMailerTest extends TestCase
{
    private $tempLog;
    private $appConfig;

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

    private function buildConfig(string $logfile, array $smtpOverrides = []): AppConfig
    {
        $smtpParameters = array_merge($this->buildParams(), $smtpOverrides);
        $iniArray = [
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
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
                'logLevel' => 0,
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
}
