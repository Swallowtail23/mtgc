<?php

/*
Version:     1.1
Date:        27/03/26
Name:        SearchTextHelper.php
Purpose:     Search text helper utilities for accent-insensitive matching and display formatting.
Notes:       -
Author:      Codex
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Text;

class SearchTextHelper
{
    /**
     * Builds a lowercase accent-stripped comparison string and a map back to original character indexes.
     *
     * @param string $value
     * @return array{0:string,1:array<int,int>}
     */
    private static function buildComparableText(string $value): array
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) :
            return ['', []];
        endif;

        $comparable = '';
        $indexMap = [];

        foreach ($chars as $charIndex => $char) :
            $normalisedChar = class_exists('Normalizer')
                ? \Normalizer::normalize($char, \Normalizer::FORM_D)
                : $char;
            if ($normalisedChar === false || $normalisedChar === null) :
                $normalisedChar = $char;
            endif;
            $strippedChar = preg_replace('/\p{Mn}+/u', '', $normalisedChar);
            if ($strippedChar === null || $strippedChar === '') :
                continue;
            endif;
            $lowerChar = mb_strtolower($strippedChar, 'UTF-8');
            $lowerChars = preg_split('//u', $lowerChar, -1, PREG_SPLIT_NO_EMPTY);
            if ($lowerChars === false) :
                continue;
            endif;
            foreach ($lowerChars as $lowerCharPart) :
                $comparable .= $lowerCharPart;
                $indexMap[] = $charIndex;
            endforeach;
        endforeach;

        return [$comparable, $indexMap];
    }

    public static function appendLanguageCodeSuffix(
        string $displayText,
        ?string $languageCode,
        bool $showLanguageCode
    ): string {
        if (!$showLanguageCode || empty($languageCode)) :
            return $displayText;
        endif;

        return $displayText . ' (' . strtoupper($languageCode) . ')';
    }

    public static function highlightAccentInsensitiveMatch(string $displayText, string $needle): string
    {
        if ($needle === '') :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $displayChars = preg_split('//u', $displayText, -1, PREG_SPLIT_NO_EMPTY);
        if ($displayChars === false) :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        [$comparableDisplay, $displayMap] = self::buildComparableText($displayText);
        [$comparableNeedle] = self::buildComparableText($needle);

        if ($comparableDisplay === '' || $comparableNeedle === '') :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $matchOffset = strpos($comparableDisplay, $comparableNeedle);
        if ($matchOffset === false) :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $matchEndOffset = $matchOffset + strlen($comparableNeedle) - 1;
        if (!isset($displayMap[$matchOffset], $displayMap[$matchEndOffset])) :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $startIndex = $displayMap[$matchOffset];
        $endIndex = $displayMap[$matchEndOffset];

        $before = implode('', array_slice($displayChars, 0, $startIndex));
        $match = implode('', array_slice($displayChars, $startIndex, $endIndex - $startIndex + 1));
        $after = implode('', array_slice($displayChars, $endIndex + 1));

        return htmlspecialchars($before, ENT_QUOTES, 'UTF-8')
            . '<strong>' . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . '</strong>'
            . htmlspecialchars($after, ENT_QUOTES, 'UTF-8');
    }

    public static function highlightByCharacterOffset(
        string $displayText,
        int $matchOffset,
        int $matchLength
    ): string {
        if ($matchOffset < 1 || $matchLength < 1) :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $displayChars = preg_split('//u', $displayText, -1, PREG_SPLIT_NO_EMPTY);
        if ($displayChars === false) :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $startIndex = $matchOffset - 1;
        $matchChars = array_slice($displayChars, $startIndex, $matchLength);
        if ($matchChars === []) :
            return htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');
        endif;

        $before = implode('', array_slice($displayChars, 0, $startIndex));
        $match = implode('', $matchChars);
        $after = implode('', array_slice($displayChars, $startIndex + count($matchChars)));

        return htmlspecialchars($before, ENT_QUOTES, 'UTF-8')
            . '<strong>' . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . '</strong>'
            . htmlspecialchars($after, ENT_QUOTES, 'UTF-8');
    }

    public static function formatQuickSearchDisplayLabel(
        string $matchedName,
        int $matchOffset,
        int $matchLength,
        ?string $languageCode = null,
        bool $showLanguageCode = false
    ): string {
        $highlightedText = self::highlightByCharacterOffset($matchedName, $matchOffset, $matchLength);

        if (!$showLanguageCode || empty($languageCode)) :
            return $highlightedText;
        endif;

        return $highlightedText . ' (' . htmlspecialchars(strtoupper($languageCode), ENT_QUOTES, 'UTF-8') . ')';
    }
}
