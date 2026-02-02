<?php

/*
Version:     1.28
Date:        02/02/26
Name:        issues.php
Purpose:     Issues page.
Notes:       No db functions.
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
$userEmail                  = $sessionUser->email();

// Content
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - issues</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo $cssver?>.css?v=<?php echo $serviceWorkerVersion; ?>"
    >
    <?php include APP_ROOT . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body id="body" class="body">
<?php
include_once APP_ROOT . '/includes/analyticstracking.php';
require APP_ROOT . '/includes/overlays.php';
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/menu.php';

?>
<div id='page'>
    <div class='staticpagecontent'>
        <div id="printtitle" class="headername">
            <img src="images/white_m.png"><?php echo $siteTitleEsc;?>
        </div>
        <h2 class='h2pad'>Known issues and bugs</h2>
        <ul>
            <li><b>Images are slow to load.</b> Images for new cards are fetched when they are added to the database,
                but for older cards the images are fetched the first time they are needed. This can take a while - be
                patient.</li>
        </ul>
&nbsp;
</div>
</div>
<?php
require APP_ROOT . '/includes/footer.php';
?>
</body>
</html>
