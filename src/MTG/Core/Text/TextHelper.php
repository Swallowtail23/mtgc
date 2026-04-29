<?php

/*
Version:     1.1
Date:        29/04/26
Name:        TextHelper.php
Purpose:     Text helper utilities.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Text;

class TextHelper
{
    /**
    * @param array<string, string> $attributes
    */
    public static function autoLink(string $str, array $attributes = array()): string
    {
        $attrs = '';
        foreach ($attributes as $attribute => $value) :
            $attrs .= " {$attribute}=\"{$value}\"";
        endforeach;
        $str = ' ' . $str;
        $str = preg_replace(
            '`([^"=\'>])((http|https|ftp)://[^\s<]+[^\s<\.)])`i',
            '$1<a href="$2"' . $attrs . '>$2</a>',
            $str
        );
        $str = substr($str, 1);
        return $str;
    }
}
