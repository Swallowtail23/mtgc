<?php

// Either just use the default below, or set your own in a copy of this file named sessionname.local.php

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
);

// override the default if needed
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('session.cookie_secure', '0');
}

function startCustomSession() {
    ini_set('session.name', 'Change-to-Your-Own-Value_2024');
    session_start();
}
