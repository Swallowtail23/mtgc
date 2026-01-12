<?php

/*
Version:     1.52
Date:        12/01/26
Name:        AppContext.php
Purpose:     Bootstrap context container for app-wide dependencies.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core;

class AppContext
{
    /**
    * @var \mysqli
    */
    private $db;
    /**
    * @var AppConfig
    */
    private $config;
    /**
    * @var GameRules
    */
    private $rules;
    /**
    * @var array<string,mixed>
    */
    private $iniArray = [];
    /**
    * @var Message
    */
    private $message;

    public function __construct($db, AppConfig $config, GameRules $rules, array $iniArray, Message $message)
    {
        $this->db = $db;
        $this->config = $config;
        $this->rules = $rules;
        $this->iniArray = $iniArray;
        $this->message = $message;
    }

    public static function fromIniPath(string $iniPath, ?\mysqli $dbOverride = null): self
    {
        $ini = new INI($iniPath);
        $iniArray = is_array($ini->data) ? $ini->data : [];

        $tier = self::normalizeTier($iniArray['general']['tier'] ?? 'prod');
        if ($tier === 'dev') :
            $turnstileSiteKey = '1x00000000000000000000AA';
            $turnstileSecretKey = '1x0000000000000000000000000000000AA';
        else :
            $turnstileSiteKey = $iniArray['security']['Turnstile_site_key'] ?? '';
            $turnstileSecretKey = $iniArray['security']['Turnstile_secret_key'] ?? '';
        endif;
        $turnstileEnabled = ($iniArray['security']['Turnstile'] ?? '') === 'enabled';

        $adminIp = $iniArray['security']['AdminIP'] ?? '';
        if ($adminIp === '') :
            $adminIp = 1;
        endif;

        $emailEnabled = (($iniArray['email']['Email'] ?? 'enabled') === 'enabled');

        $disqusEnabled = ($iniArray['comments']['Disqus'] ?? '') === 'enabled';
        $disqusDev = $disqusEnabled ? ($iniArray['comments']['DisqusDevURL'] ?? '') : '';
        $disqusProd = $disqusEnabled ? ($iniArray['comments']['DisqusProdURL'] ?? '') : '';

        $logLevelIni = $iniArray['general']['Loglevel'] ?? '';
        $logFile = $iniArray['general']['Logfile'] ?? '';
        $smtpParameters = [
            'SMTPDebug' => $iniArray['email']['SMTPDebug'] ?? '',
            'SMTPHost' => $iniArray['email']['Host'] ?? '',
            'SMTPAuth' => $iniArray['email']['SMTPAuth'] ?? '',
            'SMTPUsername' => $iniArray['email']['Username'] ?? '',
            'SMTPPassword' => $iniArray['email']['Password'] ?? '',
            'SMTPSecure' => $iniArray['email']['SMTPSecure'] ?? '',
            'SMTPPort' => $iniArray['email']['Port'] ?? 0,
            'SMTPHelo' => $iniArray['email']['SMTPHelo'] ?? gethostname(),
            'SMTPVerifySSL' => $iniArray['email']['SMTPVerifySSL'] ?? 1,
            'globalDebug' => $logLevelIni,
        ];

        $rulesPath = APP_ROOT . '/includes/game_rules.php';
        $rules = GameRules::fromFile($rulesPath);
        $rulesData = $rules->all();
        $maxCardDataAge = $rulesData['max_card_data_age'] ?? 0;

        $config = AppConfig::fromIni($iniArray, [
            'general' => [
                'tier' => $tier,
                'logLevel' => $logLevelIni,
                'logFile' => $logFile,
                'maxCardDataAge' => $maxCardDataAge,
            ],
            'security' => [
                'turnstileEnabled' => $turnstileEnabled,
                'turnstileSiteKey' => $turnstileSiteKey,
                'turnstileSecretKey' => $turnstileSecretKey,
                'adminIp' => $adminIp,
            ],
            'email' => [
                'enabled' => $emailEnabled,
                'adminEmail' => $iniArray['email']['AdminEmail'] ?? '',
                'serverEmail' => $iniArray['email']['ServerEmail'] ?? '',
                'smtp' => $smtpParameters,
            ],
            'comments' => [
                'disqusEnabled' => $disqusEnabled,
                'disqusDevUrl' => $disqusDev,
                'disqusProdUrl' => $disqusProd,
            ],
        ]);
        $message = new Message($config);

        if ($dbOverride instanceof \mysqli) :
            $db = $dbOverride;
        else :
            $dbHost = (string) $config->database('host', '');
            $dbUser = (string) $config->database('user', '');
            $dbPass = (string) $config->database('pass', '');
            $dbName = (string) $config->database('name', '');
            $db = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);
            if ($db->connect_error) :
                throw new \RuntimeException(
                    'Failed to connect to MySQL Database Error Info : ' . $db->connect_error
                );
            endif;
        endif;
        if (method_exists($db, 'set_charset')) :
            $db->set_charset('utf8mb4');
        endif;

        return new self($db, $config, $rules, $iniArray, $message);
    }

    public function db(): \mysqli
    {
        return $this->db;
    }

    public function config(): AppConfig
    {
        return $this->config;
    }

    public function rules(): GameRules
    {
        return $this->rules;
    }

    public function iniArray(): array
    {
        return $this->iniArray;
    }

    public function message(): Message
    {
        return $this->message;
    }

    private static function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));
        if ($tier === 'dev') :
            return 'dev';
        endif;
        if ($tier === 'prod') :
            return 'prod';
        endif;
        return 'prod';
    }
}
