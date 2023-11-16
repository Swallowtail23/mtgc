<?php
/* Version:     2.0
    Date:       15/10/23
    Name:       secpagesetup.php
    Purpose:    Establish variables on secure pages
    Notes:      {none}
 * 
    1.0
                Initial version
 *  2.0
 *              Add collection view check
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$cssver = cssver();                                         // find CSS Version
$sessionManager = new SessionManager($db);
$user = $sessionManager->checkLogged();                     // check if user is logged in, if not redirect to login.php
$username = username($user);                                // get user name
$useremail = str_replace("'","",$_SESSION['useremail']);    // get email address of user, without quotes
$mytable = $user."collection";                              // user's collection table
$mtcestatus = mtcemode($user);                              // check mtce mode active and if an admin user
$collection_view = collection_view($user);                  // has this user selected Collection View
if($mtcestatus == 1):                                       // check if site is in maintenance mode
    include ('includes/mtcestub.php');
    session_destroy();
    exit();
endif;
