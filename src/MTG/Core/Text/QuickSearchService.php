<?php

/*
Version:     1.0
Date:        27/03/26
Name:        QuickSearchService.php
Purpose:     Builds and executes quick-search queries for header AJAX search.
Notes:       -
Author:      Codex
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Text;

use MTG\Core\Message;

class QuickSearchService
{
    /** @var array<int,string> */
    private const MATCHED_NAME_FIELDS = [
        'printed_name',
        'flavor_name',
        'f1_name',
        'f1_printed_name',
        'f1_flavor_name',
        'f2_name',
        'f2_printed_name',
        'f2_flavor_name'
    ];

    /** @var array<int,string> */
    private const SEARCHABLE_FIELDS = [
        'printed_name',
        'flavor_name',
        'name',
        'f1_printed_name',
        'f1_flavor_name',
        'f1_name',
        'f2_printed_name',
        'f2_flavor_name',
        'f2_name'
    ];

    private \mysqli $db;
    private Message $msg;

    public function __construct(\mysqli $db, Message $msg)
    {
        $this->db = $db;
        $this->msg = $msg;
    }

    /**
     * @return array{query:string,params:array<int,string>}
     */
    public static function buildSearchSpec(
        string $typed,
        string $searchString,
        string $setcode,
        string $number,
        bool $primaryOnly
    ): array {
        $matchedNameWhenClauses = [];
        $matchedNameParams = [];
        foreach (self::MATCHED_NAME_FIELDS as $fieldName) :
            $matchedNameWhenClauses[] = "WHEN {$fieldName} LIKE ? THEN {$fieldName}";
            $matchedNameParams[] = $searchString;
        endforeach;

        $matchedPositionWhenClauses = [];
        $matchedPositionParams = [];
        foreach (self::SEARCHABLE_FIELDS as $fieldName) :
            $matchedPositionWhenClauses[] = "WHEN {$fieldName} LIKE ? THEN LOCATE(?, {$fieldName})";
            $matchedPositionParams[] = $searchString;
            $matchedPositionParams[] = $typed;
        endforeach;

        $whereLikeClauses = [];
        $whereLikeParams = [];
        foreach (self::SEARCHABLE_FIELDS as $fieldName) :
            $whereLikeClauses[] = "{$fieldName} LIKE ?";
            $whereLikeParams[] = $searchString;
        endforeach;

        $filterParams = array_merge(
            $whereLikeParams,
            [
                $setcode,
                $setcode,
                $number,
                $number
            ]
        );
        $searchParams = array_merge($matchedNameParams, $matchedPositionParams, $filterParams);
        $searchQuery = "SELECT
                id,
                setcode,
                number_import,
                lang,
                CASE
                    " . implode("
                    ", $matchedNameWhenClauses) . "
                    ELSE name
                END AS matched_name,
                CASE
                    " . implode("
                    ", $matchedPositionWhenClauses) . "
                    ELSE 0
                END AS matched_position,
                release_date
            FROM cards_scry
            WHERE
                (
                    " . implode("
                    OR ", $whereLikeClauses) . "
                )
                AND (setcode LIKE ? OR ? = '')
                AND (number_import LIKE ? OR ? = '')";

        if ($primaryOnly) :
            $searchQuery .= "
                AND (primary_card = 1)";
        endif;

        $searchQuery .= "
            ORDER BY release_date DESC, name ASC
            LIMIT 20";

        return [
            'query' => $searchQuery,
            'params' => $searchParams
        ];
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>,used_fallback:bool}
     */
    public function search(
        string $typed,
        string $searchString,
        string $setcode,
        string $number
    ): array {
        $searchRows = $this->runSearchSpec(
            self::buildSearchSpec($typed, $searchString, $setcode, $number, true),
            true
        );
        $usedFallbackSearch = false;

        if (empty($searchRows) && mb_strlen($typed) >= 3) :
            $usedFallbackSearch = true;
            $this->msg->logMessage(
                '[DEBUG]',
                "Ajax header search found no primary-card matches for '$typed'; retrying without primary_card filter"
            );
            $searchRows = $this->runSearchSpec(
                self::buildSearchSpec($typed, $searchString, $setcode, $number, false),
                false
            );
        endif;

        return [
            'rows' => $searchRows,
            'used_fallback' => $usedFallbackSearch
        ];
    }

    /**
     * @param array{query:string,params:array<int,string>} $searchSpec
     * @return array<int,array<string,mixed>>
     */
    private function runSearchSpec(array $searchSpec, bool $primaryOnly): array
    {
        $this->msg->logMessage(
            '[DEBUG]',
            'Ajax header search running with '
            . ($primaryOnly ? 'primary-card-only filter' : 'all-language fallback')
        );
        $result = $this->db->execute_query($searchSpec['query'], $searchSpec['params']);
        if ($result === false) :
            throw new \Exception(
                "[ERROR]" . basename(__FILE__) . " " . __LINE__ . ": SQL failure: " . $this->db->error
            );
        endif;

        /** @var array<int,array<string,mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        return $rows;
    }
}
