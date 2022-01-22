<?php
/* Version:     1.0
    Date:       17/10/16
    Name:       secpagesetup.php
    Purpose:    Establish variables on secure pages
    Notes:      {none}
 * 
    1.0
                Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$cssver = cssver();                                         // find CSS Version
$user = check_logged();                                     // check if user is logged in, if not redirect to login.php
$username = username($user);                                // get user name
$useremail = str_replace("'","",$_SESSION['useremail']);    // get email address of user, without quotes
$mytable = $user."collection";                              // user's collection table
$mtcestatus = mtcemode($user);                              // check mtce mode active and if an admin user
if($mtcestatus == 1):                                       // check if site is in maintenance mode
    include ('includes/mtcestub.php');
    session_destroy();
    exit();
endif;
