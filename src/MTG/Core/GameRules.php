<?php

/*
Version:     1.3
Date:        11/01/26
Name:        GameRules.php
Purpose:     Container for game-specific rules and constants.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core;

class GameRules
{
    /**
    * @var array<string,mixed>
    */
    private $rules = [];

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public static function fromFile(string $path): self
    {
        $rules = [];
        if (is_file($path)) :
            $loaded = require $path;
            if (is_array($loaded)) :
                $rules = $loaded;
            endif;
        endif;

        return new self($rules);
    }

    public static function fromDefaults(): self
    {
        $path = dirname(__DIR__, 2) . '/includes/game_rules.php';
        return self::fromFile($path);
    }

    public function get(string $key, $default = null)
    {
        return $this->rules[$key] ?? $default;
    }

    public function getLanguageLabel(string $code): string
    {
        $searchLangs = $this->get('search_langs', []);
        if (!is_array($searchLangs)) :
            return $code;
        endif;
        foreach ($searchLangs as $lang) :
            if (!is_array($lang) || !isset($lang['code'])) :
                continue;
            endif;
            if ($lang['code'] == $code) :
                return (string) ($lang['pretty'] ?? $code);
            endif;
        endforeach;
        return $code;
    }

    public function all(): array
    {
        return $this->rules;
    }
}
