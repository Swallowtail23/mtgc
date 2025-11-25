<?php

if (!class_exists('INI')) {
    class INI
    {
        public $data;

        public function __construct()
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

if (!class_exists('SessionManager')) {
    class SessionManager
    {
        public function __construct()
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
    }
}

if (!class_exists('PriceManager')) {
    class PriceManager
    {
        public function __construct()
        {
        }

        public function updateCollectionValues()
        {
        }
    }
}

if (!class_exists('ImageManager')) {
    class ImageManager
    {
        public function __construct()
        {
        }

        public function getImage()
        {
            return ['front' => '/cardimg/back.jpg'];
        }
    }
}
