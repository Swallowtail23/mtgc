<?php

/*
Version:     1.20
Date:        12/01/26
Name:        mtcestub.php
Purpose:     PHP script to display Maintenance message
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\AppConfig;

$iniArray = parse_ini_file("/opt/mtg/mtg_new.ini", true);
if (!is_array($iniArray)) :
    $iniArray = [];
endif;
require_once __DIR__ . '/vendor/autoload.php';
$appConfig = AppConfig::fromIni($iniArray);
$serviceWorkerVersion = 'v6';
$versionFile = __DIR__ . '/VERSION';
if (file_exists($versionFile)) :
    $serviceWorkerVersion = trim((string) file_get_contents($versionFile));
    if ($serviceWorkerVersion === '') :
        $serviceWorkerVersion = 'v6';
    endif;
endif;
//Copyright string
$siteTitle = (string) $appConfig->general('title', 'MTG Collection');
$tier = (string) $appConfig->general('tier', 'prod');
$copyright = (string) $appConfig->general('copyright', '');
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
$cssver = '-min';
?>

<!DOCTYPE html>
<meta http-equiv='refresh' content='3;url=../login.php'>
<html>
    <head>
        <meta charset="UTF-8">
        <title> <?php echo $siteTitleEsc;?> Maintenance page</title>
        <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
        <?php include __DIR__ . '/includes/googlefonts.php';?>
        <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    </head>
    <body class="body">
    <?php
    // Start building the page here, so errors show in the website template
    require __DIR__ . '/includes/header.php'; ?>
    <div id="menu"></div>
    <div id="page">
        <div class="staticpagecontent">
            <h3>Site maintenance</h3>
            Site is down for maintenance. Redirecting to login page.<br><br>
        </div>
    </div>

    <?php
    require __DIR__ . '/includes/footer.php'; ?>
    </body>
</html>
