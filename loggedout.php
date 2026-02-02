<?php

/*
Version:     3.14
Date:        02/02/26
Name:        loggedout.php
Purpose:     Logged out landing page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

// Bootstrap
$ctx                        = require __DIR__ . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$cssver                     = (string) $ctx->meta('cssver', '');
$serviceWorkerVersion       = (string) $ctx->meta('serviceWorkerVersion', 'v6');

$siteTitle                  = (string) $appConfig->general('title', '');

// Content
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="initial-scale=1.1, maximum-scale=1.1, minimum-scale=1.1, user-scalable=no"
    >
    <title><?php echo $siteTitleEsc;?> - logged out</title>
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8'); ?>.css?v=<?php
        echo $serviceWorkerVersion; ?>"
    >
    <?php include APP_ROOT . '/includes/googlefonts.php'; ?>
</head>
<body id="loginbody" class="body">
    <div id="loginheader">
        <h2 id="h2"><?php echo $siteTitleEsc; ?></h2>
        You have been logged out. <a href="login.php">Click here to log back in</a>
    </div>
</body>
</html>
