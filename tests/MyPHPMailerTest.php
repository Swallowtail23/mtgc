<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/MTG/Core/MyPHPMailer.php';

class MyPHPMailerTest extends TestCase
{
    private $tempLog;

    protected function setUp(): void
    {
        global $siteTitle, $logfile;
        $this->tempLog = tempnam(sys_get_temp_dir(), 'mailer_');
        $logfile = $this->tempLog;
        $siteTitle = 'MTG Test';
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
                'SMTPDebug' => 'SMTP::DEBUG_OFF',
                'globalDebug' => 3
            ],
            $overrides
        );
    }

    public function testHeloIsConfiguredFromParameters()
    {
        $mailer = new \MTG\Core\MyPHPMailer(
            true,
            $this->buildParams(['SMTPHelo' => 'custom.helo']),
            'server@example.com',
            $this->tempLog,
            'Custom Title'
        );

        $this->assertSame('custom.helo', $mailer->Helo);
    }

    public function testSslVerificationOptionsAreDisabledWhenRequested()
    {
        $mailer = new \MTG\Core\MyPHPMailer(
            true,
            $this->buildParams(['SMTPVerifySSL' => false]),
            'server@example.com',
            $this->tempLog,
            'Custom Title'
        );

        $this->assertArrayHasKey('ssl', $mailer->SMTPOptions);
        $this->assertFalse($mailer->SMTPOptions['ssl']['verify_peer']);
        $this->assertFalse($mailer->SMTPOptions['ssl']['verify_peer_name']);
        $this->assertTrue($mailer->SMTPOptions['ssl']['allow_self_signed']);
    }

    public function testSslVerificationOptionsRemainDefaultWhenNotDisabled()
    {
        $mailer = new \MTG\Core\MyPHPMailer(
            true,
            $this->buildParams(),
            'server@example.com',
            $this->tempLog,
            'Custom Title'
        );

        $this->assertSame([], $mailer->SMTPOptions);
    }
}
