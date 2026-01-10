<?php

namespace andkab\Turnstile {
    class Turnstile
    {
        public static $verifyResult;

        public function __construct($secret)
        {
        }

        public function verify($response, $remote)
        {
            return self::$verifyResult ?: new class {
                public function isSuccess()
                {
                    return true;
                }
                public function hasErrors()
                {
                    return false;
                }
                public $errorCodes = [];
            };
        }
    }
}

namespace MTG\Auth {
    if (!class_exists(PasswordCheck::class, false)) {
        class PasswordCheck
        {
            public static $result = 10;

            public function __construct($db = null, $logfile = null, $siteTitle = null)
            {
            }

            public function validatePassword($email, $password)
            {
                return self::$result;
            }
        }
    }

    if (!class_exists(TwoFactorManager::class, false)) {
        class TwoFactorManager
        {
            public static $enabled = false;
            public $verificationStarted = false;

            public function __construct($db = null, $smtpParameters = null, $serverEmail = null, $logfile = null)
            {
            }

            public function isEnabled($userId)
            {
                return self::$enabled;
            }

            public function startVerification($userId, $email)
            {
                $this->verificationStarted = true;
            }
        }
    }

    if (!class_exists(UserStatus::class, false)) {
        class UserStatus
        {
            public static $badLoginResult = ['count' => 0, 'code' => 1];
            public static $userStatusResult = ['code' => 10, 'number' => 1, 'admin' => 0];
            public $incremented = false;
            public $locked = false;
            public $zeroed = false;

            public function __construct($db = null, $logfile = null, $email = null)
            {
            }

            public function getBadLogin()
            {
                return self::$badLoginResult;
            }

            public function getUserStatus()
            {
                return self::$userStatusResult;
            }

            public function incrementBadLogin()
            {
                $this->incremented = true;
            }

            public function zeroBadLogin()
            {
                $this->zeroed = true;
            }

            public function triggerLocked()
            {
                $this->locked = true;
            }
        }
    }

    if (!class_exists(TrustedDeviceManager::class, false)) {
        class TrustedDeviceManager
        {
            public static $result = false;

            public function __construct($db = null, $logfile = null)
            {
            }

            public function validateTrustedDevice()
            {
                return self::$result;
            }
        }
    }
}

namespace {

    use MTG\Auth\LoginHandler;
    use MTG\Auth\PasswordCheck;
    use MTG\Auth\TrustedDeviceManager;
    use MTG\Auth\TwoFactorManager;
    use MTG\Auth\UserStatus;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/../src/MTG/Auth/LoginHandler.php';

    if (!class_exists(TerminateException::class)) {
        class TerminateException extends RuntimeException
        {
        }
    }

    class FakeResult
    {
        public $num_rows;
        private $rows;
        private $index = 0;

        public function __construct(array $rows)
        {
            $this->rows = array_values($rows);
            $this->num_rows = count($this->rows);
        }

        public function fetch_assoc()
        {
            if (!isset($this->rows[$this->index])) {
                return null;
            }
            return $this->rows[$this->index++];
        }
    }

    class FakeStatement
    {
        public $num_rows;
        private $rows;
        private $boundVars;

        public function __construct(array $rows)
        {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function bind_param($types, &$param)
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
            $this->boundVars = &$vars;
            return true;
        }

        public function fetch()
        {
            if (empty($this->rows)) {
                return false;
            }
            $row = $this->rows[0];
            $i = 0;
            foreach ($this->boundVars as &$var) {
                $keys = array_keys($row);
                $var = $row[$keys[$i]] ?? null;
                $i++;
            }
            return true;
        }

        public function close()
        {
            return true;
        }
    }

    class FakeDb
    {
        public $executeQueue = [];
        public $lastQueries = [];
        private $preparedStatement;

        public function setPreparedStatement($stmt)
        {
            $this->preparedStatement = $stmt;
        }

        public function prepare($query)
        {
            $this->lastQueries[] = $query;
            return $this->preparedStatement;
        }

        public function execute_query($query, $params = [])
        {
            $this->lastQueries[] = $query;
            return array_shift($this->executeQueue);
        }
    }

    class LoginHandlerTest extends TestCase
    {
        private $obLevel = 0;

        protected function setUp(): void
        {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            $this->obLevel = ob_get_level();
            ob_start();
            session_save_path(sys_get_temp_dir());
            session_id(bin2hex(random_bytes(8)));
            $_SESSION = [];
            session_start();
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }

        protected function tearDown(): void
        {
            \andkab\Turnstile\Turnstile::$verifyResult = null;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            while (ob_get_level() > $this->obLevel) {
                ob_end_clean();
            }
        }

