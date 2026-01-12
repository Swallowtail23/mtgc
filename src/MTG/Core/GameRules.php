<?php

/*
Version:     1.33
Date:        12/01/26
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
        $path = APP_ROOT . '/includes/game_rules.php';
        return self::fromFile($path);
    }

    public function get(string $key, $default = null)
    {
        return $this->rules[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->rules);
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        if (is_string($value)) :
            return $value;
        endif;
        if (is_numeric($value)) :
            return (string) $value;
        endif;
        return $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, null);
        if (is_numeric($value)) :
            return (int) $value;
        endif;
        return $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, null);
        if (is_numeric($value)) :
            return (float) $value;
        endif;
        return $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, null);
        if (is_bool($value)) :
            return $value;
        endif;
        if (is_numeric($value)) :
            return ((int) $value) === 1;
        endif;
        return $default;
    }

    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, null);
        if (is_array($value)) :
            return $value;
        endif;
        return $default;
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
