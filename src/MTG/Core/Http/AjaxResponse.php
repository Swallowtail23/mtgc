<?php

/*
Version:     1.1
Date:        29/04/26
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
    public static function json(mixed $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit();
    }

    public static function text(string $text, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo $text;
        exit();
    }
}
