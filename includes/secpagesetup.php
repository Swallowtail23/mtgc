<?php

/*
Version:     2.5
Date:        29/11/25
Name:        secpagesetup.php
Purpose:     Establish variables on secure pages
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$cssver = cssVersionCheck();                              // find CSS Version
if (!isset($_SESSION['user']) or !$_SESSION["logged"]) :
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];  // capture entered URL
    header("Location: /login.php");                       // check if user is logged in; else redirect to login.php
    exit();
else :
    // Session information \\
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    if ($userArray !== false) :
        $user = $userArray['usernumber'];
        $userName = $userArray['username'];               // get user name
        $mytable = $userArray['table'];                   // user's collection table
        $collection_view = $userArray['collection_view']; // has this user selected Collection View
        $admin = $userArray['admin'];
        $grpinout = $userArray['grpinout'];
        $groupid = $userArray['groupid'];
        $fx = $userArray['fx'];
        $targetCurrency = $userArray['currency'];
        $rate = $userArray['rate'];

        $userEmail = $_SESSION['useremail'];              // get email address of user, available in SESSION

        $mtceStatus = mtceModeCheck($user);                    // check mtce mode active and if an admin user
        if ($mtceStatus == 1) :                           // check if site is in maintenance mode
            include('includes/mtcestub.php');
            session_destroy();
            exit();
        endif;
    else :
        $msg = new \MTG\Core\Message($logfile);
        $msg->logMessage('[ERROR]', "User array returned false - user no longer exists?");
        session_destroy();
        echo "<meta http-equiv='refresh' content='1;url=login.php'>";
        exit();
    endif;
endif;
