<?php

/*
Version:     1.7
Date:        11/01/26
Name:        PriceManager.php
Purpose:     Price management class.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\AppConfig;
use MTG\Core\Message;
use MTG\Core\UserAgent;

class PriceManager
{
    /**
    * @var mysqli
    */
    private $db;
    private $appConfig;
    private $userEmail;
    private $message;
    private $maxCardDataAge;

    public function __construct($db, AppConfig $appConfig, $userEmail)
    {
        $this->db = $db;
        $this->appConfig = $appConfig;
        $this->userEmail = $userEmail;
        $this->message = new Message($this->appConfig);
        $this->maxCardDataAge = (int) $this->appConfig->general('maxCardDataAge', 0);
    }

    // Fetch TCG buy URI and price from scryfall.com JSON data
    public function scryfall($cardId, $action = '')
    {
        //Set up the function
        $this->message->logMessage('[DEBUG]', "Scryfall API by $this->userEmail for $cardId");
        if (!isset($cardId)) :
            $this->message->logMessage('[ERROR]', "Scryfall API by $this->userEmail without required card id");
            exit;
        endif;
        $baseurl = "https://api.scryfall.com/";
        $cardId = $this->db->real_escape_string($cardId);
        $time = time();
        //Set the URL
        $url = $baseurl . "cards/" . $cardId . "?" . $time;
        $this->message->logMessage('[DEBUG]', "Scryfall API by $this->userEmail URL for $cardId is $url");
        $userAgent = UserAgent::buildFromConfig($this->appConfig, null, $this->message);
        $this->message->logMessage('[DEBUG]', "Scryfall API user agent set to $userAgent");

        if ($row = $this->db->execute_query("Select id FROM cards_scry WHERE id = ?", [$cardId])) :
            if ($row->num_rows === 0) :
                $this->message->logMessage(
                    '[ERROR]',
                    "Scryfall API by $this->userEmail, no card with this id - returning 'nocard'"
                );
                $returnarray = array("action" => "nocard");
                return $returnarray;
            elseif ($row->num_rows === 1) :
                $scrymethod = 'id';
            endif;
        else :
            $this->message->logMessage('[ERROR]', "Scryfall API error");
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;

        // Check for existing data, not too old, and set required action
        $rowqry = $this->db->execute_query(
            "SELECT jsonupdatetime, tcg_buy_uri FROM scryfalljson WHERE id = ? LIMIT 1",
            [$cardId]
        );
        if ($rowqry !== false and $rowqry->num_rows < 1) :
            //No data, fetch and insert:
            $scryaction = 'get';
            $this->message->logMessage(
                '[DEBUG]',
                "Scryfall API by $this->userEmail with result: No data exists for $cardId, running '$scryaction'"
            );
        elseif ($rowqry !== false) :
            $row = $rowqry->fetch_assoc();
            $lastjsontime = $row['jsonupdatetime'];
            $record_age = (time() - $lastjsontime);
            $this->message->logMessage(
                '[DEBUG]',
                "Scryfall API by $this->userEmail with result: Data exists for $cardId, $record_age seconds old"
            );
            if ($record_age > $this->maxCardDataAge) :
                //Old data, fetch and update:
                $scryaction = 'update';
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail with result: Data stale "
                        . "(older than {$this->maxCardDataAge} seconds)"
                        . " for $cardId, running '$scryaction'"
                );
            elseif ($action == "update") :
                //Update forced
                $scryaction = 'update';
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail with result: Data update requested for $cardId, running "
                        . "'$scryaction'"
                );
            else :
                //data is there and is current:
                $scryaction = 'read';
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail with result: Data not stale "
                        . "(younger than {$this->maxCardDataAge} seconds) for $cardId, running '$scryaction'"
                );
            endif;
        else :
            throw new \Exception(
                '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                    . ": SQL failure: " . $this->db->error
            );
        endif;

        // Actions:

        // UPDATE
        if ($scryaction === 'update') :
            $this->message->logMessage(
                '[DEBUG]',
                "Scryfall API by $this->userEmail with 'update' result: fetching $url"
            );
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_HTTPHEADER => array(
                    "Accept: application/json;q=0.9,*/*;q=0.8"
                ),
            );
            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            $curlresult = curl_exec($ch);
            $this->message->logMessage('[DEBUG]', "Scryfall API by $this->userEmail with update: $curlresult");
            curl_close($ch);
            $scryfall_result = json_decode($curlresult, true);
            if (isset($scryfall_result["purchase_uris"]["tcgplayer"])) :
                $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
            else :
                $tcg_buy_uri = null;
            endif;
            if (isset($scryfall_result["prices"])) :
                $this->message->logMessage('[DEBUG]', "Scryfall API by $this->userEmail, price section included");
                if (isset($scryfall_result["prices"]["usd"])) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd set: {$scryfall_result["prices"]["usd"]}"
                    );
                    if ($scryfall_result["prices"]["usd"] == '') :
                        $price = 0.00;
                    elseif ($scryfall_result["prices"]["usd"] == 'null') :
                        $price = null;
                    else :
                        $price = $scryfall_result["prices"]["usd"];
                    endif;
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd not set, setting to null"
                    );
                    $price = null;
                endif;
                if (isset($scryfall_result["prices"]["usd_foil"])) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_foil set: {$scryfall_result["prices"]["usd_foil"]}"
                    );
                    if ($scryfall_result["prices"]["usd_foil"] == '') :
                        $price_foil = 0.00;
                    elseif ($scryfall_result["prices"]["usd_foil"] == 'null') :
                        $price_foil = null;
                    else :
                        $price_foil = $scryfall_result["prices"]["usd_foil"];
                    endif;
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_foil not set, setting to null"
                    );
                    $price_foil = null;
                endif;
                if (isset($scryfall_result["prices"]["usd_etched"])) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_etched set: "
                        . "{$scryfall_result['prices']['usd_etched']}"
                    );
                    if ($scryfall_result["prices"]["usd_etched"] == '') :
                        $price_etched = 0.00;
                    elseif ($scryfall_result["prices"]["usd_etched"] == 'null') :
                        $price_etched = null;
                    else :
                        $price_etched = $scryfall_result["prices"]["usd_etched"];
                    endif;
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_etched not set, setting to null"
                    );
                    $price_etched = null;
                endif;

                if (
                    ($price == 0.00 or $price === null)
                    and ($price_foil == 0.00 or $price_foil === null)
                    and ($price_etched == 0.00 or $price_etched === null)
                ) :
                    $price_sort = 0.00;
                elseif (
                    ($price_foil == 0.00 or $price_foil === null)
                    and ($price_etched == 0.00 or $price_etched === null)
                ) :
                    $price_sort = $price;
                elseif (($price == 0.00 or $price === null) and ($price_etched == 0.00 or $price_etched === null)) :
                    $price_sort = $price_foil;
                elseif (($price == 0.00 or $price === null) and ($price_foil == 0.00 or $price_foil === null)) :
                    $price_sort = $price_etched;
                elseif ($price == 0.00 or $price === null) :
                    $price_sort = min($price_etched, $price_foil);
                elseif ($price_foil == 0.00 or $price_foil === null) :
                    $price_sort = min($price_etched, $price);
                elseif ($price_etched == 0.00 or $price_etched === null) :
                    $price_sort = min($price, $price_foil);
                else :
                    $price_sort = min($price, $price_foil, $price_etched);
                endif;

                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall data: price: $price, price foil: $price_foil, price etched: $price_etched, therefore "
                        . "$price_sort is used for sorting price"
                );
                $update_tcg_uri = 'UPDATE scryfalljson SET tcg_buy_uri = ?,jsonupdatetime = ? WHERE id = ?';
                $stmt = $this->db->prepare($update_tcg_uri);
                if ($stmt === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": Preparing SQL: " . $this->db->error
                    );
                endif;
                $this->message->logMessage('[NOTICE]', "$update_tcg_uri");
                $stmt->bind_param('sss', $tcg_buy_uri, $time, $cardId);
                if ($stmt === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": Binding SQL: " . $this->db->error
                    );
                endif;
                $exec = $stmt->execute();
                if ($exec === false) :
                    $this->message->logMessage('[ERROR]', "Updating tcg uri failed " . $this->db->error);
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Updating tcg uri, new data written for $cardId: Insert ID: " . $stmt->insert_id
                    );
                endif;

                $update_prices = 'UPDATE cards_scry SET price = ?,price_foil = ?,price_etched = ?,price_sort = ?
                    WHERE id = ?';
                $stmt = $this->db->prepare($update_prices);
                if ($stmt === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": Preparing SQL: " . $this->db->error
                    );
                endif;
                $this->message->logMessage('[NOTICE]', "$update_prices");
                $stmt->bind_param('sssss', $price, $price_foil, $price_etched, $price_sort, $cardId);
                if ($stmt === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": Binding SQL: " . $this->db->error
                    );
                endif;
                $exec = $stmt->execute();
                if ($exec === false) :
                    $this->message->logMessage(
                        '[ERROR]',
                        "Scryfall API by $this->userEmail, price data update failed: " . $this->db->error
                    );
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price data updated for $cardId: Insert ID: "
                            . $stmt->insert_id
                    );
                endif;
            else :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, result does not contain a prices section"
                );
                $prices = 0;
                $price = 0;
                $price_foil = 0;
                $price_etched = 0;
            endif;
            $returnarray = array(
                "action" => "update",
                "tcg_uri" => $tcg_buy_uri,
                "price" => $price,
                "price_foil" => $price_foil,
                "price_etched" => $price_etched,
            );

        // READ
        elseif ($scryaction === 'read') :
            $tcg_buy_uri = $row['tcg_buy_uri'];

            $price = null;
            $price_foil = null;
            $price_etched = null;
            $this->message->logMessage(
                '[DEBUG]',
                "Scryfall API by $this->userEmail, returning $tcg_buy_uri"
            );
            $returnarray = array(
                "action" => "read",
                "tcg_uri" => $tcg_buy_uri,
                "price" => $price,
                "price_foil" => $price_foil,
                "price_etched" => $price_etched,
            );

        // GET
        elseif ($scryaction === 'get') :
            $this->message->logMessage(
                '[DEBUG]',
                "Scryfall API by $this->userEmail with 'get' result: fetching $url"
            );
            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_HTTPHEADER => array("Accept: application/json;q=0.9,*/*;q=0.8"),
                );
            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            $curlresult = curl_exec($ch);
            $this->message->logMessage('[DEBUG]', "Scryfall API by $this->userEmail with get: $curlresult");
            curl_close($ch);
            $scryfall_result = json_decode($curlresult, true);
            if (isset($scryfall_result["purchase_uris"]["tcgplayer"])) :
                $tcg_buy_uri = $scryfall_result["purchase_uris"]["tcgplayer"];
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, result contain tcg link "
                        . "{$scryfall_result['purchase_uris']['tcgplayer']}"
                );
            else :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, result does not contain a tcg link"
                );
                $tcg_buy_uri = 0;
            endif;
            if (isset($scryfall_result["prices"])) :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, price section included"
                );
                if (isset($scryfall_result["prices"]["usd"])) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd set: "
                            . "{$scryfall_result['prices']['usd']}"
                    );
                    if ($scryfall_result["prices"]["usd"] == '') :
                        $price = 0.00;
                    elseif ($scryfall_result["prices"]["usd"] == 'null') :
                        $price = null;
                    else :
                        $price = $scryfall_result["prices"]["usd"];
                    endif;
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd not set, setting to null"
                    );
                    $price = null;
                endif;
                if (isset($scryfall_result["prices"]["usd_foil"])) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_foil set: "
                            . "{$scryfall_result['prices']['usd_foil']}"
                    );
                    if ($scryfall_result["prices"]["usd_foil"] == '') :
                        $price_foil = 0.00;
                    elseif ($scryfall_result["prices"]["usd_foil"] == 'null') :
                        $price_foil = null;
                    else :
                        $price_foil = $scryfall_result["prices"]["usd_foil"];
                    endif;
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_foil not set, setting to null"
                    );
                    $price_foil = null;
                endif;
                if (isset($scryfall_result["prices"]["usd_etched"])) :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_etched set: "
                            . "{$scryfall_result['prices']['usd_etched']}"
                    );
                    if ($scryfall_result["prices"]["usd_etched"] == '') :
                        $price_etched = 0.00;
                    elseif ($scryfall_result["prices"]["usd_etched"] == 'null') :
                        $price_etched = null;
                    else :
                        $price_etched = $scryfall_result["prices"]["usd_etched"];
                    endif;
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price/usd_etched not set, setting to null"
                    );
                    $price_etched = null;
                endif;

                if (
                    ($price == 0.00 or $price === null)
                    and ($price_foil == 0.00 or $price_foil === null)
                    and ($price_etched == 0.00 or $price_etched === null)
                ) :
                    $price_sort = 0.00;
                elseif (
                    ($price_foil == 0.00 or $price_foil === null)
                    and ($price_etched == 0.00 or $price_etched === null)
                ) :
                    $price_sort = $price;
                elseif (($price == 0.00 or $price === null) and ($price_etched == 0.00 or $price_etched === null)) :
                    $price_sort = $price_foil;
                elseif (($price == 0.00 or $price === null) and ($price_foil == 0.00 or $price_foil === null)) :
                    $price_sort = $price_etched;
                elseif ($price == 0.00 or $price === null) :
                    $price_sort = min($price_etched, $price_foil);
                elseif ($price_foil == 0.00 or $price_foil === null) :
                    $price_sort = min($price_etched, $price);
                elseif ($price_etched == 0.00 or $price_etched === null) :
                    $price_sort = min($price, $price_foil);
                else :
                    $price_sort = min($price, $price_foil, $price_etched);
                endif;
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, prices are: $price, $price_foil and $price_etched; "
                        . "Sort price = $price_sort"
                );
            else :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, result does not contain a prices section"
                );
                $prices = 0;
                $price = 0;
                $price_foil = 0;
                $price_etched = 0;
            endif;
            $query = 'INSERT INTO scryfalljson (id, jsonupdatetime, tcg_buy_uri) VALUES (?,?,?)';
            $stmt = $this->db->prepare($query);
            if ($stmt === false) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": Preparing SQL: " . $this->db->error
                );
            endif;
            $this->message->logMessage('[DEBUG]', "$query");
            $stmt->bind_param('sss', $cardId, $time, $tcg_buy_uri);
            if ($stmt === false) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                        . ": Binding SQL: " . $this->db->error
                );
            endif;
            $exec = $stmt->execute();
            if ($exec === false) :
                $this->message->logMessage('[ERROR]', "Adding update notice: failed " . $this->db->error);
            else :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, new data written for $cardId: Insert ID: "
                        . $stmt->insert_id
                );
            endif;
            if (!isset($prices)) :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Scryfall API by $this->userEmail, writing prices $price, $price_foil, $price_sort"
                );
                $query = 'UPDATE cards_scry SET price = ?,price_foil = ?,price_sort = ? WHERE id = ?';
                $stmt = $this->db->prepare($query);
                $stmt->bind_param('ssss', $price, $price_foil, $price_sort, $cardId);
                if ($stmt === false) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . " " . __LINE__ . "Function " . __FUNCTION__
                            . ": Binding SQL: " . $this->db->error
                    );
                endif;
                $exec = $stmt->execute();
                if ($exec === false) :
                    $this->message->logMessage(
                        '[ERROR]',
                        "Scryfall API by $this->userEmail, price data update failed"
                    );
                else :
                    $this->message->logMessage(
                        '[DEBUG]',
                        "Scryfall API by $this->userEmail, price data updated: Insert ID: " . $stmt->insert_id
                    );
                endif;
            endif;
            $returnarray = array(
                "action" => "get",
                "tcg_uri" => $tcg_buy_uri,
                "price" => $price,
                "price_foil" => $price_foil,
                "price_etched" => $price_etched,
            );
        endif;
        return $returnarray;
    }


    public function updateCollectionValues($collection, $cardId = "")
    {
        $i = 0; // Counter for updated rows

        if ($cardId === "") : // Full collection value update (set-based)
            // Wrap in a transaction for safety
            if (!$this->db->begin_transaction()) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                    . ' Function ' . __FUNCTION__
                    . ': Failed to start transaction: ' . $this->db->error
                );
            endif;

            // SQL-based update: compute normalrate, foilrate, etchedrate in SQL,
            // then set topvalue = GREATEST(normalrate, foilrate, etchedrate)
            $query = "
            UPDATE `$collection` AS c
            LEFT JOIN `cards_scry` AS cs ON c.id = cs.id
            SET c.topvalue = GREATEST(
                /* normalrate: only if qty * price > 0 */
                CASE
                    WHEN (IFNULL(c.normal, 0) * IFNULL(cs.price, 0)) > 0
                        THEN IFNULL(cs.price, 0)
                    ELSE 0
                END,

                /* foilrate: only if qty * foilprice > 0, with original fallback logic */
                CASE
                    WHEN (
                        IFNULL(c.foil, 0) *
                        CASE
                            WHEN cs.price_foil IS NOT NULL THEN cs.price_foil
                            WHEN cs.price_foil IS NULL
                                 AND cs.foil = 1
                                 AND c.foil IS NOT NULL
                                 AND c.foil > 0 THEN IFNULL(cs.price, 0)
                            ELSE 0
                        END
                    ) > 0
                    THEN
                        CASE
                            WHEN cs.price_foil IS NOT NULL THEN cs.price_foil
                            WHEN cs.price_foil IS NULL
                                 AND cs.foil = 1
                                 AND c.foil IS NOT NULL
                                 AND c.foil > 0 THEN IFNULL(cs.price, 0)
                            ELSE 0
                        END
                    ELSE 0
                END,

                /* etchedrate: only if qty * etchedprice > 0, with original fallback logic */
                CASE
                    WHEN (
                        IFNULL(c.etched, 0) *
                        CASE
                            WHEN cs.price_etched IS NOT NULL THEN cs.price_etched
                            WHEN cs.price_etched IS NULL
                                 AND c.etched IS NOT NULL
                                 AND c.etched > 0 THEN IFNULL(cs.price, 0)
                            ELSE 0
                        END
                    ) > 0
                    THEN
                        CASE
                            WHEN cs.price_etched IS NOT NULL THEN cs.price_etched
                            WHEN cs.price_etched IS NULL
                                 AND c.etched IS NOT NULL
                                 AND c.etched > 0 THEN IFNULL(cs.price, 0)
                            ELSE 0
                        END
                    ELSE 0
                END
            )
            WHERE c.qty_total > 0
        ";

            $start = microtime(true);
            $result = $this->db->query($query);
            $duration = microtime(true) - $start;

            $this->message->logMessage(
                '[DEBUG]',
                'updateCollectionValues bulk SQL runtime: ' . number_format($duration, 6) . 's'
            );

            if ($result === false) :
                $this->db->rollback();
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                    . ' Function ' . __FUNCTION__
                    . ': SQL: ' . $this->db->error
                );
            endif;

            $i = $this->db->affected_rows;

            if (!$this->db->commit()) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                    . ' Function ' . __FUNCTION__
                    . ': Commit failed: ' . $this->db->error
                );
            endif;

            $this->message->logMessage('[NOTICE]', "Value update completed (rows affected: $i)");
            return $i;
        else : // Single-card update (set-based SQL)
            if (!$this->db->begin_transaction()) :
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                    . ' Function ' . __FUNCTION__
                    . ': Failed to start transaction: ' . $this->db->error
                );
            endif;

            $query = "
            UPDATE `$collection` AS c
            LEFT JOIN `cards_scry` AS s
                ON c.id = s.id
            SET c.topvalue = GREATEST(
                -- Normal rate: only if qty * price > 0
                CASE
                    WHEN (IFNULL(c.normal, 0) * IFNULL(s.price, 0)) > 0
                        THEN IFNULL(s.price, 0)
                    ELSE 0
                END,
                -- Foil rate: only if qty * foilprice > 0, with original fallback rules
                CASE
                    WHEN (
                        IFNULL(c.foil, 0) *
                        CASE
                            WHEN s.price_foil IS NOT NULL THEN s.price_foil
                            WHEN s.price_foil IS NULL
                                 AND s.foil = 1
                                 AND c.foil IS NOT NULL
                                 AND c.foil > 0 THEN IFNULL(s.price, 0)
                            ELSE 0
                        END
                    ) > 0
                    THEN
                        CASE
                            WHEN s.price_foil IS NOT NULL THEN s.price_foil
                            WHEN s.price_foil IS NULL
                                 AND s.foil = 1
                                 AND c.foil IS NOT NULL
                                 AND c.foil > 0 THEN IFNULL(s.price, 0)
                            ELSE 0
                        END
                    ELSE 0
                END,
                -- Etched rate: only if qty * etchedprice > 0, with original fallback rules
                CASE
                    WHEN (
                        IFNULL(c.etched, 0) *
                        CASE
                            WHEN s.price_etched IS NOT NULL THEN s.price_etched
                            WHEN s.price_etched IS NULL
                                 AND c.etched IS NOT NULL
                                 AND c.etched > 0 THEN IFNULL(s.price, 0)
                            ELSE 0
                        END
                    ) > 0
                    THEN
                        CASE
                            WHEN s.price_etched IS NOT NULL THEN s.price_etched
                            WHEN s.price_etched IS NULL
                                 AND c.etched IS NOT NULL
                                 AND c.etched > 0 THEN IFNULL(s.price, 0)
                            ELSE 0
                        END
                    ELSE 0
                END
            )
            WHERE c.qty_total > 0
              AND c.id = ?
        ";

            $stmt = $this->db->prepare($query);
            if ($stmt) :
                $stmt->bind_param("s", $cardId);

                $start = microtime(true);
                $exec = $stmt->execute();
                $duration = microtime(true) - $start;

                $this->message->logMessage(
                    '[DEBUG]',
                    'updateCollectionValues single-card SQL runtime: '
                    . number_format($duration, 6) . 's'
                );

                if ($exec === false) :
                    $this->db->rollback();
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                        . ' Function ' . __FUNCTION__
                        . ': SQL: ' . $this->db->error
                    );
                endif;

                $i = $this->db->affected_rows;
                $stmt->close();

                if (!$this->db->commit()) :
                    throw new \Exception(
                        '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                        . ' Function ' . __FUNCTION__
                        . ': Commit failed: ' . $this->db->error
                    );
                endif;

                $this->message->logMessage(
                    '[NOTICE]',
                    "Value update completed for single card $cardId (rows affected: $i)"
                );
                return $i;
            else :
                $this->db->rollback();
                throw new \Exception(
                    '[ERROR]' . basename(__FILE__) . ' ' . __LINE__
                    . ' Function ' . __FUNCTION__
                    . ': Preparing SQL: ' . $this->db->error
                );
            endif;
        endif;
    }
}
