<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

function getRealTrustedDeviceManagerClass(): string
{
    if (class_exists('TrustedDeviceManagerReal', false)) :
        return 'TrustedDeviceManagerReal';
    endif;

    // Load the production class under an alias to avoid collisions with other test doubles.
    if (!isset($_SERVER['PHP_SELF'])) {
        $_SERVER['PHP_SELF'] = __FILE__;
    }
    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/TrustedDeviceManager.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+TrustedDeviceManager/', 'class TrustedDeviceManagerReal', $source, 1);

    eval($source);

    return 'TrustedDeviceManagerReal';
}

class TrustedDeviceResultStub
{
    private $rows;
    private $position = 0;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function fetch_assoc()
    {
        if ($this->position < count($this->rows)) :
            return $this->rows[$this->position++];
        endif;

        return null;
    }
}

class TrustedDeviceStmtStub
{
    public $num_rows;
    public $error = '';
    public $affected_rows;
    public $boundParams = [];
    public $executed = false;
    private $executeReturn;
    private $resultRows;
    private $boundResultRefs = [];

    public function __construct($executeReturn = true, array $resultRows = [], $affectedRows = 0)
    {
        $this->executeReturn = $executeReturn;
        $this->resultRows = $resultRows;
        $this->num_rows = count($resultRows);
        $this->affected_rows = $affectedRows;
    }

    public function bind_param($types, &...$vars)
    {
        $this->boundParams = $vars;
    }

    public function execute()
    {
        $this->executed = true;
        return $this->executeReturn;
    }

    public function store_result()
    {
    }

    public function bind_result(&...$vars)
    {
        $this->boundResultRefs = &$vars;
    }

    public function fetch()
    {
        if (!empty($this->resultRows)) :
            $row = $this->resultRows[0];
            foreach ($this->boundResultRefs as $idx => &$var) :
                $var = $row[$idx] ?? null;
            endforeach;
            return true;
        endif;

        return false;
    }

    public function get_result()
    {
        return new TrustedDeviceResultStub($this->resultRows);
    }

    public function close()
    {
    }
}

class TrustedDeviceMysqliStub
{
    private $preparedStatements;
    private $queryResult;
    public $error = '';
    public $affected_rows;

    public function __construct(array $preparedStatements = [], $queryResult = true, $affectedRows = 0)
    {
        $this->preparedStatements = $preparedStatements;
        $this->queryResult = $queryResult;
        $this->affected_rows = $affectedRows;
    }

    public function prepare($query)
    {
        if (empty($this->preparedStatements)) :
            $this->error = 'No prepared statements available';
            return false;
        endif;

        return array_shift($this->preparedStatements);
    }

    public function query($query)
    {
        return $this->queryResult;
    }
}

class TrustedDeviceManagerTest extends TestCase
{
    private $originalServer;
    private $originalCookie;
    private $originalSecret;
    private $appConfig;
    private $managerClass;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalCookie = $_COOKIE;
        $this->originalSecret = getenv('HMAC_SECRET');
        $this->appConfig = $GLOBALS['appConfig'] ?? null;

        if ($this->appConfig === null) :
            $this->markTestSkipped('AppConfig not available for TrustedDeviceManager tests.');
        endif;

        $_COOKIE = [];
        $_SERVER = [];
        putenv('HMAC_SECRET=test_secret');

        $this->managerClass = getRealTrustedDeviceManagerClass();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_COOKIE = $this->originalCookie;

