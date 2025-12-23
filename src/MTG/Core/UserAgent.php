<?php

/*
Version:     1.1
Date:        23/12/25
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
    public static function build($iniPath = '/opt/mtg/mtg_new.ini', $versionPath = null, $logfile = null)
    {
        static $cache = array();
        $cacheKey = $iniPath . '|' . ($versionPath ?? '');
        if (isset($cache[$cacheKey])) :
            if (!empty($logfile)) :
                $msg = new \MTG\Core\Message($logfile);
                $msg->logMessage('[DEBUG]', "User agent cache hit for $iniPath");
            endif;
            return $cache[$cacheKey];
        endif;

        if ($versionPath === null) :
            $versionPath = dirname(__DIR__, 3) . '/VERSION';
        endif;

        $version = 'unknown';
        if (is_file($versionPath)) :
            $rawVersion = trim((string) file_get_contents($versionPath));
            if ($rawVersion !== '') :
                $version = ltrim($rawVersion, "vV");
            else :
                if (!empty($logfile)) :
                    $msg = new \MTG\Core\Message($logfile);
                    $msg->logMessage('[DEBUG]', "Version file is empty at $versionPath");
                endif;
            endif;
        else :
            if (!empty($logfile)) :
                $msg = new \MTG\Core\Message($logfile);
                $msg->logMessage('[DEBUG]', "Version file missing at $versionPath");
            endif;
        endif;

        $url = 'unknown';
        $adminEmail = 'unknown';
        if (is_file($iniPath)) :
            $ini = new \MTG\Core\INI($iniPath);
            $iniArray = $ini->data;
            if (!empty($iniArray['general']['URL'])) :
                $url = trim((string) $iniArray['general']['URL']);
            else :
                if (!empty($logfile)) :
                    $msg = new \MTG\Core\Message($logfile);
                    $msg->logMessage('[DEBUG]', "User agent URL missing from ini file");
                endif;
            endif;
            if (!empty($iniArray['email']['AdminEmail'])) :
                $adminEmail = trim((string) $iniArray['email']['AdminEmail']);
            else :
                if (!empty($logfile)) :
                    $msg = new \MTG\Core\Message($logfile);
                    $msg->logMessage('[DEBUG]', "User agent admin email missing from ini file");
                endif;
            endif;
        else :
            if (!empty($logfile)) :
                $msg = new \MTG\Core\Message($logfile);
                $msg->logMessage('[DEBUG]', "User agent ini file missing at $iniPath");
            endif;
        endif;

        $userAgent = self::buildFromParts($version, $url, $adminEmail);
        $cache[$cacheKey] = $userAgent;

        if (!empty($logfile)) :
            $msg = new \MTG\Core\Message($logfile);
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
}
