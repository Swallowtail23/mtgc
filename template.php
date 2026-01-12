<?php

/*
Version:     1.91
Date:        12/01/26
Name:        template.php
Purpose:     Site template.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

// Bootstrap
$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$cssver                     = (string) $ctx->meta('cssver', '');
$serviceWorkerVersion       = (string) $ctx->meta('serviceWorkerVersion', 'v6');
$sessionUser                = $ctx->sessionUser();

$siteTitle                  = (string) $appConfig->general('title', '');
$tier                       = (string) $appConfig->general('tier', 'prod');
$copyright                  = (string) $appConfig->general('copyright', '');

// Content
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - template</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css"
    >
    <?php include APP_ROOT . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body class="body">
<?php
// Start building the page here, so errors show in the website template
include_once APP_ROOT . '/includes/analyticstracking.php';
require APP_ROOT . '/includes/overlays.php';
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/menu.php';
?>
<div id="page">
    <div class="staticpagecontent">
        <h2 id="h2">Template title</h2>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
</body>
</html>
