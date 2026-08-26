<?php

/*
Version:     1.1
Date:        26/08/26
Name:        ScryfallCardRecordMapper.php
Purpose:     Normalises a Scryfall card record into cards_scry import fields.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

class ScryfallCardRecordMapper
{
    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function map(array $value, ?int $timestamp = null): array
    {
        $fields = self::emptyFields();
        $fields['time'] = $timestamp ?? time();

        $fields['id'] = $value["id"] ?? null;
        $fields['oracle_id'] = $value["oracle_id"] ?? null;
        $fields['tcgplayer_id'] = $value["tcgplayer_id"] ?? null;
        $fields['name'] = $value["name"] ?? null;
        $fields['printed_name'] = $value["printed_name"] ?? null;
        $fields['flavor_name'] = $value["flavor_name"] ?? null;
        $fields['lang'] = $value["lang"] ?? null;
        $fields['released_at'] = $value["released_at"] ?? null;
        $fields['uri'] = $value["uri"] ?? null;
        $fields['scryfall_uri'] = $value["scryfall_uri"] ?? null;
        $fields['layout'] = $value["layout"] ?? null;
        $fields['image_uri'] = self::preferredImageUri($value["image_uris"] ?? null);
        $fields['illustration_id'] = $value["illustration_id"] ?? null;
        $fields['mana_cost'] = $value["mana_cost"] ?? null;
        $fields['cmc'] = $value["cmc"] ?? null;
        $fields['type_line'] = $value["type_line"] ?? null;
        $fields['oracle_text'] = $value["oracle_text"] ?? null;
        $fields['printed_type_line'] = self::nonEmpty($value["printed_type_line"] ?? null);
        $fields['printed_text'] = self::nonEmpty($value["printed_text"] ?? null);
        $fields['power'] = $value["power"] ?? null;
        $fields['toughness'] = $value["toughness"] ?? null;
        $fields['loyalty'] = $value["loyalty"] ?? null;
        $fields['reserved'] = $value["reserved"] ?? null;
        $fields['foil'] = $value["foil"] ?? null;
        $fields['nonfoil'] = $value["nonfoil"] ?? null;
        $fields['oversized'] = $value["oversized"] ?? null;
        $fields['promo'] = $value["promo"] ?? null;
        $fields['set_id'] = $value["set_id"] ?? null;
        $fields['set_code'] = $value["set"] ?? null;
        $fields['set_name'] = $value["set_name"] ?? null;
        $fields['collector_number'] = $value["collector_number"] ?? null;
        $fields['rarity'] = $value["rarity"] ?? null;
        $fields['flavor_text'] = $value["flavor_text"] ?? null;
        $fields['card_back_id'] = $value["card_back_id"] ?? null;
        $fields['artist'] = $value["artist"] ?? null;
        $fields['gatherer_uri'] = $value["related_uris"]["gatherer"] ?? null;

        self::mapLegalities($fields, $value);
        self::mapCardFaces($fields, $value['card_faces'] ?? []);
        self::mapAllParts($fields, $value['all_parts'] ?? []);
        self::mapMultiverseIds($fields, $value['multiverse_ids'] ?? []);
        self::mapDerivedStats($fields, $value);
        self::mapJsonFields($fields, $value);
        self::mapPrices($fields, $value["prices"] ?? []);
        self::mapCollectorNumber($fields, $value);
        self::mapHashes($fields);

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyFields(): array
    {
        $fields = [
            'id' => null,
            'oracle_id' => null,
            'tcgplayer_id' => null,
            'multi_1' => null,
            'multi_2' => null,
            'name' => null,
            'printed_name' => null,
            'flavor_name' => null,
            'lang' => null,
            'released_at' => null,
            'uri' => null,
            'scryfall_uri' => null,
            'layout' => null,
            'image_uri' => null,
            'illustration_id' => null,
            'mana_cost' => null,
            'cmc' => null,
            'type_line' => null,
            'oracle_text' => null,
            'printed_type_line' => null,
            'printed_text' => null,
            'power' => null,
            'toughness' => null,
            'loyalty' => null,
            'colors' => null,
            'color_identity' => null,
            'keywords' => null,
            'produced_mana' => null,
            'legality_standard' => null,
            'legality_pioneer' => null,
            'legality_modern' => null,
            'legality_legacy' => null,
            'legality_pauper' => null,
            'legality_vintage' => null,
            'legality_commander' => null,
            'legality_alchemy' => null,
            'legality_historic' => null,
            'reserved' => null,
            'foil' => null,
            'nonfoil' => null,
            'oversized' => null,
            'promo' => null,
            'set_id' => null,
            'game_types' => null,
            'finishes' => null,
            'promo_types' => null,
            'set_code' => null,
            'set_name' => null,
            'number_int' => null,
            'collector_number' => null,
            'rarity' => null,
            'flavor_text' => null,
            'card_back_id' => null,
            'artist' => null,
            'price_usd' => null,
            'price_usd_foil' => null,
            'price_usd_etched' => null,
            'gatherer_uri' => null,
            'time' => null,
            'maxpower' => null,
            'minpower' => null,
            'maxtoughness' => null,
            'mintoughness' => null,
            'maxloyalty' => null,
            'minloyalty' => null,
            'price_sort' => null,
            'content_hash' => null,
            'price_hash' => null,
        ];

        foreach ([1, 2] as $faceNumber) :
            foreach (self::faceFields() as $field) :
                $fields["{$field}_{$faceNumber}"] = null;
            endforeach;
        endforeach;

        for ($partNumber = 1; $partNumber <= 7; $partNumber++) :
            $fields["id_p{$partNumber}"] = null;
            $fields["component_p{$partNumber}"] = null;
            $fields["name_p{$partNumber}"] = null;
            $fields["type_line_p{$partNumber}"] = null;
            $fields["uri_p{$partNumber}"] = null;
        endfor;

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    private static function faceFields(): array
    {
        return [
            'name',
            'printed_name',
            'flavor_name',
            'manacost',
            'power',
            'toughness',
            'loyalty',
            'type',
            'printed_type',
            'ability',
            'printed_text',
            'colour',
            'artist',
            'flavor',
            'image',
            'illustration_id',
            'cmc',
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $value
     */
    private static function mapLegalities(array &$fields, array $value): void
    {
        $legalities = $value["legalities"] ?? [];
        $fields['legality_standard'] = $legalities["standard"] ?? null;
        $fields['legality_pioneer'] = $legalities["pioneer"] ?? null;
        $fields['legality_modern'] = $legalities["modern"] ?? null;
        $fields['legality_legacy'] = $legalities["legacy"] ?? null;
        $fields['legality_pauper'] = $legalities["pauper"] ?? null;
        $fields['legality_vintage'] = $legalities["vintage"] ?? null;
        $fields['legality_commander'] = $legalities["commander"] ?? null;
        $fields['legality_alchemy'] = $legalities["alchemy"] ?? null;
        $fields['legality_historic'] = $legalities["historic"] ?? null;
    }

    /**
     * @param array<string, mixed> $fields
     * @param mixed $cardFaces
     */
    private static function mapCardFaces(array &$fields, mixed $cardFaces): void
    {
        if (!is_array($cardFaces) || $cardFaces === []) :
            return;
        endif;

        $faceLoop = 1;
        foreach ($cardFaces as $face) :
            if (!is_array($face)) :
                continue;
            endif;
            if (isset($face["name"])) :
                $fields["name_{$faceLoop}"] = $face["name"];
            endif;
            if (isset($face["printed_name"])) :
                $fields["printed_name_{$faceLoop}"] = $face["printed_name"];
            endif;
            if (isset($face["flavor_name"])) :
                $fields["flavor_name_{$faceLoop}"] = $face["flavor_name"];
            endif;
            if (isset($face["mana_cost"])) :
                $fields["manacost_{$faceLoop}"] = $face["mana_cost"];
            endif;
            if (isset($face["power"])) :
                $fields["power_{$faceLoop}"] = $face["power"];
            endif;
            if (isset($face["toughness"])) :
                $fields["toughness_{$faceLoop}"] = $face["toughness"];
            elseif (isset($face["defense"])) :
                $fields["toughness_{$faceLoop}"] = $face["defense"];
            endif;
            if (isset($face["loyalty"])) :
                $fields["loyalty_{$faceLoop}"] = $face["loyalty"];
            endif;
            if (isset($face["type_line"])) :
                $fields["type_{$faceLoop}"] = $face["type_line"];
            endif;
            if (isset($face["printed_type_line"]) && $face["printed_type_line"] !== '') :
                $fields["printed_type_{$faceLoop}"] = $face["printed_type_line"];
            endif;
            if (isset($face["oracle_text"])) :
                $fields["ability_{$faceLoop}"] = $face["oracle_text"];
            endif;
            if (isset($face["printed_text"]) && $face["printed_text"] !== '') :
                $fields["printed_text_{$faceLoop}"] = $face["printed_text"];
            endif;
            if (isset($face["colors"])) :
                $fields["colour_{$faceLoop}"] = json_encode($face["colors"]);
            endif;
            if (isset($face["artist"])) :
                $fields["artist_{$faceLoop}"] = $face["artist"];
            endif;
            if (isset($face["flavor_text"])) :
                $fields["flavor_{$faceLoop}"] = $face["flavor_text"];
            endif;
            $faceImageUri = self::preferredImageUri($face["image_uris"] ?? null);
            if ($faceImageUri !== null) :
                $fields["image_{$faceLoop}"] = $faceImageUri;
            endif;
            if (isset($face["illustration_id"])) :
                $fields["illustration_id_{$faceLoop}"] = $face["illustration_id"];
            endif;
            if (isset($face["cmc"])) :
                $fields["cmc_{$faceLoop}"] = $face["cmc"];
            endif;
            $faceLoop++;
            if ($faceLoop > 2) :
                break;
            endif;
        endforeach;
    }

    /**
     * @param array<string, mixed> $fields
     * @param mixed $allParts
     */
    private static function mapAllParts(array &$fields, mixed $allParts): void
    {
        if (!is_array($allParts) || $allParts === []) :
            return;
        endif;

        $partLoop = 1;
        foreach ($allParts as $part) :
            if (!is_array($part) || !isset($part["component"]) || $part["component"] === "combo_piece") :
                continue;
            endif;
            if (isset($part["id"])) :
                $fields["id_p{$partLoop}"] = $part["id"];
            endif;
            if (isset($part["component"])) :
                $fields["component_p{$partLoop}"] = $part["component"];
            endif;
            if (isset($part["name"])) :
                $fields["name_p{$partLoop}"] = $part["name"];
            endif;
            if (isset($part["type_line"])) :
                $fields["type_line_p{$partLoop}"] = $part["type_line"];
            endif;
            if (isset($part["uri"])) :
                $fields["uri_p{$partLoop}"] = $part["uri"];
            endif;
            $partLoop++;
            if ($partLoop > 7) :
                break;
            endif;
        endforeach;
    }

    /**
     * @param array<string, mixed> $fields
     * @param mixed $multiverseIds
     */
    private static function mapMultiverseIds(array &$fields, mixed $multiverseIds): void
    {
        if (!is_array($multiverseIds)) :
            return;
        endif;

        $multiverseLoop = 1;
        foreach ($multiverseIds as $multiverseId) :
            $fields["multi_{$multiverseLoop}"] = $multiverseId;
            $multiverseLoop++;
            if ($multiverseLoop > 2) :
                break;
            endif;
        endforeach;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $value
     */
    private static function mapDerivedStats(array &$fields, array $value): void
    {
        $powerValues = self::numericValues($value['power'] ?? null, $fields['power_1'], $fields['power_2']);
        if ($powerValues !== []) :
            $fields['maxpower'] = max($powerValues);
            $fields['minpower'] = min($powerValues);
        endif;

        $toughnessValues = self::numericValues(
            $value['toughness'] ?? null,
            $fields['toughness_1'],
            $fields['toughness_2']
        );
        if ($toughnessValues !== []) :
            $fields['maxtoughness'] = max($toughnessValues);
            $fields['mintoughness'] = min($toughnessValues);
        endif;

        $loyaltyValues = self::numericValues($value['loyalty'] ?? null, $fields['loyalty_1'], $fields['loyalty_2']);
        if ($loyaltyValues !== []) :
            $fields['maxloyalty'] = max($loyaltyValues);
            $fields['minloyalty'] = min($loyaltyValues);
        endif;
    }

    /**
     * @return array<int, int>
     */
    private static function numericValues(mixed ...$values): array
    {
        $numericValues = [];
        foreach ($values as $value) :
            if ($value !== null) :
                $numericValues[] = (int) $value;
            endif;
        endforeach;

        return $numericValues;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $value
     */
    private static function mapJsonFields(array &$fields, array $value): void
    {
        $fields['colors'] = isset($value["colors"]) ? json_encode($value["colors"]) : null;
        $fields['game_types'] = isset($value["games"]) ? json_encode($value["games"]) : null;
        $fields['promo_types'] = isset($value["promo_types"]) ? json_encode($value["promo_types"]) : null;
        $fields['finishes'] = isset($value["finishes"]) ? json_encode($value["finishes"]) : null;
        $fields['color_identity'] = isset($value["color_identity"]) ? json_encode($value["color_identity"]) : null;
        $fields['keywords'] = isset($value["keywords"]) ? json_encode($value["keywords"]) : null;
        $fields['produced_mana'] = isset($value["produced_mana"]) ? json_encode($value["produced_mana"]) : null;
    }

    /**
     * @param array<string, mixed> $fields
     * @param mixed $prices
     */
    private static function mapPrices(array &$fields, mixed $prices): void
    {
        $prices = is_array($prices) ? $prices : [];
        $fields['price_usd'] = $prices['usd'] ?? null;
        $fields['price_usd_foil'] = $prices['usd_foil'] ?? null;
        $fields['price_usd_etched'] = $prices['usd_etched'] ?? null;
        $priceUsd = $fields['price_usd'];
        $priceUsdFoil = $fields['price_usd_foil'];
        $priceUsdEtched = $fields['price_usd_etched'];

        if ($priceUsdFoil === null && $priceUsd === null && $priceUsdEtched === null) :
            $fields['price_sort'] = null;
        elseif ($priceUsdFoil === null && $priceUsdEtched === null) :
            $fields['price_sort'] = $priceUsd;
        elseif ($priceUsd === null && $priceUsdEtched === null) :
            $fields['price_sort'] = $priceUsdFoil;
        elseif ($priceUsdFoil === null && $priceUsd === null) :
            $fields['price_sort'] = $priceUsdEtched;
        elseif ($priceUsd === null) :
            $fields['price_sort'] = min($priceUsdEtched, $priceUsdFoil);
        elseif ($priceUsdFoil === null) :
            $fields['price_sort'] = min($priceUsdEtched, $priceUsd);
        elseif ($priceUsdEtched === null) :
            $fields['price_sort'] = min($priceUsd, $priceUsdFoil);
        else :
            $fields['price_sort'] = min($priceUsd, $priceUsdFoil, $priceUsdEtched);
        endif;
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $value
     */
    private static function mapCollectorNumber(array &$fields, array $value): void
    {
        if (!isset($value["collector_number"])) :
            return;
        endif;

        $collectorNumber = $value["collector_number"];
        if (isset($value["layout"]) && $value["layout"] === 'meld') :
            $collectorNumber = str_replace('a', '', $collectorNumber);
            $collectorNumber = str_replace('b', '', $collectorNumber);
        endif;

        $collectorNumber = str_replace('-', '', $collectorNumber);
        $collectorNumber = str_replace('a', '1', $collectorNumber);
        $collectorNumber = str_replace('b', '2', $collectorNumber);
        $collectorNumber = str_replace('c', '3', $collectorNumber);
        $collectorNumber = str_replace('d', '4', $collectorNumber);
        $collectorNumber = str_replace('e', '5', $collectorNumber);
        $collectorNumber = str_replace('f', '6', $collectorNumber);
        $collectorNumber = str_replace('g', '7', $collectorNumber);
        $collectorNumber = str_replace('h', '8', $collectorNumber);
        $collectorNumber = str_replace('E', '', $collectorNumber);
        $collectorNumber = str_replace('★', '', $collectorNumber);
        $collectorNumber = str_replace('*', '', $collectorNumber);
        $collectorNumber = str_replace('†', '', $collectorNumber);
        $collectorNumber = str_replace('U', '', $collectorNumber);

        if (substr($collectorNumber, strlen($collectorNumber) - 1) === 's') :
            $collectorNumber = str_replace('s', '', $collectorNumber);
            if (ctype_digit($collectorNumber)) :
                $collectorNumber = (int) $collectorNumber + 5000;
            endif;
        endif;

        if (substr((string) $collectorNumber, strlen((string) $collectorNumber) - 1) === 'p') :
            $collectorNumber = str_replace('p', '', (string) $collectorNumber);
        endif;

        $fields['number_int'] = (int) $collectorNumber;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function mapHashes(array &$fields): void
    {
        $contentPayload = json_encode(
            self::contentHashData($fields),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $fields['content_hash'] = sha1($contentPayload === false ? '' : $contentPayload);

        $pricePayload = json_encode(
            [
                $fields['price_usd'],
                $fields['price_usd_foil'],
                $fields['price_usd_etched'],
                $fields['price_sort'],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $fields['price_hash'] = sha1($pricePayload === false ? '' : $pricePayload);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<int, mixed>
     */
    private static function contentHashData(array $fields): array
    {
        return [
            $fields['id'],
            $fields['oracle_id'],
            $fields['tcgplayer_id'],
            $fields['multi_1'],
            $fields['multi_2'],
            $fields['name'],
            $fields['printed_name'],
            $fields['flavor_name'],
            $fields['lang'],
            $fields['released_at'],
            $fields['uri'],
            $fields['scryfall_uri'],
            $fields['layout'],
            $fields['image_uri'],
            $fields['illustration_id'],
            $fields['mana_cost'],
            $fields['cmc'],
            $fields['type_line'],
            $fields['oracle_text'],
            $fields['printed_type_line'],
            $fields['printed_text'],
            $fields['power'],
            $fields['toughness'],
            $fields['loyalty'],
            $fields['colors'],
            $fields['color_identity'],
            $fields['keywords'],
            $fields['produced_mana'],
            $fields['legality_standard'],
            $fields['legality_pioneer'],
            $fields['legality_modern'],
            $fields['legality_legacy'],
            $fields['legality_pauper'],
            $fields['legality_vintage'],
            $fields['legality_commander'],
            $fields['legality_alchemy'],
            $fields['legality_historic'],
            $fields['reserved'],
            $fields['foil'],
            $fields['nonfoil'],
            $fields['oversized'],
            $fields['promo'],
            $fields['set_id'],
            $fields['game_types'],
            $fields['finishes'],
            $fields['promo_types'],
            $fields['set_code'],
            $fields['set_name'],
            $fields['number_int'],
            $fields['collector_number'],
            $fields['rarity'],
            $fields['flavor_text'],
            $fields['card_back_id'],
            $fields['artist'],
            $fields['gatherer_uri'],
            $fields['name_1'],
            $fields['manacost_1'],
            $fields['power_1'],
            $fields['toughness_1'],
            $fields['loyalty_1'],
            $fields['type_1'],
            $fields['printed_type_1'],
            $fields['ability_1'],
            $fields['printed_text_1'],
            $fields['colour_1'],
            $fields['artist_1'],
            $fields['flavor_1'],
            $fields['image_1'],
            $fields['illustration_id_1'],
            $fields['cmc_1'],
            $fields['printed_name_1'],
            $fields['flavor_name_1'],
            $fields['name_2'],
            $fields['manacost_2'],
            $fields['power_2'],
            $fields['toughness_2'],
            $fields['loyalty_2'],
            $fields['type_2'],
            $fields['printed_type_2'],
            $fields['ability_2'],
            $fields['printed_text_2'],
            $fields['colour_2'],
            $fields['artist_2'],
            $fields['flavor_2'],
            $fields['image_2'],
            $fields['illustration_id_2'],
            $fields['cmc_2'],
            $fields['printed_name_2'],
            $fields['flavor_name_2'],
            $fields['id_p1'],
            $fields['component_p1'],
            $fields['name_p1'],
            $fields['type_line_p1'],
            $fields['uri_p1'],
            $fields['id_p2'],
            $fields['component_p2'],
            $fields['name_p2'],
            $fields['type_line_p2'],
            $fields['uri_p2'],
            $fields['id_p3'],
            $fields['component_p3'],
            $fields['name_p3'],
            $fields['type_line_p3'],
            $fields['uri_p3'],
            $fields['id_p4'],
            $fields['component_p4'],
            $fields['name_p4'],
            $fields['type_line_p4'],
            $fields['uri_p4'],
            $fields['id_p5'],
            $fields['component_p5'],
            $fields['name_p5'],
            $fields['type_line_p5'],
            $fields['uri_p5'],
            $fields['id_p6'],
            $fields['component_p6'],
            $fields['name_p6'],
            $fields['type_line_p6'],
            $fields['uri_p6'],
            $fields['id_p7'],
            $fields['component_p7'],
            $fields['name_p7'],
            $fields['type_line_p7'],
            $fields['uri_p7'],
            $fields['maxpower'],
            $fields['minpower'],
            $fields['maxtoughness'],
            $fields['mintoughness'],
            $fields['maxloyalty'],
            $fields['minloyalty'],
        ];
    }

    private static function preferredImageUri(mixed $imageUris): ?string
    {
        if (!is_array($imageUris)) :
            return null;
        endif;

        foreach (['grid', 'normal'] as $key) :
            if (isset($imageUris[$key]) && is_string($imageUris[$key]) && $imageUris[$key] !== '') :
                return $imageUris[$key];
            endif;
        endforeach;

        return null;
    }

    private static function nonEmpty(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
