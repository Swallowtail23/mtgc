<?php
/* Version:     1.0
    Date:       13/06/25
    Name:       configdefaults.php
    Purpose:    PHP script to carry config defaults
    Notes:      {none}
 *
    1.0         Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$defaults = [
    'general' => [
        'title'       => 'MtG collection',
        'tier'        => 'prod',                      // either 'dev' or 'prod'
        'ImgLocation' => '/mnt/data/cardimg/',        // ensure web server can write here
        'Logfile'     => '/var/log/mtg/mtgapp.log',   // ensure web server can write here
        'Loglevel'    => 3,                           // see admin pages
        'Timezone'    => 'Australia/Brisbane',
        'Locale'      => 'en_US',
        'Copyright'   => 'Simon Wilson - 2025',
        'URL'         => 'http://localhost:8080',     // update to your actual URL
    ],

    'database' => [
        'DBServer' => 'db',
        'DBUser'   => 'mtg',
        'DBPass'   => 'mtgpass',
        'DBName'   => 'mtg',
    ],

    'security' => [
        'AdminIP'              => '',
        'Badloginlimit'        => 5,
        'Turnstile'            => 'disabled',
        'Turnstile_site_key'   => 'xxxxxx',
        'Turnstile_secret_key' => 'xxxxxx',
        'TrustDuration'        => 7,                // days
    ],

    'fx' => [
        'FreecurrencyAPI' => 'disabled',            // If null or disabled, fx conversion is disabled
        'FreecurrencyURL' => 'https://api.freecurrencyapi.com/v1/latest?apikey=',
        'TargetCurrency'  => 'aud',
    ],

    'email' => [
        'Enabled'        => 'false',
        'ServerEmail'    => 'no_reply@your-mtg-site-url.com',
        'AdminEmail'     => 'youremail@youremail.com',
        'SMTPDebug'      => SMTP::DEBUG_OFF,
        'Host'           => 'localhost',
        'SMTPAuth'       => false,
        'Username'       => '',
        'Password'       => '',
        'SMTPSecure'     => PHPMailer::ENCRYPTION_SMTPS,
        'Port'           => 25,
    ],

    'comments' => [
        'Disqus'        => 'disabled',
        'DisqusDevURL'  => 'https://dev-url-here.disqus.com',
        'DisqusProdURL' => 'https://prod-url-here.disqus.com',
    ],
];
