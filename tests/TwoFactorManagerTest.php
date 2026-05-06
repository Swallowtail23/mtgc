<?php

/*
Version:     1.1
Date:        06/05/26
Name:        TwoFactorManagerTest.php
Purpose:     Tests two-factor manager lookup behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

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

class TwoFactorStmtStub
{
    private TwoFactorResultStub $result;
    public string $query;
    public string $boundTypes = '';
    public array $boundParams = [];

    public function __construct(TwoFactorResultStub $result, string $query = '')
    {
        $this->result = $result;
        $this->query = $query;
    }

    public function bind_param(string $types, mixed &...$params): bool
    {
        $this->boundTypes = $types;
        $this->boundParams = $params;
        return true;
    }

    public function execute(): bool
    {
        return true;
    }

    public function get_result(): TwoFactorResultStub
    {
        return $this->result;
    }
}

class TwoFactorDbStub
{
    /**
     * @var list<TwoFactorResultStub>
     */
    private array $results;
    /**
     * @var list<TwoFactorStmtStub>
     */
    public array $statements = [];

    public function __construct(TwoFactorStmtStub|array $stmtOrResults)
    {
        if ($stmtOrResults instanceof TwoFactorStmtStub) :
            $this->results = [$stmtOrResults->get_result()];
        else :
            $this->results = $stmtOrResults;
        endif;
    }

    public function prepare(string $query): TwoFactorStmtStub
    {
        $result = array_shift($this->results) ?? new TwoFactorResultStub(0, []);
        $stmt = new TwoFactorStmtStub($result, $query);
        $this->statements[] = $stmt;
        return $stmt;
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

    public function testVerifyEmailCodeUpdatesAttemptsAndDeletesByUserId(): void
    {
        $class = getRealTwoFactorManagerClass();
        $db = new TwoFactorDbStub([
            new TwoFactorResultStub(1, ['tfa_method' => 'email']),
            new TwoFactorResultStub(1, ['tfa_backup_codes' => '[]']),
            new TwoFactorResultStub(1, [
                'id' => 999,
                'code' => '123456',
                'expiry' => time() + 300,
                'attempts' => 0,
            ]),
        ]);
        $manager = new $class($db, $GLOBALS['appConfig']);

        $this->assertTrue($manager->verify(10, '123456'));
        $this->assertCount(5, $db->statements);
        $this->assertStringContainsString('UPDATE tfa_codes SET attempts = ?', $db->statements[3]->query);
        $this->assertSame([1, 10], $db->statements[3]->boundParams);
        $this->assertStringContainsString('DELETE FROM tfa_codes WHERE user_id = ?', $db->statements[4]->query);
        $this->assertSame([10], $db->statements[4]->boundParams);
    }

    public function testVerifyInvalidEmailCodeUpdatesAttemptsByUserId(): void
    {
        $class = getRealTwoFactorManagerClass();
        $db = new TwoFactorDbStub([
            new TwoFactorResultStub(1, ['tfa_method' => 'email']),
            new TwoFactorResultStub(1, ['tfa_backup_codes' => '[]']),
            new TwoFactorResultStub(1, [
                'id' => 999,
                'code' => '123456',
                'expiry' => time() + 300,
                'attempts' => 1,
            ]),
        ]);
        $manager = new $class($db, $GLOBALS['appConfig']);

        $this->assertFalse($manager->verify(10, '654321'));
        $this->assertCount(4, $db->statements);
        $this->assertStringContainsString('UPDATE tfa_codes SET attempts = ?', $db->statements[3]->query);
        $this->assertSame([2, 10], $db->statements[3]->boundParams);
    }
}
