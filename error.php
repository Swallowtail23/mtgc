<?php

/*
Version:     2.39
Date:        12/01/26
Name:        error.php
Purpose:     Very basic page with no database connectivity.
Notes:       Ini file is parsed with parse_ini_file and AppConfig is loaded for page config values.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$iniArray = parse_ini_file("/opt/mtg/mtg_new.ini", true);
if (!is_array($iniArray)) :
    $iniArray = [];
endif;
require_once __DIR__ . '/vendor/autoload.php';
$appConfig = \MTG\Core\AppConfig::fromIni($iniArray);
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
$cssver = "";
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title> <?php echo $siteTitleEsc;?> error page</title>
    <link rel="manifest" href="/manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include __DIR__ . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
</head>

<body class="body">
<?php
// Start building the page here, so errors show in the website template
// Includes first - menu and header
if ((isset($_SESSION["logged"])) and ($_SESSION["logged"] == true)) :
    require __DIR__ . '/includes/overlays.php';
endif;
require __DIR__ . '/includes/header.php'; ?>
<div id='menubuttondiv' class="togglemenu">
    <a href="#" id='toggle-menu'><span class="material-symbols-outlined menu">menu</span></a>
</div>
<div id="menu">
    <div class='nav_nodivider'><a title="Home" href="/">Home</a></div>
</div>
<div id="page">
    <div class="staticpagecontent">
        <h3>Error</h3>
        We've encountered a problem!<br><br>
        <?php
        $emailEnabled = (bool) $appConfig->email('enabled', false);
        if ($emailEnabled) :
            echo "That page returned an error, and details have been emailed to site admins.";
        else :
            echo "That page returned an error. Email alerts are disabled in this environment,";
            echo " so no notification was sent.";
        endif;
        ?><br>
        Please try again later.
        <br>
        &nbsp;
        <br>
    </div>
</div>

<?php
require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
