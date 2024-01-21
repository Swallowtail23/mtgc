<?php
/* Version:     1.0
    Date:       17/10/16
    Name:       mtcestub.php
    Purpose:    PHP script to display Maintenance message
    Notes:      {none}
 * 
    1.0
                Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<meta http-equiv='refresh' content='3;url=../login.php'>
<html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='initial-scale=1'>
        <?php echo "<title>$siteTitle</title><link rel='stylesheet' type='text/css' href='/style$cssver.css'>"; ?>
        <?php include('includes/googlefonts.php');?>
    </head>
    <body>
        <div id ='page'>
            <div class='alert-box error' id='adminerror'>Site is down for maintenance. Redirecting to login page.</div>
            <?php require('includes/header.php');  //build header ?>
        </div>
    </body>