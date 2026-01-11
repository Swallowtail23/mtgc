<?php

/*
Version:     1.0
Date:        11/01/26
Name:        Filesystem.php
Purpose:     Filesystem helper utilities.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core;

class Filesystem
{
    public static function ensureDirectoryExists($path, AppConfig $appConfig, ?Message $msg = null): void
    {
        if (is_dir($path)) :
            return;
        endif;

        if ($msg === null) :
            $msg = new Message($appConfig);
        endif;

        if (@mkdir($path, 0755, true)) :
            $msg->logMessage('[NOTICE]', "Created directory $path");
            return;
        endif;

        $error = error_get_last();
        $msg->logMessage('[ERROR]', "Failed to create directory $path: " . ($error['message'] ?? 'unknown error'));
        throw new \Exception("[ERROR] Unable to create directory {$path}");
    }
}
