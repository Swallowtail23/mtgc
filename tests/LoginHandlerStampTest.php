<?php

use MTG\Auth\LoginHandler;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class LoginStampDbStub
{
    public $lastQuery;
    public $lastParams;
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function execute_query($query, $params)
    {
        $this->lastQuery = $query;
        $this->lastParams = $params;
        return $this->result;
    }
}

class LoginHandlerStampTest extends TestCase
{
    public function testLoginStampWritesLastLogin()
    {
        $db = new LoginStampDbStub(true);

        $result = LoginHandler::loginStamp($db, $GLOBALS['appConfig'], 'user@example.test');

        $this->assertSame(1, $result);
        $this->assertSame('UPDATE users SET lastlogin_date = ? WHERE email = ?', $db->lastQuery);
        $this->assertSame(date('Y-m-d'), $db->lastParams[0]);
        $this->assertSame('user@example.test', $db->lastParams[1]);
    }

    public function testLoginStampReturnsZeroOnFailure()
    {
        $db = new LoginStampDbStub(false);

        $result = LoginHandler::loginStamp($db, $GLOBALS['appConfig'], 'user@example.test');

        $this->assertSame(0, $result);
    }
}
