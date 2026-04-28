<?php

/*
Version:     1.0
Date:        28/04/26
Name:        UserStatusTest.php
Purpose:     Tests user status lookup and update helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

function getRealUserStatusClass(): string
{
    if (class_exists('UserStatusReal', false)) :
        return 'UserStatusReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/UserStatus.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+UserStatus\\b/', 'class UserStatusReal', $source, 1);
    eval($source);
    return 'UserStatusReal';
}

class UserStatusResultStub
{
    public int $num_rows;
    private array $row;

    public function __construct(int $numRows, array $row)
    {
        $this->num_rows = $numRows;
        $this->row = $row;
    }

    public function fetch_assoc(): array
    {
        return $this->row;
    }
}

class UserStatusDbStub
{
    private array $results = [];
    public string $error = '';
    public string $info = '';

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function execute_query(string $query, array $params): UserStatusResultStub|false
    {
        unset($params);
        if (strpos($query, 'status,usernumber,admin') !== false) :
            return $this->results['status'];
        endif;
        if (strpos($query, 'badlogins') !== false) :
            return $this->results['badlogins'];
        endif;
        return false;
    }
}

class UserStatusExecuteDbStub
{
    public string $lastQuery = '';
    public array $lastParams = [];
    public string $error = '';
    public string $info = 'ok';

    public function execute_query(string $query, array $params): bool
    {
        $this->lastQuery = $query;
        $this->lastParams = $params;
        return true;
    }
}

class UserStatusTest extends TestCase
{
    public function testGetUserStatusActiveReturnsCode10()
    {
        $class = getRealUserStatusClass();
        $results = [
            'status' => new UserStatusResultStub(
                1,
                ['status' => 'active', 'usernumber' => 7, 'admin' => 1]
            ),
            'badlogins' => new UserStatusResultStub(0, [])
        ];
        $db = new UserStatusDbStub($results);
        $status = new $class($db, $GLOBALS['appConfig'], 'user@example.com');

        $result = $status->getUserStatus();

        $this->assertSame(10, $result['code']);
        $this->assertSame(7, $result['number']);
        $this->assertSame(1, $result['admin']);
    }

    public function testGetBadLoginDefaultsNullToZero()
    {
        $class = getRealUserStatusClass();
        $results = [
            'status' => new UserStatusResultStub(0, []),
            'badlogins' => new UserStatusResultStub(1, ['badlogins' => null])
        ];
        $db = new UserStatusDbStub($results);
        $status = new $class($db, $GLOBALS['appConfig'], 'user@example.com');

        $result = $status->getBadLogin();

        $this->assertSame(1, $result['code']);
        $this->assertSame(0, $result['count']);
    }

    public function testIncrementBadLoginUpdatesBadlogins()
    {
        $class = getRealUserStatusClass();
        $db = new UserStatusExecuteDbStub();
        $status = new $class($db, $GLOBALS['appConfig'], 'user@example.com');

        $status->incrementBadLogin();

        $this->assertStringContainsString('UPDATE users', $db->lastQuery);
        $this->assertStringContainsString('badlogins', $db->lastQuery);
        $this->assertSame(['user@example.com'], $db->lastParams);
    }

    public function testZeroBadLoginResetsCount()
    {
        $class = getRealUserStatusClass();
        $db = new UserStatusExecuteDbStub();
        $status = new $class($db, $GLOBALS['appConfig'], 'user@example.com');

        $status->zeroBadLogin();

        $this->assertSame('UPDATE users SET  badlogins = 0 WHERE email=?', $db->lastQuery);
        $this->assertSame(['user@example.com'], $db->lastParams);
    }

    public function testTriggerLockedUpdatesStatus()
    {
        $class = getRealUserStatusClass();
        $db = new UserStatusExecuteDbStub();
        $status = new $class($db, $GLOBALS['appConfig'], 'user@example.com');

        $status->triggerLocked();

        $this->assertSame('UPDATE users SET status=? WHERE email=?', $db->lastQuery);
        $this->assertSame(['locked', 'user@example.com'], $db->lastParams);
    }
}
