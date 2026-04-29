<?php

/*
Version:     1.1
Date:        29/04/26
Name:        ProfilePreferences.php
Purpose:     Profile preference helpers for update flows.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Profile;

use MTG\Core\Message;

class ProfilePreferences
{
    public static function normalizeCurrency(?string $currency, array $rulesCurrencies): ?string
    {
        if ($currency === null) :
            return null;
        endif;

        $currency = trim($currency);
        if ($currency === '' || $currency === 'zzz') :
            return null;
        endif;

        if (!in_array($currency, array_column($rulesCurrencies, 'code'), true)) :
            return null;
        endif;

        return $currency;
    }

    /**
    * @param \mysqli|object $db
    */
    public static function updateCurrency(
        $db,
        array $rulesCurrencies,
        int $userId,
        ?string $currency,
        Message $msg
    ): ?string {
        $normalized = self::normalizeCurrency($currency, $rulesCurrencies);
        $msg->logMessage('[DEBUG]', "Called with user currency '$normalized'");

        $query = "UPDATE users SET currency = ? WHERE usernumber = ?";
        $params = [$normalized, $userId];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new \Exception('[ERROR] profile.php: Error: ' . $db->error);
        endif;

        return $normalized;
    }
}
