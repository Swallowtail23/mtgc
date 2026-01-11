<?php

use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    public function testFromIniNormalizesValuesAndOverrides()
    {
        $ini = [
            'general' => [
                'tier' => 'stage',
                'Loglevel' => '2',
                'Logfile' => '/tmp/app.log',
                'MaxCardDataAge' => '15'
            ],
            'security' => [
                'TrustDuration' => '30',
                'Badloginlimit' => '5',
                'AdminIP' => ''
            ],
            'email' => [
                'Email' => 'disabled',
                'SMTPVerifySSL' => '0',
                'Port' => '2525'
            ],
            'fx' => [],
            'comments' => []
        ];

        $config = AppConfig::fromIni($ini, [
            'general' => [
                'tier' => 'dev'
            ]
        ]);

        $this->assertSame('dev', $config->general('tier'));
        $this->assertSame(30, $config->security('trustDuration'));
        $this->assertSame(5, $config->security('badLoginLimit'));
        $this->assertFalse($config->getSmtpParameters()['SMTPVerifySSL']);
        $this->assertSame(2525, $config->getSmtpParameters()['SMTPPort']);
        $this->assertSame('15', $config->general('maxCardDataAge'));
    }

    public function testToArrayRedactsSecrets()
    {
        $ini = [
            'general' => [],
            'security' => [
                'Turnstile' => 'enabled',
                'Turnstile_site_key' => 'site',
                'Turnstile_secret_key' => 'secret'
            ],
            'email' => [
                'Email' => 'enabled',
                'Username' => 'user',
                'Password' => 'pass'
            ],
            'fx' => [
                'FreecurrencyAPI' => 'token'
            ],
            'comments' => []
        ];

        $config = AppConfig::fromIni($ini);
        $redacted = $config->toArray();
        $raw = $config->toArrayRaw();

        $this->assertSame('[REDACTED]', $redacted['security']['turnstileSecretKey']);
        $this->assertSame('[REDACTED]', $redacted['email']['smtp']['SMTPPassword']);
        $this->assertSame('[REDACTED]', $redacted['email']['smtp']['SMTPUsername']);
        $this->assertSame('[REDACTED]', $redacted['fx']['api']);

        $this->assertSame('secret', $raw['security']['turnstileSecretKey']);
        $this->assertSame('pass', $raw['email']['smtp']['SMTPPassword']);
        $this->assertSame('user', $raw['email']['smtp']['SMTPUsername']);
        $this->assertSame('token', $raw['fx']['api']);
    }
}
