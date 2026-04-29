<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

if (false) :
    class PasswordCheckNewUserReal extends \MTG\Auth\PasswordCheck
    {
    }
endif;

function getRealPasswordCheckNewUserClass(): string
{
    if (class_exists('PasswordCheckNewUserReal', false)) :
        return 'PasswordCheckNewUserReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/PasswordCheck.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+PasswordCheck\\b/', 'class PasswordCheckNewUserReal', $source, 1);
    eval($source);
    return 'PasswordCheckNewUserReal';
}

class PasswordCheckNewUserDbStub
{
    public function prepare(string $query): never
    {
        throw new RuntimeException('Unexpected DB access for invalid email');
    }
}

class PasswordCheckNewUserTest extends TestCase
{
    public function testNewUserRejectsInvalidEmail()
    {
        $class = getRealPasswordCheckNewUserClass();
        $checker = new $class(new PasswordCheckNewUserDbStub(), $GLOBALS['appConfig']);

        $result = $checker->newUser('Example User', 'not-an-email', 'ValidPass1');

        $this->assertSame(6, $result);
    }
}
