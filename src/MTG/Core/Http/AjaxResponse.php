<?php

/*
Version:     1.0
Date:        11/01/26
Name:        AjaxResponse.php
Purpose:     Standard AJAX response helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core\Http;

class AjaxResponse
{
    public static function json($payload, $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit();
    }

    public static function text($text, $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo $text;
        exit();
    }
}
