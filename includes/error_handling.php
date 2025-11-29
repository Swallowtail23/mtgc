<?php

/*
Version:     2.4
Date:        25/11/25
Name:        error_handling.php
Purpose:     Process page initiation and setup error handling.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0         Removed hard-coded email address, now uses ini file variables
    2.1         Fix empty variable ($context)
    2.2 20/01/24 Move to logMessage
    2.3 28/02/25 Removed writelog() to message class file
    2.4 25/11/25 Standard tidy-up
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

function mtgError($number, $string, $file, $line, $context = '')
{
    global $logfile, $adminEmail, $serverEmail;
    $msg = new Message($logfile);

    if (isset($_SESSION['useremail']) && !empty($_SESSION['useremail'])) :
        $userEmail = $_SESSION['useremail'];
    else :
        $userEmail = $serverEmail;
    endif;

    if (!(error_reporting() & $number)) :
        return;
    endif;

    switch ($number) :
        case E_USER_ERROR:
            $msg->logMessage('[ERROR]', "$string (E_USER_ERROR) in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error (E_USER_ERROR) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            mail($adminEmail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
        case E_USER_WARNING:
            $msg->logMessage('[ERROR]', "$string (E_USER_WARNING) in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error (E_USER_WARNING) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            mail($adminEmail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
        case E_USER_NOTICE:
            $msg->logMessage('[ERROR]', "$string (E_USER_NOTICE) in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error (E_USER_NOTICE) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            mail($adminEmail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
        default:
            $msg->logMessage('[ERROR]', "$string Error in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            mail($adminEmail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
    endswitch;
}

function mtgException($err)
{
    global $logfile, $adminEmail, $serverEmail;
    if (($fd = fopen($logfile, 'a')) !== false) :
        $msg = "[ERROR] Fatal exception: {$err->getMessage()}";
        $str = "[" . date('Y/m/d H:i:s', time()) . "] " . $msg;
        fwrite($fd, $str . "\n");
        fclose($fd);
    else :
        openlog('MTG', LOG_NDELAY, LOG_USER);
        syslog(LOG_ERR, "[MTG-DEBUG] Fatal exception: {$err->getMessage()}");
        closelog();
    endif;
    $from = "From: " . $serverEmail;
    $subject = "Exception on MTGCollection";
    $message = wordwrap($err->getMessage(), 70);
    mail($adminEmail, $subject, $message, $from);
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    exit();
}

set_error_handler('mtgError');
set_exception_handler('mtgException');
