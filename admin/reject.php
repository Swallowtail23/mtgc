<?php
/* Version:     2.1
    Date:       20/01/24
    Name:       admin/reject.php
    Purpose:    Non-admin rejection page called by admin pages on attempted load
                by non-admin or from non-secure page (if set in ini file)
    Notes:      
        
    1.0
                Initial version
 *  2.0 
 *              Moved to Message class from writelog
  * 
 *  2.1         20/01/24
 *              Move to include sessionname and logMessage
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;?>

<!DOCTYPE html>
    <head>
        <title>MtG collection - admin (reject)</title>
        <link rel="manifest" href="manifest.json" />
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" type="text/css" href="/css/style<?php echo $cssver?>.css">
        <?php include('../includes/googlefonts.php');?>
    </head>
    <body id="body" class="body">

    <?php 
    include '../includes/overlays.php'; 
    include '../includes/header.php';
    require('../includes/menu.php');
    $msg = new Message($logfile);
    ?>
    <div id='page'>
        <div class='staticpagecontent'>
            <?php 
            $msg->logMessage('[ERROR]',"Admin page called by user number {$_SESSION['user']}, admin status is $admin");
            if ($admin == 3): 
                echo "<meta http-equiv='refresh' content='2;url=../index.php'>";
                echo "<div class='alert-box error' id='adminerror'><span>error: </span>"
                        . "Insufficient rights to access this page. "
                        . "Redirecting to main page.</div>";    
                $msg->logMessage('[ERROR]',"Admin page called by non-admin user from ".$_SERVER['REMOTE_ADDR'].", exiting");
                exit();
            elseif ($admin == 2): 
                echo "<meta http-equiv='refresh' content='2;url=../index.php'>";
                echo "<div class='alert-box error' id='adminerror'><span>error: </span>"
                        . "This page only accessible from location specified in ini file. "
                        . "Redirecting to main page.</div>";    
                $msg->logMessage('[ERROR]',"Admin page called by admin user from non-secure location: ".$_SERVER['REMOTE_ADDR'].", exiting");
                exit();
            endif;