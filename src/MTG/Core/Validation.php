<?php

/*
Version:     1.0
Date:        11/01/26
Name:        Validation.php
Purpose:     Validation helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core;

class Validation
{
    public static function validateTrueDecimal($value, ?AppConfig $appConfig = null): bool
    {
        $result = floor($value);
        if ($appConfig !== null) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', "Checking $value for true decimal, result is $result");
        endif;

        return (floor($value) != $value);
    }

    public static function validUUID($uuid, ?AppConfig $appConfig = null)
    {
        if ($appConfig !== null) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', "Checking for valid UUID ($uuid)");
        endif;

        if (
            preg_match(
                '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
                $uuid
            )
        ) :
            if (isset($msg)) :
                $msg->logMessage('[DEBUG]', "Valid UUID ($uuid)");
            endif;
            return $uuid;
        else :
            if (isset($msg)) :
                $msg->logMessage('[ERROR]', "Invalid UUID ($uuid), returning 'false'");
            endif;
            return false;
        endif;
    }

    public static function validTableName($input, ?AppConfig $appConfig = null)
    {
        if ($appConfig !== null) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', "Checking for valid table name ($input)");
        endif;

        $pattern = '/^\d+collection$/';
        if (preg_match($pattern, $input)) :
            if (isset($msg)) :
                $msg->logMessage('[DEBUG]', "Valid table name");
            endif;
            return $input;
        else :
            if (isset($msg)) :
                $msg->logMessage('[ERROR]', "Invalid table name");
            endif;
            return false;
        endif;
    }

    public static function isValidSetcode($setcode): bool
    {
        return preg_match('/^[a-zA-Z0-9]{3,6}$/', $setcode) || empty($setcode);
    }

    public static function isValidCardName($name): bool
    {
        // Cannot be only numbers
        return preg_match('/\D/', $name) || empty($name);
    }

    public static function isValidLanguageCode($lang): bool
    {
        // Alpha only
        return preg_match('/^[a-zA-Z]*$/', $lang) || empty($lang);
    }

    public static function inArrayCaseInsensitive($needle, $haystack): bool
    {
        if (!is_array($haystack)) :
            return false;
        endif;

        foreach ($haystack as $item) :
            if (strtolower($needle) == strtolower($item)) :
                return true;
            endif;
        endforeach;
        return false;
    }
}
