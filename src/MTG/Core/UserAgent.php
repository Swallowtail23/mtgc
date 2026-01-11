<?php

/*
Version:     1.5
Date:        11/01/26
Name:        UserAgent.php
Purpose:     Build consistent HTTP user agent strings from config and version data.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

class UserAgent
{
    public static function buildFromConfig(AppConfig $config, $versionPath = null, $msg = null): string
    {
        static $cache = array();

        if ($versionPath === null) :
            $versionPath = dirname(__DIR__, 3) . '/VERSION';
        endif;

        $url = trim((string) $config->general('url', ''));
        $adminEmail = trim((string) $config->email('adminEmail', ''));

        if ($url === '') :
            $url = 'unknown';
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', 'User agent URL missing from config');
            endif;
        endif;
        if ($adminEmail === '') :
            $adminEmail = 'unknown';
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', 'User agent admin email missing from config');
            endif;
        endif;

        $cacheKey = $versionPath . '|' . $url . '|' . $adminEmail;
        if (isset($cache[$cacheKey])) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "User agent cache hit for $versionPath");
            endif;
            return $cache[$cacheKey];
        endif;

        $version = self::resolveVersion($versionPath, $msg);
        $userAgent = self::buildFromParts($version, $url, $adminEmail);
        $cache[$cacheKey] = $userAgent;

        if ($msg instanceof Message) :
            $msg->logMessage('[DEBUG]', "User agent built as $userAgent");
        endif;

        return $userAgent;
    }

    public static function buildFromParts($version, $url, $adminEmail): string
    {
        $version = trim((string) $version);
        $url = trim((string) $url);
        $adminEmail = trim((string) $adminEmail);

        return "MtGCollection/{$version} ({$url}; {$adminEmail})";
    }

    private static function resolveVersion(string $versionPath, $msg = null): string
    {
        $version = 'unknown';
        if (is_file($versionPath)) :
            $rawVersion = trim((string) file_get_contents($versionPath));
            if ($rawVersion !== '') :
                $version = ltrim($rawVersion, "vV");
            else :
                if ($msg instanceof Message) :
                    $msg->logMessage('[DEBUG]', "Version file is empty at $versionPath");
                endif;
            endif;
        else :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Version file missing at $versionPath");
            endif;
        endif;

        return $version;
    }
}
