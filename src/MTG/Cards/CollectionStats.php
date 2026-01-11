<?php

/*
Version:     1.8
Date:        11/01/26
Name:        CollectionStats.php
Purpose:     Compute collection totals and values for a user.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Auth\SessionManager;
use MTG\Core\AppConfig;
use MTG\Core\Message;

class CollectionStats
{
    /**
    * @var mysqli
    */
    private $db;
    private $message;
    private $fxAPI;
    private $fxLocal;
    private $appConfig;

    public function __construct($db, AppConfig $appConfig)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->fxAPI = $this->appConfig->fx('api', '');
        $this->fxLocal = $this->appConfig->fx('local', '');
        $logfile = (string) $this->appConfig->general('logFile', '');
        $this->message = new Message($logfile);
    }

    public function getStats($userNumber, $tableName, $preferredCurrency = null)
    {
        $totalCardCount = $this->getTotalCardCount($tableName);
        $mrCardCount = $this->getMrCardCount($tableName);
        $valueUsd = $this->getUsdValue($tableName);

        $localCurrency = null;
        $rateUsed = null;
        $localValue = null;

        $targetCurrency = $preferredCurrency ?: $this->fxLocal;
        $targetCurrency = strtoupper(trim((string) $targetCurrency));

        if (!empty($targetCurrency) && $targetCurrency !== 'USD' && !empty($this->fxAPI)) :
            $sessionManager = new SessionManager($this->db, [], $this->appConfig);
            $currencies = "usd_" . strtolower($targetCurrency);
            $rate = $sessionManager->getRateForCurrencyPair($currencies);
            if ($rate !== null && $rate !== false) :
                $rateUsed = (float) $rate;
                $localValue = round($valueUsd * $rateUsed, 2);
                $localCurrency = $targetCurrency;
            else :
                $this->message->logMessage('[NOTICE]', "FX unavailable for $currencies");
            endif;
        endif;

        return [
            'value_usd' => $valueUsd,
            'value_local' => $localValue,
            'local_currency' => $localCurrency,
            'rate_used' => $rateUsed,
            'card_count' => $totalCardCount,
            'mr_count' => $mrCardCount
        ];
    }

    private function getTotalCardCount($tableName)
    {
        $query = "
            SELECT sum(IFNULL(normal, 0)) + sum(IFNULL(foil, 0)) + sum(IFNULL(etched, 0)) AS TOTAL
            FROM `$tableName`
        ";
        $result = $this->db->query($query);
        if ($result === false) :
            throw new \Exception(
                '[ERROR] collectionstats.class.php: total count query failed: ' . $this->db->error
            );
        endif;
        $row = $result->fetch_array(MYSQLI_ASSOC);
        $count = is_null($row['TOTAL']) ? 0 : (int) $row['TOTAL'];
        $this->message->logMessage('[DEBUG]', "Total card count for $tableName = $count");
        return $count;
    }

    private function getMrCardCount($tableName)
    {
        $query = "
            SELECT
              SUM(IFNULL(`$tableName`.normal, 0))
              +
              SUM(IFNULL(`$tableName`.foil, 0))
              +
              SUM(IFNULL(`$tableName`.etched, 0))
             AS TOTALMR
             FROM `$tableName`
             LEFT JOIN cards_scry
             ON `$tableName`.id = cards_scry.id
             WHERE rarity IN ('mythic', 'rare')
        ";
        $result = $this->db->query($query);
        if ($result === false) :
            throw new \Exception(
                '[ERROR] collectionstats.class.php: M/R count query failed: ' . $this->db->error
            );
        endif;
        $row = $result->fetch_array(MYSQLI_ASSOC);
        $count = is_null($row['TOTALMR']) ? 0 : (int) $row['TOTALMR'];
        $this->message->logMessage('[DEBUG]', "Total mythic/rare count for $tableName = $count");
        return $count;
    }

    private function getUsdValue($tableName)
    {
        $query = "SELECT (
                COALESCE(SUM(`$tableName`.normal * price),0)
                +
                COALESCE(SUM(`$tableName`.foil *
                    CASE
                        WHEN price_foil IS NOT NULL AND price_foil > 0 THEN price_foil
                        WHEN price IS NOT NULL AND price > 0 THEN price
                        ELSE 0
                    END), 0)
                +
                COALESCE(SUM(`$tableName`.etched *
                    CASE
                        WHEN price_etched IS NOT NULL AND price_etched > 0 THEN price_etched
                        WHEN price IS NOT NULL AND price > 0 THEN price
                        ELSE 0
                    END), 0)
                )
                AS TOTAL
                FROM `$tableName` LEFT JOIN cards_scry ON `$tableName`.id = cards_scry.id";
        $result = $this->db->query($query);
        if ($result === false) :
            throw new \Exception(
                '[ERROR] collectionstats.class.php: Value query failed: ' . $this->db->error
            );
        endif;
        $row = $result->fetch_assoc();
        $value = (float) ($row['TOTAL'] ?? 0);
        $this->message->logMessage('[DEBUG]', "Unformatted USD value for $tableName = $value");
        return $value;
    }
}