        if ($this->originalSecret === false) :
            putenv('HMAC_SECRET');
        else :
            putenv('HMAC_SECRET=' . $this->originalSecret);
        endif;
    }

    private function makeManager(TrustedDeviceMysqliStub $db)
    {
        $class = $this->managerClass;
        return new $class($db, $this->appConfig);
    }

    public function testGetCookieNameReturnsConfiguredName()
    {
        $db = new TrustedDeviceMysqliStub();
        $manager = $this->makeManager($db);

        $this->assertSame('mtgc_trusted_device', $manager->getCookieName());
    }

    public function testGetTokenHashUsesHmacSecret()
    {
        $db = new TrustedDeviceMysqliStub();
        $manager = $this->makeManager($db);

        $this->assertSame(
            hash_hmac('sha256', 'token', 'test_secret'),
            $manager->getTokenHash('token')
        );
    }

    public function testCreateTrustedDeviceStoresRecord()
    {
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('A', 260);

        $insertStmt = new TrustedDeviceStmtStub();
        $db = new TrustedDeviceMysqliStub([$insertStmt]);

        $manager = $this->makeManager($db);
        $result = $manager->createTrustedDevice(5, 2);

        $this->assertTrue($result);
        $this->assertCount(6, $insertStmt->boundParams);
        $this->assertSame(5, $insertStmt->boundParams[0]);
        $this->assertSame(64, strlen($insertStmt->boundParams[1]));
        $this->assertSame(str_repeat('A', 255), $insertStmt->boundParams[2]);
    }

    public function testValidateTrustedDeviceReturnsUserId()
    {
        $_COOKIE['mtgc_trusted_device'] = 'abc123';

        $selectStmt = new TrustedDeviceStmtStub(true, [[12, 44]]);
        $updateStmt = new TrustedDeviceStmtStub();
        $db = new TrustedDeviceMysqliStub([$selectStmt, $updateStmt]);

        $manager = $this->makeManager($db);
        $userId = $manager->validateTrustedDevice();

        $this->assertSame($manager->getTokenHash('abc123'), $selectStmt->boundParams[0]);
        $this->assertSame(44, $userId);
        $this->assertTrue($updateStmt->executed);
    }

    public function testRemoveTrustedDeviceDeletesRecord()
    {
        $_COOKIE['mtgc_trusted_device'] = 'xyz';

        $deleteStmt = new TrustedDeviceStmtStub();
        $db = new TrustedDeviceMysqliStub([$deleteStmt]);

        $manager = $this->makeManager($db);
        $result = $manager->removeTrustedDevice();

        $this->assertTrue($result);
        $this->assertSame($manager->getTokenHash('xyz'), $deleteStmt->boundParams[0]);
    }

    public function testGetUserDevicesReturnsRows()
    {
        $rows = [
            [
                'id' => 1,
                'device_name' => 'Phone',
                'token_hash' => 'hash',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'agent',
                'last_used' => '2025-01-01',
                'created' => '2025-01-01',
                'expires' => '2025-02-01'
            ],
            [
                'id' => 2,
                'device_name' => 'Laptop',
                'token_hash' => 'hash2',
                'ip_address' => '10.0.0.1',
                'user_agent' => 'agent2',
                'last_used' => '2025-01-02',
                'created' => '2025-01-02',
                'expires' => '2025-02-02'
            ],
        ];

        $selectStmt = new TrustedDeviceStmtStub(true, $rows);
        $db = new TrustedDeviceMysqliStub([$selectStmt]);

        $manager = $this->makeManager($db);
        $devices = $manager->getUserDevices(9);

        $this->assertSame(9, $selectStmt->boundParams[0]);
        $this->assertSame($rows, $devices);
    }

    public function testRemoveDeviceByIdReportsAffectedRows()
    {
        $deleteStmt = new TrustedDeviceStmtStub(true, [], 1);
        $db = new TrustedDeviceMysqliStub([$deleteStmt]);

        $manager = $this->makeManager($db);
        $result = $manager->removeDeviceById(3, 7);

        $this->assertTrue($result);
        $this->assertSame([3, 7], $deleteStmt->boundParams);
    }

    public function testCleanupExpiredTokensUsesAffectedRows()
    {
        $db = new TrustedDeviceMysqliStub([], true, 4);

        $manager = $this->makeManager($db);
        $this->assertSame(4, $manager->cleanupExpiredTokens());
    }
}
