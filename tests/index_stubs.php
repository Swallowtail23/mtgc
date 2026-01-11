<?php

namespace MTG\Core {
    if (!class_exists(INI::class, false)) {
        class INI
        {
            public $data;

            public function __construct($file = null, $sections = true)
            {
                $this->data = [
                    'general' => [
                        'URL' => 'http://localhost',
                        'title' => 'MTG Collection',
                        'tier' => 'dev',
                        'Loglevel' => 0,
                        'ImgLocation' => '/cardimg/',
                        'Timezone' => 'UTC',
                        'Locale' => 'en_US',
                        'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
                        'Copyright' => 'Test Copyright'
                    ],
                    'fx' => [
                        'FreecurrencyAPI' => '',
                        'TargetCurrency' => 'USD'
                    ],
                    'security' => [
                        'Turnstile' => 'disabled',
                        'Turnstile_site_key' => '',
                        'Turnstile_secret_key' => '',
                        'AdminIP' => '',
                        'Badloginlimit' => 3,
                        'TrustDuration' => 30
                    ],
                    'comments' => [
                        'Disqus' => 'disabled',
                        'DisqusDevURL' => '',
                        'DisqusProdURL' => ''
                    ],
                    'database' => [
                        'DBServer' => 'localhost',
                        'DBUser' => 'user',
                        'DBPass' => 'pass',
                        'DBName' => 'db'
                    ],
                    'email' => [
                        'SMTPDebug' => 0,
                        'Host' => '',
                        'SMTPAuth' => false,
                        'Username' => '',
                        'Password' => '',
                        'SMTPSecure' => '',
                        'Port' => 0,
                        'AdminEmail' => 'admin@example.com',
                        'ServerEmail' => 'server@example.com'
                    ]
                ];
            }
        }
    }
}

namespace MTG\Auth {
    if (!class_exists(SessionManager::class, false)) {
        class SessionManager
        {
            public function __construct(...$args)
            {
            }

            public function getUserInfo()
            {
                return [
                    'usernumber' => 'user1',
                    'username' => 'Test User',
                    'table' => 'collectionTemplate',
                    'collection_view' => false,
                    'admin' => false,
                    'grpinout' => '',
                    'groupid' => '',
                    'fx' => '',
                    'currency' => 'USD',
                    'rate' => 1
                ];
            }

            public static function forcePasswordChange($appConfig = null)
            {
                return;
            }

            public static function generateCsrfToken()
            {
                if (!isset($_SESSION['csrf_token'])) :
                    $_SESSION['csrf_token'] = 'test-csrf-token';
                endif;

                return $_SESSION['csrf_token'];
            }

            public static function validateCsrfToken($submittedToken)
            {
                return is_string($submittedToken) && isset($_SESSION['csrf_token'])
                    && hash_equals($_SESSION['csrf_token'], $submittedToken);
            }

            public static function validateAjaxRequest(
                $expectedReferringPages,
                $appConfig,
                $context = '',
                $requireCsrf = true
            ) {
                return [
                    'valid' => true,
                    'reason' => ''
                ];
            }
        }
    }
}

namespace MTG\Cards {
    if (!class_exists(PriceManager::class, false)) {
        class PriceManager
        {
            public function __construct(...$args)
            {
            }

            public function updateCollectionValues()
            {
            }
        }
    }

    if (!class_exists(ImageManager::class, false)) {
        class ImageManager
        {
            public function __construct(...$args)
            {
            }

            public function getImage(
                $setcode = null,
                $cardId = null,
                $layout = null,
                $allowFetch = true
            ) {
                return ['front' => '/cardimg/back.jpg'];
            }
        }
    }
}
