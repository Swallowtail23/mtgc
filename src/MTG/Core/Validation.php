<?php

/*
Version:     1.1
Date:        29/04/26
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
    public static function validateTrueDecimal(mixed $value, ?AppConfig $appConfig = null): bool
    {
        $result = floor($value);
        if ($appConfig !== null) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', "Checking $value for true decimal, result is $result");
        endif;

        return (floor($value) != $value);
    }

    public static function validUUID(mixed $uuid, ?AppConfig $appConfig = null): string|false
    {
        if ($appConfig !== null) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', "Checking for valid UUID ($uuid)");
        endif;

        if (
            is_string($uuid)
            &&
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

    public static function validTableName(mixed $input, ?AppConfig $appConfig = null): string|false
    {
        if ($appConfig !== null) :
            $msg = new Message($appConfig);
            $msg->logMessage('[DEBUG]', "Checking for valid table name ($input)");
        endif;

        $pattern = '/^\d+collection$/';
        if (is_string($input) && preg_match($pattern, $input)) :
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

    public static function isValidSetcode(string $setcode): bool
    {
        return preg_match('/^[a-zA-Z0-9]{3,6}$/', $setcode) || empty($setcode);
    }

    public static function isValidCardName(string $name): bool
    {
        // Cannot be only numbers
        return preg_match('/\D/', $name) || empty($name);
    }

    public static function isValidLanguageCode(string $lang): bool
    {
        // Alpha only
        return preg_match('/^[a-zA-Z]*$/', $lang) || empty($lang);
    }

    public static function inArrayCaseInsensitive(mixed $needle, mixed $haystack): bool
    {
        if (!is_array($haystack)) :
            return false;
        endif;

        foreach ($haystack as $item) :
            if (strtolower((string) $needle) == strtolower((string) $item)) :
                return true;
            endif;
        endforeach;
        return false;
    }
}
