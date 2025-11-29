<?php

/*
Version:     2.1
Date:        25/11/25
Name:        error.php
Purpose:     Very basic page with no database connectivity.
Notes:       Ini file is parsed with parse_ini_file, not INI class, as classes not loaded in this page.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0 17/10/16 Update copyright text
    2.1 25/11/25 Standard tidy-up
*/

$iniArray = parse_ini_file("/opt/mtg/mtg_new.ini");
//Copyright string
$copyright = $iniArray['Copyright'];
if ($iniArray['tier'] === 'dev') :
    $tier = 'dev';
else :
    $tier = 'prod';
endif;
$siteTitle = $iniArray['title'];
$cssver = "";
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title> <?php echo $siteTitle;?> error page</title>
    <link rel="manifest" href="manifest.json" />
    <link rel="stylesheet" type="text/css" href="css/style<?php echo $cssver?>.css">
    <?php include('includes/googlefonts.php');?>
    <script src="/js/jquery.js"></script>
</head>

<body class="body">
<?php
// Start building the page here, so errors show in the website template
// Includes first - menu and header
if ((isset($_SESSION["logged"])) and ($_SESSION["logged"] == true)) :
    require('includes/overlays.php');
endif;
require('includes/header.php'); ?>
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
        That page returned an error, and details have been emailed to site admins. <br>
        Please try again later.
        <br>
        &nbsp;
        <br>
    </div>
</div>

<?php
require('includes/footer.php'); ?>
</body>
</html>
