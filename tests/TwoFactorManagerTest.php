<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

function getRealTwoFactorManagerClass(): string
{
    if (class_exists('TwoFactorManagerReal', false)) :
        return 'TwoFactorManagerReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/TwoFactorManager.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+TwoFactorManager\\b/', 'class TwoFactorManagerReal', $source, 1);
    eval($source);
    return 'TwoFactorManagerReal';
}

class TwoFactorResultStub
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

class TwoFactorStmtStub
{
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function bind_param($types, &...$params)
    {
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function get_result()
    {
        return $this->result;
    }
}

class TwoFactorDbStub
{
    private $stmt;

    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    public function prepare($query)
    {
        return $this->stmt;
    }
}

class TwoFactorManagerTest extends TestCase
{
    public function testIsEnabledReturnsTrueWhenFlagSet()
    {
        $class = getRealTwoFactorManagerClass();
        $result = new TwoFactorResultStub(1, ['tfa_enabled' => 1]);
        $stmt = new TwoFactorStmtStub($result);
        $db = new TwoFactorDbStub($stmt);
        $manager = new $class($db, $GLOBALS['appConfig']);

        $this->assertTrue($manager->isEnabled(10));
    }

    public function testGetMethodDefaultsToEmailWhenNoRow()
    {
        $class = getRealTwoFactorManagerClass();
        $result = new TwoFactorResultStub(0, []);
        $stmt = new TwoFactorStmtStub($result);
        $db = new TwoFactorDbStub($stmt);
        $manager = new $class($db, $GLOBALS['appConfig']);

        $this->assertSame('email', $manager->getMethod(10));
    }
}
