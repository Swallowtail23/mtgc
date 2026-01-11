<?php

/*
Version:     1.0
Date:        11/01/26
Name:        ErrorHandler.php
Purpose:     Centralised error and exception handling.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Core;

class ErrorHandler
{
    /**
    * @var AppConfig
    */
    private $appConfig;

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function handleError($number, $string, $file, $line): void
    {
        $msg = new Message($this->appConfig);
        $adminEmail = (string) $this->appConfig->email('adminEmail', '');
        $serverEmail = (string) $this->appConfig->email('serverEmail', '');
        $emailEnabled = (bool) $this->appConfig->email('enabled', false);

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
                $notice = "Email disabled; E_USER_ERROR notification not sent for $file:$line";
                $this->sendErrorEmail(
                    $userEmail,
                    $adminEmail,
                    $subject,
                    $message,
                    $emailEnabled,
                    $notice,
                    $msg
                );
                echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
                exit();
            case E_USER_WARNING:
                $msg->logMessage('[ERROR]', "$string (E_USER_WARNING) in $file on line $line");
                $subject = "Error (E_USER_WARNING) on MTGCollection in file $file line $line";
                $message = wordwrap($string, 70);
                $notice = "Email disabled; E_USER_WARNING notification not sent for $file:$line";
                $this->sendErrorEmail(
                    $userEmail,
                    $adminEmail,
                    $subject,
                    $message,
                    $emailEnabled,
                    $notice,
                    $msg
                );
                echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
                exit();
            case E_USER_NOTICE:
                $msg->logMessage('[ERROR]', "$string (E_USER_NOTICE) in $file on line $line");
                $subject = "Error (E_USER_NOTICE) on MTGCollection in file $file line $line";
                $message = wordwrap($string, 70);
                $notice = "Email disabled; E_USER_NOTICE notification not sent for $file:$line";
                $this->sendErrorEmail(
                    $userEmail,
                    $adminEmail,
                    $subject,
                    $message,
                    $emailEnabled,
                    $notice,
                    $msg
                );
                echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
                exit();
            default:
                $msg->logMessage('[ERROR]', "$string Error in $file on line $line");
                $subject = "Error on MTGCollection in file $file line $line";
                $message = wordwrap($string, 70);
                $notice = "Email disabled; error notification not sent for $file:$line";
                $this->sendErrorEmail(
                    $userEmail,
                    $adminEmail,
                    $subject,
                    $message,
                    $emailEnabled,
                    $notice,
                    $msg
                );
                echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
                exit();
        endswitch;
    }

    public function handleException($err): void
    {
        $logfile = (string) $this->appConfig->general('logFile', '');
        $adminEmail = (string) $this->appConfig->email('adminEmail', '');
        $emailEnabled = (bool) $this->appConfig->email('enabled', false);
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
            $mail = new MyPHPMailer(true, $this->appConfig);
            $mail->sendEmail($adminEmail, false, $subject, $message);
        else :
            $fallback = new Message($this->appConfig);
            $fallback->logMessage(
                '[NOTICE]',
                "Email disabled; exception notification not sent ({$err->getMessage()})"
            );
        endif;
        echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
        exit();
    }

    private function sendErrorEmail(
        string $userEmail,
        string $adminEmail,
        string $subject,
        string $message,
        bool $emailEnabled,
        string $noticeMessage,
        Message $msg
    ): void {
        if ($emailEnabled) :
            $mail = new MyPHPMailer(true, $this->appConfig);
            $mail->clearReplyTos();
            if ($userEmail !== '') :
                $mail->addReplyTo($userEmail, $userEmail);
            endif;
            $mail->sendEmail($adminEmail, false, $subject, $message);
        else :
            $msg->logMessage('[NOTICE]', $noticeMessage);
        endif;
    }
}
