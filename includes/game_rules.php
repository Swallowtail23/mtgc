<?php

/*
Version:     1.13
Date:        25/08/26
Name:        game_rules.php
Purpose:     Game rules arrays and constants.
Notes:       Add a new rule by defining it once below; it is returned automatically.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

if (!function_exists('mtg_game_rules')) :
    function mtg_game_rules(): array
    {
        /** How old must card data be to trigger automatic refresh, in hours **/

        $max_data_age_in_hours = 0.25; // Set age in hours here

        $seconds_in_hour = 3600;
        $max_card_data_age = $seconds_in_hour * $max_data_age_in_hours;

        /** Define card types and variables which require special treatment **/

        // Valid tribes
        $valid_tribe = array(
            "merfolk",
            "spider",
            "goblin",
            "treefolk",
            "sliver",
            "human",
            "zombie",
            "vampire",
            "elf"
        );

        // Valid search languages
        $search_langs = array(
            array(
                'code' => 'en',
                'pretty' => 'English'
            ),
            array(
                'code' => 'es',
                'pretty' => 'Spanish'
            ),
            array(
                'code' => 'fr',
                'pretty' => 'French'
            ),
            array(
                'code' => 'de',
                'pretty' => 'German'
            ),
            array(
                'code' => 'it',
                'pretty' => 'Italian'
            ),
            array(
                'code' => 'pt',
                'pretty' => 'Portuguese'
            ),
            array(
                'code' => 'ja',
                'pretty' => 'Japanese'
            ),
            array(
                'code' => 'ko',
                'pretty' => 'Korean'
            ),
            array(
                'code' => 'ru',
                'pretty' => 'Russian'
            ),
            array(
                'code' => 'zhs',
                'pretty' => 'Chinese (simplified)'
            ),
            array(
                'code' => 'zht',
                'pretty' => 'Chinese (traditional)'
            ),
            array(
                'code' => 'he',
                'pretty' => 'Hebrew'
            ),
            array(
                'code' => 'la',
                'pretty' => 'Latin'
            ),
            array(
                'code' => 'grc',
                'pretty' => 'Ancient Greek'
            ),
            array(
                'code' => 'ar',
                'pretty' => 'Arabic'
            ),
            array(
                'code' => 'sa',
                'pretty' => 'Sanskrit'
            ),
            array(
                'code' => 'ph',
                'pretty' => 'Phyrexian'
            ),
            array(
                'code' => 'qya',
                'pretty' => 'Quenya'
            ),
            array(
                'code' => 'dw',
                'pretty' => 'Dwarven'
            )
        );
        $search_langs_codes = array_column($search_langs, 'code');

        // Selectable currencies
        $currencies = array(
            array(
                'code' => 'zzz',
                'pretty' => 'None',
                'db' => null
            ),
            array(
                'code' => 'aud',
                'pretty' => 'Australian $',
                'db' => 'aud'
            ),
            array(
                'code' => 'cad',
                'pretty' => 'Canadian $',
                'db' => 'cad'
            ),
            array(
                'code' => 'eur',
                'pretty' => 'Euro €',
                'db' => 'eur'
            ),
            array(
                'code' => 'gbp',
                'pretty' => 'British £',
                'db' => 'gbp'
            ),
            array(
                'code' => 'jpy',
                'pretty' => 'Japanese ¥',
                'db' => 'jpy'
            ),
            array(
                'code' => 'nzd',
                'pretty' => 'New Zealand $',
                'db' => 'nzd'
            )
        );

        // Card layouts which get a flip button
        $flip_button_cards = array(
            'transform',
            'modal_dfc',
            'reversible_card',
            'double_faced_token',
            'battle'
        );

        // Card layouts which need two detail sections on card detail page
        $twoCardDetailSections = array(
            'transform',
            'modal_dfc',
            'reversible_card',
            'double_faced_token',
            'battle',
            'art_series'
        );

        // Two layouts, array to drive looking for face 1 content for primary card info on card detail page
        $layouts_double = array(
            'transform',
            'modal_dfc',
            'reversible_card',
            'double_faced_token',
            'battle',
            'adventure',
            'split',
            'flip'
        );

        // Token layouts
        $token_layouts = array(
            'double_faced_token',
            'token',
            'emblem'
        );

        // Layouts needing rotation
        $image90rotate = array(
            'split',
            'planar',
            'Battle — Siege'
        );

        // Commander deck types
        $commander_decktypes = array(
            'Commander',
            'Tiny Leader'
        );

        // Cards legal for multiples in Commander
        $commander_multiples = array(
            "Basic Land",
            "Basic Snow Land",
            "Token"
        );

        // E.g. Relentless Rats
        $any_quantity = array(
            "A deck can have any number of cards named"
        );

        // Commander variations
        // Check for abilities which allow card to be a commander
        $valid_commander_text = array(
            "can be your commander"
        );

        // Check for abilities which allow card to be second commander
        $second_commander_text = array(
            "Partner",
            "Friends forever",
            "Doctor's companion"
        );

        // Check for "Type" valid ONLY in second commander slot
        $second_commander_only_type = array(
            "Background"
        );

        // Selectable deck types on deck detail page
        $validtypes = array(
            'Commander',
            'Casual',
            'Tiny Leader',
            'Standard',
            'Modern',
            'Wishlist'
        );

        // Card layouts to NOT import in deck quick add routine
        $noQuickAddLayouts = array(
            'meld',
            'art_series'
        );

        // Cards with brackets contents in names (not currently needed or used, see inputInterpreter())
        $bracketsInNames = array(
            "cont'd",
            'Front Card',
            '2000',
            "Not the Urza's Legacy One",
            'minigame',
            'Bevy of Beebles',
            'Big Furry Monster',
            '1999',
            '2000',
            '2001',
            'Used',
            'Theme'
        );

        $importLinestoIgnore = array(
            "Creatures",
            "Instants and Sorceries",
            "Other",
            "Lands",
            "Sideboard",
            "Notes",
            "Sideboard notes",
            "Planes and Phenomena",
            "Tokens"
        );

        // Cards required per deck type for legal play
        $hundredcarddecks = array(
            'Commander'
        );

        $sixtycarddecks = array(
            'Casual',
            'Standard',
            'Modern'
        );

        $fiftycarddecks = array(
            'Tiny Leader'
        );

        // Setcodes to not include by default when card-adding (i.e. excluding plst in favour of originals)
        $nonPreferredSetCodes = array(
            'plst',
            'sld',
            'spg'
        );

        // Which database field holds information about card legality in the deck types
        $deck_legality_map = array(
            array(
                'decktype' => 'Commander',
                'db_field' => 'legalitycommander'
            ),
            array(
                'decktype' => 'Standard',
                'db_field' => 'legalitystandard'
            ),
            array(
                'decktype' => 'Tiny Leader',
                'db_field' => 'legalitytinyleaderscommander'
            ),
            array(
                'decktype' => 'Modern',
                'db_field' => 'legalitymodern'
            ),
            array(
                'decktype' => 'Casual',
                'db_field' => ''
            ),
            array(
                'decktype' => 'Wishlist',
                'db_field' => ''
            )
        );

        //Promo types to show on Card Detail page
        $promos_to_show = array(
            array(
                'promotype' => 'thick',
                'display' => 'Thick card (commander proxy)'
            ),
            array(
                'promotype' => 'serialized',
                'display' => 'Serialised card'
            ),
            array(
                'promotype' => 'godzillaseries',
                'display' => 'Godzilla card'
            ),
            array(
                'promotype' => 'buyabox',
                'display' => 'Buy-a-box card'
            ),
            array(
                'promotype' => 'oilslick',
                'display' => 'Oil slick foil'
            ),
            array(
                'promotype' => 'ripplefoil',
                'display' => 'Ripple foil'
            ),
            array(
                'promotype' => 'surgefoil',
                'display' => 'Surge foil'
            ),
            array(
                'promotype' => 'doublerainbow',
                'display' => 'Double rainbow foil'
            ),
            array(
                'promotype' => 'boosterfun',
                'display' => 'Booster fun'
            ),
            array(
                'promotype' => 'stepandcompleat',
                'display' => 'Step-and-Compleat Phyrexian foil'
            ),
            array(
                'promotype' => 'datestamped',
                'display' => 'Date stamped'
            ),
            array(
                'promotype' => 'fnm',
                'display' => 'Friday Night Magic'
            ),
            array(
                'promotype' => 'arenaleague',
                'display' => 'Arena League'
            ),
            array(
                'promotype' => 'storechampionship',
                'display' => 'Store Championship'
            ),
            array(
                'promotype' => 'prerelease',
                'display' => 'Prelease'
            ),
            array(
                'promotype' => 'mediainsert',
                'display' => 'Media Insert'
            ),
            array(
                'promotype' => 'starterdeck',
                'display' => 'Starter Deck'
            ),
            array(
                'promotype' => 'promopack',
                'display' => 'Promo pack'
            ),
            array(
                'promotype' => 'stamped',
                'display' => 'Stamped'
            ),
            array(
                'promotype' => 'setpromo',
                'display' => 'Set promo'
            ),
            array(
                'promotype' => 'silverfoil',
                'display' => 'Silver foil'
            ),
            array(
                'promotype' => 'galaxyfoil',
                'display' => 'Galaxy foil'
            ),
            array(
                'promotype' => 'tourney',
                'display' => 'Tournament promo'
            ),
            array(
                'promotype' => 'planeswalkerdeck',
                'display' => 'Planeswalker deck card'
            ),
            array(
                'promotype' => 'instore',
                'display' => 'In-store promo card'
            ),
            array(
                'promotype' => 'judgegift',
                'display' => 'Judge gift program card'
            ),
            array(
                'promotype' => 'halofoil',
                'display' => 'Halo foil'
            ),
            array(
                'promotype' => 'boxtopper',
                'display' => 'Box topper card'
            ),
            array(
                'promotype' => 'embossed',
                'display' => 'Embossed card'
            ),
            array(
                'promotype' => 'textured',
                'display' => 'Textured card'
            ),
            array(
                'promotype' => 'neonink',
                'display' => 'Neon ink'
            ),
            array(
                'promotype' => 'confettifoil',
                'display' => 'Confetti foil'
            ),
            array(
                'promotype' => 'wizardsplaynetwork',
                'display' => 'WPN'
            ),
            array(
                'promotype' => 'draftweekend',
                'display' => 'Draft weekend'
            ),
            array(
                'promotype' => 'concept',
                'display' => 'Concept card'
            ),
            array(
                'promotype' => 'gameday',
                'display' => 'Game Day card'
            ),
            array(
                'promotype' => 'release',
                'display' => 'Release card'
            ),
            array(
                'promotype' => 'convention',
                'display' => 'Convention promo card'
            ),
            array(
                'promotype' => 'event',
                'display' => 'Event promo card'
            ),
            array(
                'promotype' => 'datestamped',
                'display' => 'Date stamped'
            )
        );

        // Bulk import rules
        /// Languages to ignore in Default cards download (currently importing all)
        // $langs_to_skip = ['fr','es','it','zhs','sa','he','de','ru','ar','grc','la','zht','ko','pt'];
        $langs_to_skip = [];

        /// Languages to ignore in All cards download (currently importing all)
        // $langs_to_skip_all = ['fr','es','it','zhs','sa','he','de','ru','ar','grc','la','zht','ko','pt'];
        $langs_to_skip_all = [];

        /// Layouts to skip (currently empty, so all layouts are imported)
        $layouts_to_skip = [];

        // Which type of cards to include
        $games_to_include = ['paper','arena'];

        // Scryfall API endpoints
        $scryfallApiBaseUrl = "https://api.scryfall.com";
        $defaultCardsUrl    = $scryfallApiBaseUrl . "/bulk-data/default-cards";
        $allCardsUrl        = $scryfallApiBaseUrl . "/bulk-data/all-cards";
        $setsUrl            = $scryfallApiBaseUrl . "/sets";
        $rulingsUrl         = $scryfallApiBaseUrl . "/bulk-data/rulings";
        $oracleTagsUrl      = $scryfallApiBaseUrl . "/bulk-data/oracle-tags";
        $artTagsUrl         = $scryfallApiBaseUrl . "/bulk-data/art-tags";
        $migrationsUrl      = $scryfallApiBaseUrl . "/migrations";
        $manifestUrl        = $scryfallApiBaseUrl . "/cards/manifest";

        $rules = get_defined_vars();
        unset($rules['rules']);
        return $rules;
    }
endif;

return mtg_game_rules();
