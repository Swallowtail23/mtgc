<?php

/*
Version:     1.1
Date:        28/04/26
Name:        index_stubs.php
Purpose:     Provides stub classes for index tests.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core {
    if (!class_exists(INI::class, false)) {
        class INI
        {
            public array $data;

            public function __construct(?string $file = null, bool $sections = true)
            {
                unset($file, $sections);
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
            public function __construct(mixed ...$args)
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

            public static function forcePasswordChange(mixed $appConfig = null): void
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

            public static function validateCsrfToken(mixed $submittedToken): bool
            {
                return is_string($submittedToken) && isset($_SESSION['csrf_token'])
                    && hash_equals($_SESSION['csrf_token'], $submittedToken);
            }

            public static function validateAjaxRequest(
                mixed $expectedReferringPages,
                mixed $appConfig,
                mixed $context = '',
                bool $requireCsrf = true
            ): array {
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
            public function __construct(mixed ...$args)
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
            public function __construct(mixed ...$args)
            {
            }

            public function getImage(
                ?string $setcode = null,
                ?string $cardId = null,
                ?string $layout = null,
                bool $allowFetch = true
            ): array {
                return ['front' => '/cardimg/back.jpg'];
            }
        }
    }
}
