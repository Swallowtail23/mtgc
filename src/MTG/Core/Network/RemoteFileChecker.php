<?php

/*
Version:     1.0
Date:        11/01/26
Name:        RemoteFileChecker.php
Purpose:     Remote file validation helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Network;

use MTG\Core\AppConfig;
use MTG\Core\Message;
use MTG\Core\UserAgent;

class RemoteFileChecker
{
    public static function exists($url, AppConfig $appConfig, ?Message $msg = null): bool
    {
        if ($msg === null) :
            $msg = new Message($appConfig);
        endif;

        if (stripos($url, 'file://') === 0) :
            $path = substr($url, 7);
            if (is_file($path) && filesize($path) > 0) :
                $msg->logMessage('[NOTICE]', "$url exists locally");
                return true;
            endif;
            $msg->logMessage('[ERROR]', "$url does not exist locally or is empty");
            return false;
        endif;

        $ch = curl_init();
        $userAgent = UserAgent::buildFromConfig($appConfig, null, $msg);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json;q=0.9,*/*;q=0.8"));
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $curlresult = curl_exec($ch);
        $curldetail = curl_getinfo($ch);
        curl_close($ch);
        if ($curlresult === false || $curldetail['http_code'] != 200 || $curldetail['download_content_length'] < 1) :
            $msg->logMessage(
                '[ERROR]',
                "{$curldetail['url']} DOES NOT exist, HTTP code is: {$curldetail['http_code']}, "
                . "file size is: {$curldetail['download_content_length']} bytes"
            );
            return false;
        else :
            $msg->logMessage(
                '[NOTICE]',
                "{$curldetail['url']} exists, HTTP code is: {$curldetail['http_code']}, "
                . "file size is: {$curldetail['download_content_length']} bytes"
            );
            return true;
        endif;
    }
}
