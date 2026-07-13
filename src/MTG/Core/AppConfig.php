<?php

/*
Version:     1.6
Date:        13/07/26
Name:        AppConfig.php
Purpose:     App-wide config container built from ini values.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core;

class AppConfig
{
    private array $general = [];
    private array $security = [];
    private array $email = [];
    private array $fx = [];
    private array $comments = [];
    private array $database = [];

    /**
     * Build AppConfig from ini array with optional overrides.
     *
     * Expected override shape:
     * - general: array (url, title, tier, logLevel, logFile, imageBaseDir, timezone, locale, copyright)
     * - security: array (turnstileEnabled, turnstileSiteKey, turnstileSecretKey, trustDuration, badLoginLimit, adminIp)
     * - email: array (enabled, adminEmail, serverEmail, senderEmail, smtp => array(...))
     * - fx: array (api, local, url)
     * - comments: array (disqusEnabled, disqusDevUrl, disqusProdUrl)
     * - database: array (host, user, pass, name)
     */
    public static function fromIni(array $iniArray, array $overrides = []): self
    {
        $config = new self();

        $tier = self::normalizeTier($iniArray['general']['tier'] ?? 'prod');
        $trustDuration = self::normalizeInt($iniArray['security']['TrustDuration'] ?? 0);
        $badLoginLimit = self::normalizeInt($iniArray['security']['Badloginlimit'] ?? 0);
        $smtpPort = self::normalizeInt($iniArray['email']['Port'] ?? 0);
        $smtpVerifySsl = self::normalizeBool($iniArray['email']['SMTPVerifySSL'] ?? 1, true);

        $config->general = [
            'url' => $iniArray['general']['URL'] ?? '',
            'title' => $iniArray['general']['title'] ?? '',
            'tier' => $tier,
            'logLevel' => $iniArray['general']['Loglevel'] ?? '',
            'logFile' => $iniArray['general']['Logfile'] ?? '',
            'imageBaseDir' => $iniArray['general']['ImgLocation'] ?? '',
            'timezone' => $iniArray['general']['Timezone'] ?? '',
            'locale' => $iniArray['general']['Locale'] ?? '',
            'copyright' => $iniArray['general']['Copyright'] ?? '',
            'maxCardDataAge' => $iniArray['general']['MaxCardDataAge'] ?? 0,
        ];

        $config->security = [
            'turnstileEnabled' => ($iniArray['security']['Turnstile'] ?? '') === 'enabled',
            'turnstileSiteKey' => $iniArray['security']['Turnstile_site_key'] ?? '',
            'turnstileSecretKey' => $iniArray['security']['Turnstile_secret_key'] ?? '',
            'trustDuration' => $trustDuration,
            'badLoginLimit' => $badLoginLimit,
            'adminIp' => $iniArray['security']['AdminIP'] ?? '',
        ];

        $config->email = [
            'enabled' => ($iniArray['email']['Email'] ?? 'enabled') === 'enabled',
            'adminEmail' => $iniArray['email']['AdminEmail'] ?? '',
            'serverEmail' => $iniArray['email']['ServerEmail'] ?? '',
            'senderEmail' => $iniArray['email']['SenderEmail'] ?? '',
            'smtp' => [
                'SMTPDebug' => $iniArray['email']['SMTPDebug'] ?? '',
                'SMTPHost' => $iniArray['email']['Host'] ?? '',
                'SMTPAuth' => $iniArray['email']['SMTPAuth'] ?? '',
                'SMTPUsername' => $iniArray['email']['Username'] ?? '',
                'SMTPPassword' => $iniArray['email']['Password'] ?? '',
                'SMTPSecure' => $iniArray['email']['SMTPSecure'] ?? '',
                'SMTPPort' => $smtpPort,
                'SMTPHelo' => $iniArray['email']['SMTPHelo'] ?? '',
                'SMTPVerifySSL' => $smtpVerifySsl,
                'globalDebug' => $iniArray['general']['Loglevel'] ?? '',
            ],
        ];

        $config->fx = [
            'api' => $iniArray['fx']['FreecurrencyAPI'] ?? '',
            'local' => $iniArray['fx']['TargetCurrency'] ?? '',
            'url' => $iniArray['fx']['FreecurrencyURL'] ?? '',
        ];

        $config->comments = [
            'disqusEnabled' => ($iniArray['comments']['Disqus'] ?? '') === 'enabled',
            'disqusDevUrl' => $iniArray['comments']['DisqusDevURL'] ?? '',
            'disqusProdUrl' => $iniArray['comments']['DisqusProdURL'] ?? '',
        ];

        $config->database = [
            'host' => $iniArray['database']['DBServer'] ?? '',
            'user' => $iniArray['database']['DBUser'] ?? '',
            'pass' => $iniArray['database']['DBPass'] ?? '',
            'name' => $iniArray['database']['DBName'] ?? '',
        ];

        $config->applyOverrides($overrides);

        return $config;
    }

    public function general(string $key, mixed $default = null): mixed
    {
        return $this->general[$key] ?? $default;
    }

    public function security(string $key, mixed $default = null): mixed
    {
        return $this->security[$key] ?? $default;
    }

    public function email(string $key, mixed $default = null): mixed
    {
        return $this->email[$key] ?? $default;
    }

    public function fx(string $key, mixed $default = null): mixed
    {
        return $this->fx[$key] ?? $default;
    }

    public function comments(string $key, mixed $default = null): mixed
    {
        return $this->comments[$key] ?? $default;
    }

    public function database(string $key, mixed $default = null): mixed
    {
        return $this->database[$key] ?? $default;
    }

    public function getSmtpParameters(): array
    {
        return $this->email['smtp'] ?? [];
    }

    public function toArray(): array
    {
        return $this->toArrayInternal(true);
    }

    public function toArrayRaw(): array
    {
        return $this->toArrayInternal(false);
    }

    private function applyOverrides(array $overrides): void
    {
        foreach ($overrides as $section => $values) :
            if (!is_array($values)) :
                continue;
            endif;
            if ($section === 'general') :
                $this->general = array_merge($this->general, $values);
            elseif ($section === 'security') :
                $this->security = array_merge($this->security, $values);
            elseif ($section === 'email') :
                if (isset($values['smtp']) && is_array($values['smtp'])) :
                    $this->email['smtp'] = array_merge($this->email['smtp'] ?? [], $values['smtp']);
                    unset($values['smtp']);
                endif;
                $this->email = array_merge($this->email, $values);
            elseif ($section === 'fx') :
                $this->fx = array_merge($this->fx, $values);
            elseif ($section === 'comments') :
                $this->comments = array_merge($this->comments, $values);
            endif;
        endforeach;
    }

    private function toArrayInternal(bool $redact): array
    {
        $data = [
            'general' => $this->general,
            'security' => $this->security,
            'email' => $this->email,
            'fx' => $this->fx,
            'comments' => $this->comments,
            'database' => $this->database,
        ];

        if ($redact) :
            $data = $this->redactSecrets($data);
        endif;

        return $data;
    }

    private function redactSecrets(array $data): array
    {
        if (!empty($data['security']['turnstileSecretKey'])) :
            $data['security']['turnstileSecretKey'] = '[REDACTED]';
        endif;
        if (!empty($data['email']['smtp']['SMTPPassword'])) :
            $data['email']['smtp']['SMTPPassword'] = '[REDACTED]';
        endif;
        if (!empty($data['email']['smtp']['SMTPUsername'])) :
            $data['email']['smtp']['SMTPUsername'] = '[REDACTED]';
        endif;
        if (!empty($data['fx']['api'])) :
            $data['fx']['api'] = '[REDACTED]';
        endif;

        return $data;
    }

    private static function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));
        $allowed = ['prod', 'dev', 'test', 'staging', 'stage'];
        if (!in_array($tier, $allowed, true)) :
            return 'prod';
        endif;
        if ($tier === 'stage') :
            return 'staging';
        endif;
        return $tier;
    }

    private static function normalizeInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) :
            return $value;
        endif;
        if (is_numeric($value)) :
            return (int) $value;
        endif;
        return $default;
    }

    private static function normalizeBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) :
            return $value;
        endif;
        if (is_int($value)) :
            return $value === 1;
        endif;
        if (is_string($value)) :
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) :
                return true;
            endif;
            if (in_array($value, ['0', 'false', 'no', 'off', ''], true)) :
                return false;
            endif;
        endif;
        return $default;
    }
}
