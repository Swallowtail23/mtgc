<?php

require __DIR__ . '/../../vendor/autoload.php';

use MTG\Core\Http\AjaxResponse;

$metaPath = getenv('AJAX_META_PATH');
if ($metaPath === false || $metaPath === '') :
    $metaPath = sys_get_temp_dir() . '/ajax_meta.json';
endif;

register_shutdown_function(function () use ($metaPath) {
    $data = [
        'headers' => headers_list(),
        'status' => http_response_code()
    ];
    file_put_contents($metaPath, json_encode($data));
});

AjaxResponse::text('ok', 202);
