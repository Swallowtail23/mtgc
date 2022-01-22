<?php
/* Version:     1.0
    Date:       17/10/16
    Name:       logout.php
    Purpose:    Destroy the session, log it, and head to login.php
    Notes:      {none}
    To do:      -
    
    1.0
                Initial version
*/
session_start();
session_destroy();
date_default_timezone_set('Australia/Brisbane');
$filename = "/var/log/mtg/mtgapp.log";
$msg = "User ".$_SESSION['useremail']." logged out from ".$_SERVER['REMOTE_ADDR']."";
if (($fd = fopen($filename, "a")) !== false):
    $str = "[" . date("Y/m/d H:i:s", time()) . "] ".$msg;
    fwrite($fd, $str . "\n");
    fclose($fd); 
else:
    openlog("MTG", LOG_NDELAY, LOG_USER);
    syslog(LOG_ERR, "Can't write to MTG log file - check path and permissions. Falling back to syslog.");
    syslog(LOG_ERR, $str);
    closelog();
endif;
header('Location: login.php');
exit;