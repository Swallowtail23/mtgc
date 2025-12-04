<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

function getRealPasswordCheckClass(): string
{
    if (class_exists('PasswordCheckReal')) :
        return 'PasswordCheckReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../classes/passwordcheck.class.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/class\\s+PasswordCheck/', 'class PasswordCheckReal', $source, 1);
    eval($source);
    return 'PasswordCheckReal';
}

getRealPasswordCheckClass();

class PasswordCheckStub extends PasswordCheckReal
{
    public $users = [];
    public $tokens = [];
    public $sentLinks = [];
    public $statusUpdates = [];

    protected function findUserByEmail($email)
    {
        return $this->users[$email] ?? null;
    }

    protected function ensureResetTable()
    {
    }

    protected function persistResetToken($email, $tokenHash, $expires)
    {
        $this->tokens[$email] = ['token_hash' => $tokenHash, 'expires_at' => $expires];
        return true;
    }

    public function fetchResetRecord($email)
    {
        return $this->tokens[$email] ?? null;
    }

    protected function clearResetRecord($email)
    {
        unset($this->tokens[$email]);
    }

    protected function clearExpiredResetTokens()
    {
        foreach ($this->tokens as $email => $data) {
            if (strtotime($data['expires_at']) < time()) {
                unset($this->tokens[$email]);
            }
        }
    }

    protected function updateUserStatus($email, $status)
    {
        $this->statusUpdates[] = ['email' => $email, 'status' => $status];
        return true;
    }

    protected function updateUserPassword($email, $hashedPassword, $setActive = false)
    {
        if (!isset($this->users[$email])) {
            return false;
        }
        $this->users[$email]['password'] = $hashedPassword;
        return true;
    }

    protected function getCurrentPasswordHash($email)
    {
        return $this->users[$email]['password'] ?? null;
    }

    public function sendPasswordChangeNotification($email)
    {
        return true;
    }

    protected function sendResetEmail($email, $link, $siteTitle, $serverEmail, $smtpParameters)
    {
        $this->sentLinks[] = ['email' => $email, 'link' => $link];
        return true;
    }
}

class PasswordResetTest extends TestCase
{
    private $checker;

    protected function setUp(): void
    {
        global $emailEnabled, $serverEmail, $siteTitle, $myURL, $smtpParameters;
        $emailEnabled = true;
        $serverEmail = 'server@example.test';
        $siteTitle = 'MTG';
        $myURL = 'http://example.test';
        $smtpParameters = ['SMTPDebug' => 'SMTP::DEBUG_OFF'];

        $realClass = getRealPasswordCheckClass();
        $this->checker = new PasswordCheckStub(null, $GLOBALS['logfile'], $siteTitle);
        $this->checker->users['user@example.test'] = ['usernumber' => 1, 'email' => 'user@example.test'];
    }

    public function testRequestResetCreatesTokenAndSendsLink()
    {
        global $emailEnabled;
        $emailEnabled = true;

        $result = $this->checker->requestResetToken('user@example.test');

        $this->assertTrue($result);
        $this->assertNotEmpty($this->checker->tokens['user@example.test']);
        $this->assertNotEmpty($this->checker->sentLinks);
    }

    public function testRequestResetBlockedWhenEmailDisabled()
    {
        global $emailEnabled;
        $emailEnabled = false;

        $result = $this->checker->requestResetToken('user@example.test');

        $this->assertFalse($result);
        $this->assertEmpty($this->checker->tokens);
    }

    public function testCompleteResetSucceedsWithValidToken()
    {
        global $emailEnabled;
        $emailEnabled = true;
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
        global $emailEnabled;
        $emailEnabled = true;
        $this->checker->tokens['user@example.test'] = [
            'token_hash' => password_hash('token123', PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() - 10),
        ];

        $result = $this->checker->completeReset('user@example.test', 'token123', 'Newpass1');

        $this->assertFalse($result);
    }

    public function testExpiredTokensAreClearedOnRequest()
    {
        global $emailEnabled;
        $emailEnabled = true;
        $this->checker->tokens['old@example.test'] = [
            'token_hash' => password_hash('old', PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ];

        $this->checker->requestResetToken('user@example.test');

        $this->assertArrayNotHasKey('old@example.test', $this->checker->tokens);
    }

    public function testRequestResetWithInvalidEmailIsNonDestructive()
    {
        global $emailEnabled;
        $emailEnabled = true;

        $result = $this->checker->requestResetToken('not-an-email');

        $this->assertTrue($result);
        $this->assertEmpty($this->checker->tokens);
        $this->assertEmpty($this->checker->sentLinks);
    }

    public function testRequestResetForUnknownUserDoesNotPersistToken()
    {
        global $emailEnabled;
        $emailEnabled = true;

        $result = $this->checker->requestResetToken('missing@example.test');

        $this->assertTrue($result);
        $this->assertArrayNotHasKey('missing@example.test', $this->checker->tokens);
        $this->assertEmpty($this->checker->sentLinks);
    }

    public function testRequestResetWithForceChangeUpdatesStatus()
    {
        global $emailEnabled;
        $emailEnabled = true;

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
        global $emailEnabled;
        $emailEnabled = true;
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