        private function buildHandler(FakeDb $db, ?callable $terminator = null, int $turnstileEnabled = 0): LoginHandler
        {
            global $logfile, $smtpParameters, $serverEmail, $Badloglimit, $turnstile, $turnstile_secret_key, $siteTitle;
            $logfile = sys_get_temp_dir() . '/mtg_loginhandler_test.log';
            $smtpParameters = [];
            $serverEmail = 'server@example.com';
            $Badloglimit = 3;
            $turnstile = $turnstileEnabled;
            $turnstile_secret_key = '';
            $siteTitle = 'MTG Test';

            return new LoginHandler(
                $db,
                $logfile,
                $turnstile,
                $turnstile_secret_key,
                $Badloglimit,
                $siteTitle,
                $smtpParameters,
                $serverEmail,
                $terminator
            );
        }

        public function testTrustedDeviceLoginReturnsRedirect()
        {
            $db = new FakeDb();
            $GLOBALS['db'] = $db;
            $db->setPreparedStatement(new FakeStatement([
            ['usernumber' => 7, 'username' => 'tester', 'email' => 'user@example.com', 'admin' => 1]
            ]));
            $db->executeQueue = [true];
            TrustedDeviceManager::$result = 7;

            $handler = $this->buildHandler($db);
            $result = $handler->attemptTrustedDeviceLogin('dashboard.php');

            $this->assertTrue($result['trusted_login']);
            $this->assertEquals('dashboard.php', $result['redirect']);
            $this->assertTrue($_SESSION['logged']);
            $this->assertEquals(7, $_SESSION['user']);
            $this->assertEquals('user@example.com', $_SESSION['useremail']);
            $this->assertTrue($_SESSION['admin']);
        }

        public function testTrustedDeviceLoginWithoutToken()
        {
            $db = new FakeDb();
            $GLOBALS['db'] = $db;
            TrustedDeviceManager::$result = false;

            $handler = $this->buildHandler($db);
            $result = $handler->attemptTrustedDeviceLogin(null);

            $this->assertFalse($result['trusted_login']);
            $this->assertNull($result['redirect']);
            $this->assertArrayNotHasKey('logged', $_SESSION);
        }

        public function testProcessLoginSubmissionReturnsNullWhenNotSubmitted()
        {
            $db = new FakeDb();
            $handler = $this->buildHandler($db);

            $result = $handler->processLoginSubmission([]);

            $this->assertNull($result);
        }

        public function testProcessLoginSubmissionSuccessWithout2FA()
        {
            $db = new FakeDb();
            UserStatus::$badLoginResult = ['count' => 0, 'code' => 1];
            UserStatus::$userStatusResult = ['code' => 10, 'number' => 42, 'admin' => 0];
            PasswordCheck::$result = 10;
            TwoFactorManager::$enabled = false;

            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            });

            $post = [
            'ac' => 'log',
            'email' => 'user@example.com',
            'password' => 'secret'
            ];

            $result = $handler->processLoginSubmission($post);

            $this->assertIsArray($result);
            $this->assertTrue($_SESSION['logged']);
            $this->assertEquals(42, $_SESSION['user']);
            $this->assertEquals('user@example.com', $_SESSION['useremail']);
            $this->assertArrayNotHasKey('user_pending_2fa', $_SESSION);
        }

        public function testProcessLoginSubmissionInvalidEmailTriggersAbort()
        {
            $db = new FakeDb();
            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            });

            $this->expectException(TerminateException::class);

            $post = [
            'ac' => 'log',
            'email' => 'not-an-email',
            'password' => 'secret'
            ];

            $handler->processLoginSubmission($post);
        }

        public function testProcessLoginSubmissionLockedAccountTriggersAbort()
        {
            $db = new FakeDb();

            // Simulate a user who is already locked
            UserStatus::$badLoginResult    = ['count' => 5];
            UserStatus::$userStatusResult  = [
                'code'   => 2,   // locked
                'number' => 123,
                'admin'  => 0,
            ];
            PasswordCheck::$result = 10; // could be 10 or something else; it won't matter

            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            });

            $this->expectException(TerminateException::class);

            $post = [
                'ac'       => 'log',
                'email'    => 'user@example.com',
                'password' => 'secret',
            ];

            $handler->processLoginSubmission($post);
        }

        public function testTurnstileFailureTriggersAbort()
        {
            $db = new FakeDb();
            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            }, 1);

            \andkab\Turnstile\Turnstile::$verifyResult = new class {
                public $errorCodes = ['timeout-or-duplicate'];
                public function isSuccess()
                {
                    return false;
                }
                public function hasErrors()
                {
                    return true;
                }
            };

            $this->expectException(TerminateException::class);

            $handler->handleTurnstileCheck(
                ['cf-turnstile-response' => 'token'],
                '127.0.0.1'
            );
        }

        public function testCompleteLoginRedirectsToTrustDevice()
        {
            $db = new FakeDb();
            $db->executeQueue = [
            true, // loginstamp update
            new FakeResult([['mtce' => 0]]) // mtcemode check
            ];
            $GLOBALS['db'] = $db;

            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            });

            $_SESSION['logged'] = true;
            $loginData = [
            'email' => 'user@example.com',
            'usernumber' => 99,
            'userstat_result' => ['admin' => 0, 'code' => 10]
            ];

            $this->expectException(TerminateException::class);
            $handler->completeLogin($loginData, 'next.php');
        }
    }
}
