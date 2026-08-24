<?php

/*
Version:     1.0
Date:        25/08/26
Name:        CardNavigation.php
Purpose:     Builds set navigation queries for card detail pages.
Notes:       -
Author:      Codex
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Cards;

class CardNavigation
{
    /**
     * @return array{query:string,params:array<int,string>}
     */
    public static function buildQuerySpec(string $setCode, string $language, bool $primaryCard): array
    {
        $params = [$setCode];
        if ($primaryCard) :
            $scope = 'AND primary_card = 1';
        else :
            $scope = 'AND lang = ?';
            $params[] = $language;
        endif;

        if ($setCode === 'plst') :
            $order = "ORDER BY
                    (SELECT sets.release_date
                        FROM sets
                        WHERE sets.code = SUBSTRING(
                            cards_scry.number_import,
                            1,
                            LOCATE('-', cards_scry.number_import) - 1
                        )
                    ) DESC,
                    SUBSTRING(number_import, 1, LOCATE('-', number_import) - 1) ASC,
                    CAST(SUBSTRING(number_import FROM LOCATE('-', number_import) + 1) AS UNSIGNED) ASC,
                    primary_card DESC,
                    number ASC,
                    COALESCE(flavor_name, name) ASC,
                    id ASC";
        elseif ($setCode === 'sld') :
            $order = "ORDER BY
                    release_date DESC,
                    primary_card DESC,
                    number ASC,
                    CAST(REGEXP_REPLACE(number_import, '[[:alpha:]]', '') AS UNSIGNED) ASC,
                    number_import ASC,
                    COALESCE(flavor_name, name) ASC,
                    id ASC";
        else :
            $order = "ORDER BY
                    primary_card DESC,
                    number ASC,
                    release_date ASC,
                    CAST(REGEXP_REPLACE(number_import, '[[:alpha:]]', '') AS UNSIGNED) ASC,
                    number_import ASC,
                    COALESCE(flavor_name, name) ASC,
                    id ASC";
        endif;

        return [
            'query' => "SELECT id
                FROM cards_scry
                WHERE setcode = ?
                {$scope}
                {$order}",
            'params' => $params
        ];
    }
}
