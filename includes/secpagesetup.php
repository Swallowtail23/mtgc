<?php
/* Version:     2.1
    Date:       27/11/23
    Name:       secpagesetup.php
    Purpose:    Establish variables on secure pages
    Notes:      {none}
 * 
    1.0
                Initial version
 *  2.0
 *              Add collection view check
 *  2.1
 *              27/11/23
 *              Moved fx logic into session manager class's user info method
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$cssver = cssver();                                             // find CSS Version

if (!isset($_SESSION['user']) OR !$_SESSION["logged"]):
    header("Location: /login.php");                             // check if user is logged in; else redirect to login.php
    exit();    
else:
    // Session information \\
    $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $username = $userArray['username'];                         // get user name
    $mytable = $userArray['table'];                             // user's collection table
    $collection_view = $userArray['collection_view'];           // has this user selected Collection View
    $admin = $userArray['admin'];
    $grpinout = $userArray['grpinout'];
    $groupid = $userArray['groupid'];
    $fx = $userArray['fx'];
    $targetCurrency = $userArray['currency'];
    $rate = $userArray['rate'];

    $useremail = str_replace("'","",$_SESSION['useremail']);    // get email address of user, without quotes, available in SESSION

    $mtcestatus = mtcemode($user);                              // check mtce mode active and if an admin user
    if($mtcestatus == 1):                                       // check if site is in maintenance mode
        include ('includes/mtcestub.php');
        session_destroy();
        exit();
    endif;
endif;