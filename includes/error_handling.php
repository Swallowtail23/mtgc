<?php

/*
Version:     2.5
Date:        25/11/25
Name:        error_handling.php
Purpose:     Process page initiation and setup error handling.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

function mtgError($number, $string, $file, $line, $context = '')
{
    global $logfile, $adminEmail, $serverEmail, $emailEnabled;
    $msg = new \MTG\Core\Message($logfile);

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
            if ($emailEnabled) :
                mail($adminEmail, $subject, $message, $from);
            else :
                $msg->logMessage(
                    '[NOTICE]',
                    "Email disabled; E_USER_ERROR notification not sent for $file:$line"
                );
            endif;
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
        case E_USER_WARNING:
            $msg->logMessage('[ERROR]', "$string (E_USER_WARNING) in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error (E_USER_WARNING) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                mail($adminEmail, $subject, $message, $from);
            else :
                $msg->logMessage(
                    '[NOTICE]',
                    "Email disabled; E_USER_WARNING notification not sent for $file:$line"
                );
            endif;
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
        case E_USER_NOTICE:
            $msg->logMessage('[ERROR]', "$string (E_USER_NOTICE) in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error (E_USER_NOTICE) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                mail($adminEmail, $subject, $message, $from);
            else :
                $msg->logMessage(
                    '[NOTICE]',
                    "Email disabled; E_USER_NOTICE notification not sent for $file:$line"
                );
            endif;
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
        default:
            $msg->logMessage('[ERROR]', "$string Error in $file on line $line");
            $from = "From: $userEmail\r\nReturn-path: $userEmail";
            $subject = "Error on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                mail($adminEmail, $subject, $message, $from);
            else :
                $msg->logMessage('[NOTICE]', "Email disabled; error notification not sent for $file:$line");
            endif;
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
    endswitch;
}

function mtgException($err)
{
    global $logfile, $adminEmail, $serverEmail, $emailEnabled;
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
    if ($emailEnabled) :
        mail($adminEmail, $subject, $message, $from);
    else :
        $fallback = new \MTG\Core\Message($logfile);
        $fallback->logMessage(
            '[NOTICE]',
            "Email disabled; exception notification not sent ({$err->getMessage()})"
        );
    endif;
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    exit();
}

set_error_handler('mtgError');
set_exception_handler('mtgException');
