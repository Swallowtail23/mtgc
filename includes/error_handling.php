<?php

/*
Version:     2.9
Date:        11/01/26
Name:        error_handling.php
Purpose:     Process page initiation and setup error handling.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\AppConfig;
use MTG\Core\Message;
use MTG\Core\MyPHPMailer;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

function mtgError($number, $string, $file, $line, AppConfig $appConfig)
{
    $msg = new Message($appConfig);
    $adminEmail = (string) $appConfig->email('adminEmail', '');
    $serverEmail = (string) $appConfig->email('serverEmail', '');
    $emailEnabled = (bool) $appConfig->email('enabled', false);

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
            $subject = "Error (E_USER_ERROR) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                $mail = new MyPHPMailer(true, $appConfig);
                $mail->clearReplyTos();
                if ($userEmail !== '') :
                    $mail->addReplyTo($userEmail, $userEmail);
                endif;
                $mail->sendEmail($adminEmail, false, $subject, $message);
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
            $subject = "Error (E_USER_WARNING) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                $mail = new MyPHPMailer(true, $appConfig);
                $mail->clearReplyTos();
                if ($userEmail !== '') :
                    $mail->addReplyTo($userEmail, $userEmail);
                endif;
                $mail->sendEmail($adminEmail, false, $subject, $message);
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
            $subject = "Error (E_USER_NOTICE) on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                $mail = new MyPHPMailer(true, $appConfig);
                $mail->clearReplyTos();
                if ($userEmail !== '') :
                    $mail->addReplyTo($userEmail, $userEmail);
                endif;
                $mail->sendEmail($adminEmail, false, $subject, $message);
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
            $subject = "Error on MTGCollection in file $file line $line";
            $message = wordwrap($string, 70);
            if ($emailEnabled) :
                $mail = new MyPHPMailer(true, $appConfig);
                $mail->clearReplyTos();
                if ($userEmail !== '') :
                    $mail->addReplyTo($userEmail, $userEmail);
                endif;
                $mail->sendEmail($adminEmail, false, $subject, $message);
            else :
                $msg->logMessage('[NOTICE]', "Email disabled; error notification not sent for $file:$line");
            endif;
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
    endswitch;
}

function mtgException($err, AppConfig $appConfig)
{
    $logfile = (string) $appConfig->general('logFile', '');
    $adminEmail = (string) $appConfig->email('adminEmail', '');
    $serverEmail = (string) $appConfig->email('serverEmail', '');
    $emailEnabled = (bool) $appConfig->email('enabled', false);
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
    $subject = "Exception on MTGCollection";
    $message = wordwrap($err->getMessage(), 70);
    if ($emailEnabled) :
        $mail = new MyPHPMailer(true, $appConfig);
        $mail->sendEmail($adminEmail, false, $subject, $message);
    else :
        $fallback = new Message($appConfig);
        $fallback->logMessage(
            '[NOTICE]',
            "Email disabled; exception notification not sent ({$err->getMessage()})"
        );
    endif;
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    exit();
}

set_error_handler(function ($number, $string, $file, $line) use ($appConfig) {
    mtgError($number, $string, $file, $line, $appConfig);
});
set_exception_handler(function ($err) use ($appConfig) {
    mtgException($err, $appConfig);
});
