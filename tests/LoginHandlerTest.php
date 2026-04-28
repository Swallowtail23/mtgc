<?php

/*
Version:     1.0
Date:        28/04/26
Name:        LoginHandlerTest.php
Purpose:     Tests login handler authentication flows.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace andkab\Turnstile {
    class Turnstile
    {
        public static mixed $verifyResult = null;

        public function __construct(?string $secret)
        {
            unset($secret);
        }

        public function verify(?string $response, ?string $remote): object
        {
            unset($response, $remote);
            return self::$verifyResult ?: new class {
                public function isSuccess(): bool
                {
                    return true;
                }
                public function hasErrors(): bool
                {
                    return false;
                }
                public array $errorCodes = [];
            };
        }
    }
}

namespace MTG\Auth {
    if (!class_exists(PasswordCheck::class, false)) {
        class PasswordCheck
        {
            public static int $result = 10;

            public function __construct(mixed $db = null, mixed $appConfig = null)
            {
                unset($db, $appConfig);
            }

            public function validatePassword(string $email, string $password): int
            {
                unset($email, $password);
                return self::$result;
            }
        }
    }

    if (!class_exists(TwoFactorManager::class, false)) {
        class TwoFactorManager
        {
            public static bool $enabled = false;
            public bool $verificationStarted = false;

            public function __construct(mixed $db = null, mixed $appConfig = null)
            {
                unset($db, $appConfig);
            }

            public function isEnabled(int $userId): bool
            {
                unset($userId);
                return self::$enabled;
            }

            public function startVerification(int $userId, string $email): void
            {
                unset($userId, $email);
                $this->verificationStarted = true;
            }
        }
    }

    if (!class_exists(UserStatus::class, false)) {
        class UserStatus
        {
            public static array $badLoginResult = ['count' => 0, 'code' => 1];
            public static array $userStatusResult = ['code' => 10, 'number' => 1, 'admin' => 0];
            public bool $incremented = false;
            public bool $locked = false;
            public bool $zeroed = false;

            public function __construct(mixed $db = null, mixed $appConfig = null, ?string $email = null)
            {
                unset($db, $appConfig, $email);
            }

            public function getBadLogin(): array
            {
                return self::$badLoginResult;
            }

            public function getUserStatus(): array
            {
                return self::$userStatusResult;
            }

            public function incrementBadLogin(): void
            {
                $this->incremented = true;
            }

            public function zeroBadLogin(): void
            {
                $this->zeroed = true;
            }

            public function triggerLocked(): void
            {
                $this->locked = true;
            }
        }
    }

    if (!class_exists(TrustedDeviceManager::class, false)) {
        class TrustedDeviceManager
        {
            public static bool $result = false;

            public function __construct(mixed $db = null, mixed $appConfig = null)
            {
                unset($db, $appConfig);
            }

            public function validateTrustedDevice(): bool
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
    use MTG\Core\AppConfig;
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
        public int $num_rows;
        private array $rows;
        private int $index = 0;

        public function __construct(array $rows)
        {
            $this->rows = array_values($rows);
            $this->num_rows = count($this->rows);
        }

        public function fetch_assoc(): ?array
        {
            if (!isset($this->rows[$this->index])) {
                return null;
            }
            return $this->rows[$this->index++];
        }
    }

    class FakeStatement
    {
        public int $num_rows;
        private array $rows;
        private array $boundVars = [];

        public function __construct(array $rows)
        {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function bind_param(string $types, mixed &$param): bool
        {
            unset($types, $param);
            return true;
        }

        public function execute(): bool
        {
            return true;
        }

        public function store_result(): bool
        {
            return true;
        }

        public function bind_result(mixed &...$vars): bool
        {
            $this->boundVars = &$vars;
            return true;
        }

        public function fetch(): bool
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

        public function close(): bool
        {
            return true;
        }
    }

    class FakeDb
    {
        public array $executeQueue = [];
        public array $lastQueries = [];
        private ?FakeStatement $preparedStatement = null;

        public function setPreparedStatement(FakeStatement $stmt): void
        {
            $this->preparedStatement = $stmt;
        }

        public function prepare(string $query): ?FakeStatement
        {
            $this->lastQueries[] = $query;
            return $this->preparedStatement;
        }

        public function execute_query(string $query, array $params = []): mixed
        {
            unset($params);
            $this->lastQueries[] = $query;
            return array_shift($this->executeQueue);
        }
    }

    class LoginHandlerTest extends TestCase
    {
        private int $obLevel = 0;

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
            $logfile = sys_get_temp_dir() . '/mtg_loginhandler_test.log';
            $serverEmail = 'server@example.com';
            $Badloglimit = 3;
            $siteTitle = 'MTG Test';
            $adminEmail = 'admin@example.com';
            $smtpParameters = [
                'SMTPDebug' => 'SMTP::DEBUG_OFF',
                'SMTPHost' => 'localhost',
                'SMTPAuth' => '',
                'SMTPUsername' => '',
                'SMTPPassword' => '',
                'SMTPSecure' => '',
                'SMTPPort' => 25,
                'SMTPHelo' => 'localhost',
                'SMTPVerifySSL' => 1,
                'globalDebug' => 0
            ];
            $iniArray = [
                'general' => [
                    'URL' => 'https://test.example',
                    'title' => $siteTitle,
                    'tier' => 'dev',
                    'Loglevel' => 0,
                    'Logfile' => $logfile,
                    'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
                    'Timezone' => 'UTC',
                    'Locale' => 'en_US',
                    'Copyright' => ''
                ],
                'security' => [
                    'Turnstile' => $turnstileEnabled === 1 ? 'enabled' : 'disabled',
                    'Turnstile_site_key' => '',
                    'Turnstile_secret_key' => '',
                    'TrustDuration' => 0,
                    'Badloginlimit' => $Badloglimit,
                    'AdminIP' => ''
                ],
                'email' => [
                    'Email' => 'enabled',
                    'AdminEmail' => $adminEmail,
                    'ServerEmail' => $serverEmail,
                    'SMTPDebug' => $smtpParameters['SMTPDebug'],
                    'Host' => $smtpParameters['SMTPHost'],
                    'SMTPAuth' => $smtpParameters['SMTPAuth'],
                    'Username' => $smtpParameters['SMTPUsername'],
                    'Password' => $smtpParameters['SMTPPassword'],
                    'SMTPSecure' => $smtpParameters['SMTPSecure'],
                    'Port' => $smtpParameters['SMTPPort'],
                    'SMTPHelo' => $smtpParameters['SMTPHelo'],
                    'SMTPVerifySSL' => $smtpParameters['SMTPVerifySSL']
                ],
                'fx' => [
                    'FreecurrencyAPI' => '',
                    'TargetCurrency' => ''
                ],
                'comments' => [
                    'Disqus' => 'disabled',
                    'DisqusDevURL' => '',
                    'DisqusProdURL' => ''
                ],
            ];
            $appConfig = AppConfig::fromIni($iniArray, [
                'general' => [
                    'logLevel' => 0,
                    'logFile' => $logfile,
                ],
                'email' => [
                    'enabled' => true,
                    'adminEmail' => $adminEmail,
                    'serverEmail' => $serverEmail,
                    'smtp' => $smtpParameters,
                ],
            ]);

            $GLOBALS['logfile'] = $logfile;

            return new LoginHandler($db, $appConfig, $terminator);
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
                public array $errorCodes = ['timeout-or-duplicate'];

                public function isSuccess(): bool
                {
                    return false;
                }

                public function hasErrors(): bool
                {
                    return true;
                }
            };

            $this->expectException(TerminateException::class);

            $handler->handleTurnstileCheck(
                [
                    'ac' => 'log',
                    'email' => 'user@example.com',
                    'password' => 'secret',
                    'cf-turnstile-response' => 'token'
                ],
                '127.0.0.1'
            );
        }

        public function testTurnstileMissingResponseTriggersAbortOnLoginSubmission()
        {
            $db = new FakeDb();
            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            }, 1);

            $this->expectException(TerminateException::class);

            $handler->handleTurnstileCheck(
                [
                    'ac' => 'log',
                    'email' => 'user@example.com',
                    'password' => 'secret'
                ],
                '127.0.0.1'
            );
        }

        public function testTurnstileMissingResponseAllowsNonLoginPageLoad()
        {
            $db = new FakeDb();
            $handler = $this->buildHandler($db, function () {
                throw new TerminateException();
            }, 1);

            $handler->handleTurnstileCheck([], '127.0.0.1');

            $this->assertSame([], $_SESSION);
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
