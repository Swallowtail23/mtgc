<?php

/*
Version:     2.8
Date:        11/01/26
Name:        loggedout.php
Purpose:     Logged out landing page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Admin\AdminSettings;
use MTG\Core\Message;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;

startCustomSession();
require 'includes/ini.php';               // Initialise and load ini file
require 'includes/error_handling.php';

$msg = new Message($appConfig);
$cssver = AdminSettings::getCssVersionSuffix($db, $appConfig);
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
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8'); ?>.css"
    >
    <?php include 'includes/googlefonts.php'; ?>
</head>
<body id="loginbody" class="body">
    <div id="loginheader">
        <h2 id="h2"><?php echo $siteTitleEsc; ?></h2>
        You have been logged out. <a href="login.php">Click here to log back in</a>
    </div>
</body>
</html>
