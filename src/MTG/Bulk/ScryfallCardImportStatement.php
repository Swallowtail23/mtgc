<?php

/*
Version:     1.0
Date:        08/07/26
Name:        ScryfallCardImportStatement.php
Purpose:     Defines cards_scry import SQL and bind field order for Scryfall card imports.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

class ScryfallCardImportStatement
{
    private const CONTENT_HASHED_COLUMNS = [
        'id',
        'oracle_id',
        'tcgplayer_id',
        'multiverse',
        'multiverse2',
        'name',
        'printed_name',
        'flavor_name',
        'lang',
        'release_date',
        'api_uri',
        'scryfall_uri',
        'layout',
        'image_uri',
        'illustration_id',
        'manacost',
        'cmc',
        'type',
        'printed_type_line',
        'ability',
        'printed_text',
        'power',
        'toughness',
        'loyalty',
        'color',
        'color_identity',
        'keywords',
        'generatedmana',
        'legalitystandard',
        'legalitypioneer',
        'legalitymodern',
        'legalitylegacy',
        'legalitypauper',
        'legalityvintage',
        'legalitycommander',
        'legalityalchemy',
        'legalityhistoric',
        'reserved',
        'foil',
        'nonfoil',
        'oversized',
        'promo',
        'set_id',
        'game_types',
        'finishes',
        'promo_types',
        'setcode',
        'set_name',
        'number',
        'number_import',
        'rarity',
        'flavor',
        'backid',
        'artist',
        'gatherer_uri',
        'f1_name',
        'f1_manacost',
        'f1_power',
        'f1_toughness',
        'f1_loyalty',
        'f1_type',
        'f1_printed_type_line',
        'f1_ability',
        'f1_printed_text',
        'f1_colour',
        'f1_artist',
        'f1_flavor',
        'f1_image_uri',
        'f1_illustration_id',
        'f1_cmc',
        'f1_printed_name',
        'f1_flavor_name',
        'f2_name',
        'f2_manacost',
        'f2_power',
        'f2_toughness',
        'f2_loyalty',
        'f2_type',
        'f2_printed_type_line',
        'f2_ability',
        'f2_printed_text',
        'f2_colour',
        'f2_artist',
        'f2_flavor',
        'f2_image_uri',
        'f2_illustration_id',
        'f2_cmc',
        'f2_printed_name',
        'f2_flavor_name',
        'p1_id',
        'p1_component',
        'p1_name',
        'p1_type_line',
        'p1_uri',
        'p2_id',
        'p2_component',
        'p2_name',
        'p2_type_line',
        'p2_uri',
        'p3_id',
        'p3_component',
        'p3_name',
        'p3_type_line',
        'p3_uri',
        'p4_id',
        'p4_component',
        'p4_name',
        'p4_type_line',
        'p4_uri',
        'p5_id',
        'p5_component',
        'p5_name',
        'p5_type_line',
        'p5_uri',
        'p6_id',
        'p6_component',
        'p6_name',
        'p6_type_line',
        'p6_uri',
        'p7_id',
        'p7_component',
        'p7_name',
        'p7_type_line',
        'p7_uri',
        'maxpower',
        'minpower',
        'maxtoughness',
        'mintoughness',
        'maxloyalty',
        'minloyalty',
    ];

    private const PRICE_HASHED_COLUMNS = [
        'price',
        'price_foil',
        'price_etched',
        'price_sort',
    ];

    private const FIELD_MAP = [
        ['id', 'id'],
        ['oracle_id', 'oracle_id'],
        ['tcgplayer_id', 'tcgplayer_id'],
        ['multiverse', 'multi_1'],
        ['multiverse2', 'multi_2'],
        ['name', 'name'],
        ['printed_name', 'printed_name'],
        ['flavor_name', 'flavor_name'],
        ['lang', 'lang'],
        ['release_date', 'released_at'],
        ['api_uri', 'uri'],
        ['scryfall_uri', 'scryfall_uri'],
        ['layout', 'layout'],
        ['image_uri', 'image_uri'],
        ['illustration_id', 'illustration_id'],
        ['manacost', 'mana_cost'],
        ['cmc', 'cmc'],
        ['type', 'type_line'],
        ['ability', 'oracle_text'],
        ['power', 'power'],
        ['toughness', 'toughness'],
        ['loyalty', 'loyalty'],
        ['color', 'colors'],
        ['color_identity', 'color_identity'],
        ['keywords', 'keywords'],
        ['generatedmana', 'produced_mana'],
        ['legalitystandard', 'legality_standard'],
        ['legalitypioneer', 'legality_pioneer'],
        ['legalitymodern', 'legality_modern'],
        ['legalitylegacy', 'legality_legacy'],
        ['legalitypauper', 'legality_pauper'],
        ['legalityvintage', 'legality_vintage'],
        ['legalitycommander', 'legality_commander'],
        ['legalityalchemy', 'legality_alchemy'],
        ['legalityhistoric', 'legality_historic'],
        ['reserved', 'reserved'],
        ['foil', 'foil'],
        ['nonfoil', 'nonfoil'],
        ['oversized', 'oversized'],
        ['promo', 'promo'],
        ['set_id', 'set_id'],
        ['game_types', 'game_types'],
        ['finishes', 'finishes'],
        ['promo_types', 'promo_types'],
        ['setcode', 'set_code'],
        ['set_name', 'set_name'],
        ['number', 'number_int'],
        ['number_import', 'collector_number'],
        ['rarity', 'rarity'],
        ['flavor', 'flavor_text'],
        ['backid', 'card_back_id'],
        ['artist', 'artist'],
        ['price', 'price_usd'],
        ['price_foil', 'price_usd_foil'],
        ['price_etched', 'price_usd_etched'],
        ['gatherer_uri', 'gatherer_uri'],
        ['updatetime', 'time'],
        ['f1_name', 'name_1'],
        ['f1_manacost', 'manacost_1'],
        ['f1_power', 'power_1'],
        ['f1_toughness', 'toughness_1'],
        ['f1_loyalty', 'loyalty_1'],
        ['f1_type', 'type_1'],
        ['f1_ability', 'ability_1'],
        ['f1_colour', 'colour_1'],
        ['f1_artist', 'artist_1'],
        ['f1_flavor', 'flavor_1'],
        ['f1_image_uri', 'image_1'],
        ['f1_illustration_id', 'illustration_id_1'],
        ['f1_cmc', 'cmc_1'],
        ['f1_printed_name', 'printed_name_1'],
        ['f1_flavor_name', 'flavor_name_1'],
        ['f2_name', 'name_2'],
        ['f2_manacost', 'manacost_2'],
        ['f2_power', 'power_2'],
        ['f2_toughness', 'toughness_2'],
        ['f2_loyalty', 'loyalty_2'],
        ['f2_type', 'type_2'],
        ['f2_ability', 'ability_2'],
        ['f2_colour', 'colour_2'],
        ['f2_artist', 'artist_2'],
        ['f2_flavor', 'flavor_2'],
        ['f2_image_uri', 'image_2'],
        ['f2_illustration_id', 'illustration_id_2'],
        ['f2_cmc', 'cmc_2'],
        ['f2_printed_name', 'printed_name_2'],
        ['f2_flavor_name', 'flavor_name_2'],
        ['p1_id', 'id_p1'],
        ['p1_component', 'component_p1'],
        ['p1_name', 'name_p1'],
        ['p1_type_line', 'type_line_p1'],
        ['p1_uri', 'uri_p1'],
        ['p2_id', 'id_p2'],
        ['p2_component', 'component_p2'],
        ['p2_name', 'name_p2'],
        ['p2_type_line', 'type_line_p2'],
        ['p2_uri', 'uri_p2'],
        ['p3_id', 'id_p3'],
        ['p3_component', 'component_p3'],
        ['p3_name', 'name_p3'],
        ['p3_type_line', 'type_line_p3'],
        ['p3_uri', 'uri_p3'],
        ['p4_id', 'id_p4'],
        ['p4_component', 'component_p4'],
        ['p4_name', 'name_p4'],
        ['p4_type_line', 'type_line_p4'],
        ['p4_uri', 'uri_p4'],
        ['p5_id', 'id_p5'],
        ['p5_component', 'component_p5'],
        ['p5_name', 'name_p5'],
        ['p5_type_line', 'type_line_p5'],
        ['p5_uri', 'uri_p5'],
        ['p6_id', 'id_p6'],
        ['p6_component', 'component_p6'],
        ['p6_name', 'name_p6'],
        ['p6_type_line', 'type_line_p6'],
        ['p6_uri', 'uri_p6'],
        ['p7_id', 'id_p7'],
        ['p7_component', 'component_p7'],
        ['p7_name', 'name_p7'],
        ['p7_type_line', 'type_line_p7'],
        ['p7_uri', 'uri_p7'],
        ['maxpower', 'maxpower'],
        ['minpower', 'minpower'],
        ['maxtoughness', 'maxtoughness'],
        ['mintoughness', 'mintoughness'],
        ['maxloyalty', 'maxloyalty'],
        ['minloyalty', 'minloyalty'],
        ['printed_type_line', 'printed_type_line'],
        ['printed_text', 'printed_text'],
        ['f1_printed_type_line', 'printed_type_1'],
        ['f1_printed_text', 'printed_text_1'],
        ['f2_printed_type_line', 'printed_type_2'],
        ['f2_printed_text', 'printed_text_2'],
        ['price_sort', 'price_sort'],
        ['content_hash', 'content_hash'],
        ['price_hash', 'price_hash'],
        ['date_added', 'date_added'],
        ['primary_card', 'primary_card'],
    ];

    /**
     * @return array<int, string>
     */
    public static function insertColumns(): array
    {
        return array_column(self::FIELD_MAP, 0);
    }

    /**
     * @return array<int, string>
     */
    public static function bindFields(): array
    {
        $fields = array_column(self::FIELD_MAP, 1);
        $fields[] = 'primary_card_update';

        return $fields;
    }

    public static function bindTypes(): string
    {
        return str_repeat('s', count(self::bindFields()) - 2) . 'ii';
    }

    public static function insertSql(string $tableName): string
    {
        $columnsSql = implode(",\n                                ", self::insertColumns());
        $placeholdersSql = self::placeholders(count(self::insertColumns()), 40);
        $updatesSql = self::updatesSql();

        return sprintf(
            "INSERT INTO
                                `%s`
                                (%s
                                )
                            VALUES(
                                %s
                            )
                            ON DUPLICATE KEY UPDATE
                                %s
                            ",
            $tableName,
            $columnsSql,
            $placeholdersSql,
            $updatesSql
        );
    }

    public static function hashLookupSql(string $tableName): string
    {
        return sprintf(
            "SELECT content_hash, price_hash FROM `%s` WHERE id = ? LIMIT 1",
            $tableName
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialBindValues(string $date, int $primary): array
    {
        $bindValues = [];
        foreach (self::bindFields() as $field) :
            $bindValues[$field] = null;
        endforeach;

        $bindValues['date_added'] = $date;
        $bindValues['primary_card'] = $primary;
        $bindValues['primary_card_update'] = $primary;

        return $bindValues;
    }

    /**
     * @param array<string, mixed> $bindValues
     * @param array<string, mixed> $mappedCard
     */
    public static function applyMappedCard(array &$bindValues, array $mappedCard): void
    {
        foreach ($mappedCard as $field => $value) :
            if (array_key_exists($field, $bindValues)) :
                $bindValues[$field] = $value;
            endif;
        endforeach;
    }

    /**
     * @param array<string, mixed> $bindValues
     * @return array<int, mixed>
     */
    public static function orderedBindValues(array &$bindValues): array
    {
        $ordered = [];
        foreach (self::bindFields() as $field) :
            $ordered[] = &$bindValues[$field];
        endforeach;

        return $ordered;
    }

    private static function updatesSql(): string
    {
        $updates = [];
        foreach (self::CONTENT_HASHED_COLUMNS as $column) :
            $updates[] = self::hashedUpdateSql($column, 'content_hash');
        endforeach;
        foreach (self::PRICE_HASHED_COLUMNS as $column) :
            $updates[] = self::hashedUpdateSql($column, 'price_hash');
        endforeach;
        $updates[] = "updatetime = IF(
                                    NOT (content_hash <=> VALUES(content_hash))
                                        OR NOT (price_hash <=> VALUES(price_hash)),
                                    VALUES(updatetime),
                                    updatetime
                                )";
        $updates[] = self::hashedUpdateSql('content_hash', 'content_hash');
        $updates[] = self::hashedUpdateSql('price_hash', 'price_hash');
        $updates[] = 'primary_card = IF(?, 1, primary_card)';

        return implode(",\n                                ", $updates);
    }

    private static function hashedUpdateSql(string $column, string $hashColumn): string
    {
        return "$column = IF(NOT ($hashColumn <=> VALUES($hashColumn)), VALUES($column), $column)";
    }

    private static function placeholders(int $count, int $perLine): string
    {
        $placeholderLines = [];
        while ($count > 0) :
            $lineCount = min($perLine, $count);
            $placeholderLines[] = implode(',', array_fill(0, $lineCount, '?'));
            $count -= $lineCount;
        endwhile;

        return implode(",\n                                ", $placeholderLines);
    }
}
