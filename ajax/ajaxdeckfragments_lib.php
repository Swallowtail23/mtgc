<?php

/*
Version:     1.83
Date:        28/04/26
Name:        ajaxdeckfragments_lib.php
Purpose:     Fragment rendering helpers for deck detail AJAX updates.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

function deckdetailFragmentRegistry(): array
{
    return [
        [
            'key' => 'decklist',
            'id' => 'decklist-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_decklist.php',
            'default' => true
        ],
        [
            'key' => 'colour_identity',
            'id' => 'deck-colour-identity-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_colour_identity.php',
            'default' => true
        ],
        [
            'key' => 'warnings',
            'id' => 'deck-warnings-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_warnings.php',
            'default' => true
        ],
        [
            'key' => 'mana_value',
            'id' => 'deck-mana-value-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_mana_value.php',
            'default' => true
        ],
        [
            'key' => 'mana_costs',
            'id' => 'deck-mana-costs-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_mana_costs.php',
            'default' => true
        ],
        [
            'key' => 'deck_value',
            'id' => 'deck-value-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_deck_value.php',
            'default' => true
        ],
        [
            'key' => 'deck_lists',
            'id' => 'deck-lists-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_deck_lists.php',
            'default' => true
        ],
        [
            'key' => 'export_list',
            'id' => 'deck-export-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_export_list.php',
            'default' => true
        ],
        [
            'key' => 'missing',
            'id' => 'deck-missing-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_missing.php',
            'default' => true
        ],
        [
            'key' => 'buy_missing',
            'id' => 'deck-buy-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_buy_missing.php',
            'default' => true
        ],
        [
            'key' => 'random_draw',
            'id' => 'deck-random-draw-fragment',
            'include' => APP_ROOT . '/includes/fragments/deckdetail_random_draw.php',
            'default' => true
        ]
    ];
}

function deckdetailFragmentMap(?array $fragmentRegistry = null): array
{
    $fragmentRegistry = $fragmentRegistry ?? deckdetailFragmentRegistry();
    $fragmentMap = [];
    foreach ($fragmentRegistry as $entry) :
        $fragmentMap[$entry['key']] = $entry['include'];
    endforeach;
    return $fragmentMap;
}

function deckdetailDefaultFragments(?array $fragmentRegistry = null): array
{
    $fragmentRegistry = $fragmentRegistry ?? deckdetailFragmentRegistry();
    $defaults = [];
    foreach ($fragmentRegistry as $entry) :
        if (!empty($entry['default'])) :
            $defaults[] = $entry['key'];
        endif;
    endforeach;
    return $defaults;
}

function deckdetailFragmentTargets(?array $fragmentRegistry = null): array
{
    $fragmentRegistry = $fragmentRegistry ?? deckdetailFragmentRegistry();
    $targets = [];
    foreach ($fragmentRegistry as $entry) :
        $targets[$entry['key']] = $entry['id'];
    endforeach;
    return $targets;
}

function deckdetailRenderFragments(array $requestedFragments, ?array $fragmentMapOverride = null): array
{
    if (isset($GLOBALS) && is_array($GLOBALS)) :
        foreach ($GLOBALS as $key => $value) :
            if (!isset($$key)) :
                $$key = $value;
            endif;
        endforeach;
    endif;
    $fragmentMap = $fragmentMapOverride ?? deckdetailFragmentMap();

    $fragments = [];
    $requested = is_array($requestedFragments) ? array_values($requestedFragments) : [];
    $requested = array_values(array_unique($requested));
    if (!in_array('decklist', $requested, true)) :
        $requested[] = 'decklist';
    endif;
    $decklistIndex = array_search('decklist', $requested, true);
    if ($decklistIndex !== false) :
        unset($requested[$decklistIndex]);
        array_unshift($requested, 'decklist');
    endif;

    foreach ($requested as $fragmentKey) :
        if (!isset($fragmentMap[$fragmentKey])) :
            continue;
        endif;
        if (!isset($fragments[$fragmentKey])) :
            ob_start();
            include $fragmentMap[$fragmentKey];
            $fragments[$fragmentKey] = ob_get_clean();
        endif;
    endforeach;

    return $fragments;
}

function deckdetailBuildFragmentResponse(array $requestedFragments, ?array $fragmentMapOverride = null): array
{
    return [
        'success' => true,
        'fragments' => deckdetailRenderFragments($requestedFragments, $fragmentMapOverride)
    ];
}
