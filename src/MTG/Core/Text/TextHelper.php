<?php

/*
Version:     1.0
Date:        11/01/26
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
    public static function autoLink($str, $attributes = array())
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
