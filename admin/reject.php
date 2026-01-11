<?php

/*
Version:     2.82
Date:        28/11/25
Name:        reject.php
Purpose:     Non-admin rejection page called by admin pages on attempted load by non-admin or from non-secure page
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\Message;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;?>
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

<!DOCTYPE html>
    <head>
        <title><?php echo $siteTitleEsc;?> - admin (reject)</title>
        <link rel="manifest" href="/manifest.json" />
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
        <?php include APP_ROOT . '/includes/googlefonts.php';?>
    </head>
    <body id="body" class="body">

    <?php
    include APP_ROOT . '/includes/overlays.php';
    include APP_ROOT . '/includes/header.php';
    require APP_ROOT . '/includes/menu.php';
    $msg = new Message($appConfig);
    ?>
    <div id='page'>
        <div class='staticpagecontent'>
            <?php
            $msg->logMessage('[ERROR]', "Admin page called by user number {$_SESSION['user']}, admin status is $admin");
            if ($admin == 3) :
                echo "<meta http-equiv='refresh' content='2;url=../index.php'>";
                echo "<div class='alert-box error' id='adminerror'><span>error: </span>"
                        . "Insufficient rights to access this page. "
                        . "Redirecting to main page.</div>";
                $msg->logMessage(
                    '[ERROR]',
                    "Admin page called by non-admin user from " . $_SERVER['REMOTE_ADDR'] . ", exiting"
                );
                exit();
            elseif ($admin == 2) :
                echo "<meta http-equiv='refresh' content='2;url=../index.php'>";
                echo "<div class='alert-box error' id='adminerror'><span>error: </span>"
                        . "This page only accessible from location specified in ini file. "
                        . "Redirecting to main page.</div>";
                $msg->logMessage(
                    '[ERROR]',
                    "Admin page called by admin user from non-secure location: " . $_SERVER['REMOTE_ADDR'] . ", exiting"
                );
                exit();
            endif;
