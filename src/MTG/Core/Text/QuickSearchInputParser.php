<?php

/*
Version:     1.1
Date:        27/03/26
Name:        QuickSearchInputParser.php
Purpose:     Parses quick-search input into typed text, search string, setcode, and number parts.
Notes:       -
Author:      Codex
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Text;

use MTG\Core\Validation;

class QuickSearchInputParser
{
    /**
     * @param string $input
     * @param array<int,string> $bracketsInNames
     * @return array{typed:string,search_string:string,setcode:string,number:string}
     */
    public static function parse(string $input, array $bracketsInNames): array
    {
        $result = [
            'typed' => trim($input),
            'search_string' => '%' . trim($input) . '%',
            'setcode' => '',
            'number' => ''
        ];

        if (strpos($input, '[') === false && strpos($input, '(') === false) :
            return $result;
        endif;

        $insideBrackets = false;
        $closingBracket = false;
        $setClosed = false;
        $closingBracketIndex = null;
        $typedText = '';
        $number = '';
        $setcode = '';

        foreach (str_split($input) as $charIndex => $char) :
            if ($char === '[' || $char === '(') :
                $insideBrackets = true;
            elseif ($insideBrackets && $char !== ']' && $char !== ')' && !$setClosed && $char !== ' ') :
                $setcode .= $char;
            elseif ($insideBrackets && $char !== ']' && $char !== ')' && $char === ' ' && !$setClosed) :
                $setClosed = true;
            elseif ($insideBrackets && $char !== ']' && $char !== ')' && $setClosed === true) :
                $number .= $char;
            elseif ($insideBrackets && ($char === ']' || $char === ')')) :
                $setClosed = true;
                $closingBracket = true;
                $closingBracketIndex = $charIndex;
                break;
            elseif (!$insideBrackets) :
                $typedText .= $char;
            endif;
        endforeach;

        if ($closingBracket && $number === '' && $closingBracketIndex !== null) :
            $trailingText = trim(substr($input, $closingBracketIndex + 1));
            if ($trailingText !== '') :
                $number = $trailingText;
            endif;
        endif;

        if ($insideBrackets && !$setClosed) :
            $result['setcode'] = trim($setcode) . '%';
        elseif ($setClosed && $number === '' && $closingBracket) :
            $result['setcode'] = trim($setcode);
        elseif ($insideBrackets && $number !== '' && !$closingBracket) :
            $result['setcode'] = trim($setcode);
            $result['number'] = trim($number) . '%';
        elseif ($setClosed && $number !== '' && $closingBracket) :
            $result['setcode'] = trim($setcode);
            $result['number'] = trim($number);
        endif;

        $result['typed'] = trim($typedText);
        $result['search_string'] = '%' . trim($typedText) . '%';

        if ($result['setcode'] !== '') :
            $bracketText = trim(trim($result['setcode']) . ' ' . trim($result['number']));
            if (Validation::inArrayCaseInsensitive($bracketText, $bracketsInNames)) :
                $result['typed'] .= ' (' . $bracketText . ')';
                $result['search_string'] = $result['typed'];
                $result['setcode'] = '';
                $result['number'] = '';
            endif;
        endif;

        return $result;
    }
}
