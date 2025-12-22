<?php

use PHPUnit\Framework\TestCase;

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
    public $num_rows;
    private $row;

    public function __construct($numRows, $row)
    {
        $this->num_rows = $numRows;
        $this->row = $row;
    }

    public function fetch_assoc()
    {
        return $this->row;
    }
}

class UserStatusDbStub
{
    private $results = [];
    public $error = '';
    public $info = '';

    public function __construct($results)
    {
        $this->results = $results;
    }

    public function execute_query($query, $params)
    {
        if (strpos($query, 'status,usernumber,admin') !== false) :
            return $this->results['status'];
        endif;
        if (strpos($query, 'badlogins') !== false) :
            return $this->results['badlogins'];
        endif;
        return false;
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
        $status = new $class($db, $GLOBALS['logfile'], 'user@example.com');

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
        $status = new $class($db, $GLOBALS['logfile'], 'user@example.com');

        $result = $status->getBadLogin();

        $this->assertSame(1, $result['code']);
        $this->assertSame(0, $result['count']);
    }
}
