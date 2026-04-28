<?php

/*
Version:     1.0
Date:        28/04/26
Name:        PasswordResetTest.php
Purpose:     Tests password reset token and password update flows.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Core\AppConfig;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

if (false) :
    class PasswordCheckReal extends \MTG\Auth\PasswordCheck
    {
    }
endif;

function getRealPasswordCheckClass(): string
{
    if (class_exists('PasswordCheckReal', false)) :
        return 'PasswordCheckReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/PasswordCheck.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+PasswordCheck\\b/', 'class PasswordCheckReal', $source, 1);
    eval($source);
    return 'PasswordCheckReal';
}

getRealPasswordCheckClass();

class PasswordCheckStub extends PasswordCheckReal
{
    public array $users = [];
    public array $tokens = [];
    public array $sentLinks = [];
    public array $statusUpdates = [];

    protected function findUserByEmail(string $email): ?array
    {
        return $this->users[$email] ?? null;
    }

    protected function ensureResetTable(): void
    {
    }

    protected function persistResetToken(string $email, string $tokenHash, string $expires): bool
    {
        $this->tokens[$email] = ['token_hash' => $tokenHash, 'expires_at' => $expires];
        return true;
    }

    public function fetchResetRecord(string $email): ?array
    {
        return $this->tokens[$email] ?? null;
    }

    protected function clearResetRecord(string $email): void
    {
        unset($this->tokens[$email]);
    }

    protected function clearExpiredResetTokens(): void
    {
        foreach ($this->tokens as $email => $data) {
            if (strtotime($data['expires_at']) < time()) {
                unset($this->tokens[$email]);
            }
        }
    }

    protected function updateUserStatus(string $email, string $status): bool
    {
        $this->statusUpdates[] = ['email' => $email, 'status' => $status];
        return true;
    }

    protected function updateUserPassword(string $email, string $hashedPassword, bool $setActive = false): bool
    {
        if (!isset($this->users[$email])) {
            return false;
        }
        $this->users[$email]['password'] = $hashedPassword;
        return true;
    }

    protected function getCurrentPasswordHash(string $email): ?string
    {
        return $this->users[$email]['password'] ?? null;
    }

    public function sendPasswordChangeNotification(string $email): bool
    {
        return true;
    }

    protected function sendResetEmail(string $email, string $link): bool
    {
        $this->sentLinks[] = ['email' => $email, 'link' => $link];
        return true;
    }
}

class PasswordResetTest extends TestCase
{
    private PasswordCheckStub $checker;
    private string $baseUrl = 'http://example.test';

    protected function setUp(): void
    {
        $this->checker = new PasswordCheckStub(null, $this->buildAppConfig(true));
        $this->checker->users['user@example.test'] = ['usernumber' => 1, 'email' => 'user@example.test'];
    }

    private function buildAppConfig(bool $emailEnabled): AppConfig
    {
        $logfile = $GLOBALS['logfile'] ?? sys_get_temp_dir() . '/mtg_passwordreset_test.log';
        $siteTitle = 'MTG';
        $serverEmail = 'server@example.test';
        $adminEmail = 'admin@example.test';
        $smtpParameters = [
            'SMTPDebug' => 'SMTP::DEBUG_OFF',
            'SMTPHost' => 'localhost',
            'SMTPAuth' => '',
            'SMTPUsername' => '',
            'SMTPPassword' => '',
            'SMTPSecure' => '',
            'SMTPPort' => 25,
            'SMTPHelo' => 'localhost',
            'SMTPVerifySSL' => 1,
            'globalDebug' => 0
        ];
        $iniArray = [
            'general' => [
                'URL' => $this->baseUrl,
                'title' => $siteTitle,
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => $logfile,
                'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
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
                'Email' => $emailEnabled ? 'enabled' : 'disabled',
                'AdminEmail' => $adminEmail,
                'ServerEmail' => $serverEmail,
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
                'enabled' => $emailEnabled,
                'adminEmail' => $adminEmail,
                'serverEmail' => $serverEmail,
                'smtp' => $smtpParameters,
            ],
        ]);
    }

    public function testRequestResetCreatesTokenAndSendsLink()
    {
        $result = $this->checker->requestResetToken('user@example.test');

        $this->assertTrue($result);
        $this->assertNotEmpty($this->checker->tokens['user@example.test']);
        $this->assertNotEmpty($this->checker->sentLinks);
    }

    public function testRequestResetBlockedWhenEmailDisabled()
    {
        $this->checker = new PasswordCheckStub(null, $this->buildAppConfig(false));

        $result = $this->checker->requestResetToken('user@example.test');

        $this->assertFalse($result);
        $this->assertEmpty($this->checker->tokens);
    }

    public function testCompleteResetSucceedsWithValidToken()
    {
        $this->checker->tokens['user@example.test'] = [
            'token_hash' => password_hash('token123', PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $result = $this->checker->completeReset('user@example.test', 'token123', 'Newpass1');

        $this->assertTrue($result);
        $this->assertArrayNotHasKey('user@example.test', $this->checker->tokens);
        $this->assertTrue(password_verify('Newpass1', $this->checker->users['user@example.test']['password']));
    }

    public function testCompleteResetFailsWhenExpired()
    {
        $this->checker->tokens['user@example.test'] = [
            'token_hash' => password_hash('token123', PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() - 10),
        ];

        $result = $this->checker->completeReset('user@example.test', 'token123', 'Newpass1');

        $this->assertFalse($result);
    }

    public function testExpiredTokensAreClearedOnRequest()
    {
        $this->checker->tokens['old@example.test'] = [
            'token_hash' => password_hash('old', PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ];

        $this->checker->requestResetToken('user@example.test');

        $this->assertArrayNotHasKey('old@example.test', $this->checker->tokens);
    }

    public function testRequestResetWithInvalidEmailIsNonDestructive()
    {
        $result = $this->checker->requestResetToken('not-an-email');

        $this->assertTrue($result);
        $this->assertEmpty($this->checker->tokens);
        $this->assertEmpty($this->checker->sentLinks);
    }

    public function testRequestResetForUnknownUserDoesNotPersistToken()
    {
        $result = $this->checker->requestResetToken('missing@example.test');

        $this->assertTrue($result);
        $this->assertArrayNotHasKey('missing@example.test', $this->checker->tokens);
        $this->assertEmpty($this->checker->sentLinks);
    }

    public function testRequestResetWithForceChangeUpdatesStatus()
    {
        $result = $this->checker->requestResetToken('user@example.test', true);

        $this->assertTrue($result);
        $this->assertEquals(
            [['email' => 'user@example.test', 'status' => 'chgpwd']],
            $this->checker->statusUpdates
        );
        $this->assertArrayHasKey('user@example.test', $this->checker->tokens);
    }

    public function testCompleteResetFailsWithInvalidToken()
    {
        $this->checker->tokens['user@example.test'] = [
            'token_hash' => password_hash('token123', PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $result = $this->checker->completeReset('user@example.test', 'wrongtoken', 'Newpass1');

        $this->assertFalse($result);
        $this->assertArrayHasKey('user@example.test', $this->checker->tokens);
        $this->assertArrayNotHasKey('password', $this->checker->users['user@example.test']);
    }
}
