<?php
/* Version:     2.2
    Date:       20/01/24
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
 * 
 *  2.2         20/01/24
 *              Move to logMessage
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$cssver = cssver();                                             // find CSS Version
if (!isset($_SESSION['user']) OR !$_SESSION["logged"]):
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];        // capture entered URL
    header("Location: /login.php");                             // check if user is logged in; else redirect to login.php
    exit();    
else:
    // Session information \\
    $sessionManager = new SessionManager($db,$adminip,$_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    if($userArray !== FALSE):
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

        $useremail = $_SESSION['useremail'];                        // get email address of user, available in SESSION

        $mtcestatus = mtcemode($user);                              // check mtce mode active and if an admin user
        if($mtcestatus == 1):                                       // check if site is in maintenance mode
            include ('includes/mtcestub.php');
            session_destroy();
            exit();
        endif;
    else:
        $msg = new Message($logfile);
        $msg->logMessage('[ERROR]',"User array returned false - user no longer exists?");
        session_destroy();
        echo "<meta http-equiv='refresh' content='1;url=login.php'>";
        exit();
    endif;
endif;