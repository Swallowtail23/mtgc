<?php

/*
Version:     2.0
Date:        18/09/26
Name:        ScryfallCardImportPolicy.php
Purpose:     Decide whether a mapped Scryfall card is eligible for an import mode.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\GameRules;

class ScryfallCardImportPolicy
{
    /** @var array<int, string> */
    private array $gamesToInclude;
    /** @var array<int, mixed> */
    private array $languagesToSkip;
    /** @var array<int, mixed> */
    private array $allLanguagesToSkip;
    /** @var array<int, mixed> */
    private array $layoutsToSkip;

    /**
     * @param GameRules $gameRules          Game rules configuration
     * @param ScryfallGameTypeRepository $repository Database-backed game-type repository
     */
    public function __construct(GameRules $gameRules, ScryfallGameTypeRepository $repository)
    {
        $this->gamesToInclude = $repository->getSelectedCodes();
        $this->languagesToSkip = self::ruleArray($gameRules->get('langs_to_skip', []));
        $this->allLanguagesToSkip = self::ruleArray($gameRules->get('langs_to_skip_all', []));
        $this->layoutsToSkip = self::ruleArray($gameRules->get('layouts_to_skip', []));
    }

    /** @param array<string, mixed> $card @return array{include: bool, reason: string} */
    public function decide(array $card, string $type): array
    {
        $games = is_array($card['games'] ?? null) ? $card['games'] : [];
        if (array_intersect($games, $this->gamesToInclude) === []) :
            return ['include' => false, 'reason' => 'unsupported game'];
        endif;
        $lang = $card['lang'] ?? null;
        if ($type === 'default' && in_array($lang, $this->languagesToSkip, true)) :
            return ['include' => false, 'reason' => 'excluded language'];
        endif;
        if ($type === 'all' && in_array($lang, $this->allLanguagesToSkip, true)) :
            return ['include' => false, 'reason' => 'excluded all-file language'];
        endif;
        if (in_array($card['layout'] ?? null, $this->layoutsToSkip, true)) :
            return ['include' => false, 'reason' => 'excluded layout'];
        endif;
        return ['include' => true, 'reason' => 'included'];
    }

    /** @return array<int, mixed> */
    private static function ruleArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
