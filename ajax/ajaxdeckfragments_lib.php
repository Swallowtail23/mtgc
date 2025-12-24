<?php

/*
Version:     1.5
Date:        24/12/25
Name:        ajaxdeckfragments_lib.php
Purpose:     Fragment rendering helpers for deck detail AJAX updates.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

function deckdetailRenderFragments($requestedFragments, $fragmentMapOverride = null)
{
    if (isset($GLOBALS) && is_array($GLOBALS)) :
        extract($GLOBALS, EXTR_SKIP);
    endif;
    $fragmentMap = $fragmentMapOverride ?? [
        'colour_identity' => '../includes/fragments/deckdetail_colour_identity.php',
        'decklist' => '../includes/fragments/deckdetail_decklist.php',
        'warnings' => '../includes/fragments/deckdetail_warnings.php',
        'mana_value' => '../includes/fragments/deckdetail_mana_value.php',
        'mana_costs' => '../includes/fragments/deckdetail_mana_costs.php',
        'deck_value' => '../includes/fragments/deckdetail_deck_value.php',
        'deck_lists' => '../includes/fragments/deckdetail_deck_lists.php',
        'export_list' => '../includes/fragments/deckdetail_export_list.php',
        'missing' => '../includes/fragments/deckdetail_missing.php',
        'buy_missing' => '../includes/fragments/deckdetail_buy_missing.php',
        'random_draw' => '../includes/fragments/deckdetail_random_draw.php'
    ];

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

function deckdetailBuildFragmentResponse($requestedFragments, $fragmentMapOverride = null)
{
    return [
        'success' => true,
        'fragments' => deckdetailRenderFragments($requestedFragments, $fragmentMapOverride)
    ];
}
