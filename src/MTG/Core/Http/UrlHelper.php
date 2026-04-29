<?php

/*
Version:     1.1
Date:        29/04/26
Name:        UrlHelper.php
Purpose:     URL helper utilities.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Http;

class UrlHelper
{
    public static function normalizeRedirectUrl(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') :
            return null;
        endif;

        $parsed = parse_url($url);
        if ($parsed === false) :
            return null;
        endif;

        if (isset($parsed['scheme']) || isset($parsed['host'])) :
            return null;
        endif;

        $path = $parsed['path'] ?? '';
        if ($path === '') :
            return null;
        endif;

        if ($path[0] !== '/') :
            $path = '/' . ltrim($path, '/');
        endif;

        $query = $parsed['query'] ?? '';
        $fragment = $parsed['fragment'] ?? '';
        $final = $path;
        if ($query !== '') :
            $final .= '?' . $query;
        endif;
        if ($fragment !== '') :
            $final .= '#' . $fragment;
        endif;

        return $final;
    }

    /**
    * @param array<string, mixed> $input
    */
    public static function getStringParameters(array $input, string $ignore1, string $ignore2 = ''): string
    {
        $params = array();

        foreach ($input as $key => $value) :
            if ($key === $ignore1 || $key === $ignore2) :
                continue;
            endif;

            if ($key === 'layout') :
                $validlayouts = array('grid', 'list', 'bulk', '');
                if (!in_array($value, $validlayouts, true)) :
                    $value = 'grid';
                endif;
                $params[$key] = (string) $value;
            elseif ($key === 'set' && is_array($value)) :
                // keep set[] as array
                $params['set'] = array_values($value);
            else :
                $params[$key] = (string) $value;
            endif;
        endforeach;

        if (empty($params)) :
            return '';
        endif;

        return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function getFullUrl(): string
    {
        // Get HTTP/HTTPS (the possible values for this vary from server to server)
        $myUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']
            && !in_array(strtolower($_SERVER['HTTPS']), array('off','no')))
            ? 'https'
            : 'http';
        // Get domain portion
        $myUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? '');
        // Get path to script
        $myUrl .= $_SERVER['REQUEST_URI'] ?? '';
        // Add path info, if any
        if (!empty($_SERVER['PATH_INFO'])) :
            $myUrl .= $_SERVER['PATH_INFO'];
        endif;

        return $myUrl;
    }
}
