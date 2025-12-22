<?php

use PHPUnit\Framework\TestCase;

function getRealSessionManagerClass(): string
{
    if (class_exists('SessionManagerReal', false)) :
        return 'SessionManagerReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/SessionManager.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+SessionManager\\b/', 'class SessionManagerReal', $source, 1);
    eval($source);
    return 'SessionManagerReal';
}

class RateStmtStub
{
    public $num_rows = 1;
    private $rate;
    private $lastUpdate;
    private $boundRate;
    private $boundLastUpdate;

    public function __construct($rate, $lastUpdate)
    {
        $this->rate = $rate;
        $this->lastUpdate = $lastUpdate;
    }

    public function bind_param($types, &...$params)
    {
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function store_result()
    {
        return true;
    }

    public function bind_result(&...$vars)
    {
        $this->boundRate = &$vars[0];
        $this->boundLastUpdate = &$vars[1];
        return true;
    }

    public function fetch()
    {
        $this->boundRate = $this->rate;
        $this->boundLastUpdate = $this->lastUpdate;
        return true;
    }

    public function close()
    {
        return true;
    }
}

class RateDbStub
{
    private $stmt;
    public $error = '';

    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    public function prepare($query)
    {
        return $this->stmt;
    }
}

class SessionManagerTest extends TestCase
{
    public function testGetRateForCurrencyPairUsesCachedRate()
    {
        $class = getRealSessionManagerClass();
        $stmt = new RateStmtStub('1.25', time());
        $db = new RateDbStub($stmt);
        $manager = new $class($db, 1, [], '', '', $GLOBALS['logfile']);

        $rate = $manager->getRateForCurrencyPair('usd_eur');

        $this->assertSame('1.25', $rate);
    }
}
