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
    private function resetRequestState(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
    }

    public function testGetRateForCurrencyPairUsesCachedRate()
    {
        $class = getRealSessionManagerClass();
        $stmt = new RateStmtStub('1.25', time());
        $db = new RateDbStub($stmt);
        $manager = new $class($db, [], $GLOBALS['appConfig']);

        $rate = $manager->getRateForCurrencyPair('usd_eur');

        $this->assertSame('1.25', $rate);
    }

    public function testGenerateCsrfTokenStoresToken()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();

        $token = $class::generateCsrfToken();

        $this->assertIsString($token);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGenerateCsrfTokenKeepsExistingToken()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['csrf_token'] = 'existing-token';

        $token = $class::generateCsrfToken();

        $this->assertSame('existing-token', $token);
    }

    public function testValidateCsrfTokenMatchesSession()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['csrf_token'] = 'test-token';

        $this->assertTrue($class::validateCsrfToken('test-token'));
        $this->assertFalse($class::validateCsrfToken('wrong-token'));
    }

    public function testValidateCsrfTokenRejectsMissingSessionToken()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();

        $this->assertFalse($class::validateCsrfToken('test-token'));
    }

    public function testValidateAjaxRequestAcceptsValidReferrerAndCsrf()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['csrf_token'] = 'valid-token';
        $_POST['csrf_token'] = 'valid-token';
        $_SERVER['HTTP_REFERER'] = 'https://example.test/collection.php';

        $result = $class::validateAjaxRequest(['collection.php'], $GLOBALS['appConfig'], 'test');

        $this->assertSame(['valid' => true, 'reason' => ''], $result);
    }

    public function testValidateAjaxRequestRejectsInvalidReferrer()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['csrf_token'] = 'valid-token';
        $_POST['csrf_token'] = 'valid-token';
        $_SERVER['HTTP_REFERER'] = 'https://evil.test/';

        $result = $class::validateAjaxRequest(['collection.php'], $GLOBALS['appConfig'], 'test');

        $this->assertSame(['valid' => false, 'reason' => 'referrer'], $result);
    }

    public function testValidateAjaxRequestRejectsInvalidCsrf()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['csrf_token'] = 'valid-token';
        $_POST['csrf_token'] = 'wrong-token';
        $_SERVER['HTTP_REFERER'] = 'https://example.test/collection.php';

        $result = $class::validateAjaxRequest(['collection.php'], $GLOBALS['appConfig'], 'test');

        $this->assertSame(['valid' => false, 'reason' => 'csrf'], $result);
    }

    public function testValidateAjaxRequestSkipsCsrfWhenDisabled()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SERVER['HTTP_REFERER'] = 'https://example.test/collection.php';

        $result = $class::validateAjaxRequest(['collection.php'], $GLOBALS['appConfig'], 'test', false);

        $this->assertSame(['valid' => true, 'reason' => ''], $result);
    }

    public function testForcePasswordChangeDoesNotRedirectWhenDisabled()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['chgpwd'] = false;

        if (function_exists('header_remove')) :
            header_remove();
        endif;

        $class::forcePasswordChange($GLOBALS['appConfig']);

        $headers = headers_list();
        $locationHeaders = array_filter($headers, function ($header) {
            return stripos($header, 'Location:') === 0;
        });

        $this->assertSame([], array_values($locationHeaders));
    }

    public function testForcePasswordChangeRedirectsWithInjectedHandlers()
    {
        $this->resetRequestState();
        $class = getRealSessionManagerClass();
        $_SESSION['chgpwd'] = true;
        $captured = [
            'location' => null,
            'terminated' => false
        ];

        $redirectHandler = function ($location) use (&$captured) {
            $captured['location'] = $location;
        };
        $terminateHandler = function () use (&$captured) {
            $captured['terminated'] = true;
        };

        $class::forcePasswordChange($GLOBALS['appConfig'], $redirectHandler, $terminateHandler);

        $this->assertSame('/profile.php', $captured['location']);
        $this->assertTrue($captured['terminated']);
    }
}
