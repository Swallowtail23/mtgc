<?php

/*
Version:     3.05
Date:        12/01/26
Name:        loggedout.php
Purpose:     Logged out landing page.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

// Bootstrap

$appContext = require __DIR__ . '/bootstrap.php';

$siteTitle = (string) $appConfig->general('title', '');

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
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8'); ?>.css"
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
