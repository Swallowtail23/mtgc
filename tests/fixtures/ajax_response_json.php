<?php

/*
Version:     1.0
Date:        28/04/26
Name:        ajax_response_json.php
Purpose:     Fixture script for JSON AJAX response tests.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

require __DIR__ . '/../../vendor/autoload.php';

use MTG\Core\Http\AjaxResponse;

$metaPath = getenv('AJAX_META_PATH');
if ($metaPath === false || $metaPath === '') :
    $metaPath = sys_get_temp_dir() . '/ajax_meta.json';
endif;

register_shutdown_function(function () use ($metaPath): void {
    $data = [
        'headers' => headers_list(),
        'status' => http_response_code()
    ];
    file_put_contents($metaPath, json_encode($data));
});

AjaxResponse::json(['ok' => true], 201);
