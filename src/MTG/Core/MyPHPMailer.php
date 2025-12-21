<?php

/*
Version:     1.9
Date:        20/12/25
Name:        MyPHPMailer.php
Purpose:     Extends PHPMailer with standard options.
Notes:       Usage:
                 $mail = new \MTG\Core\MyPHPMailer(true, $smtpParameters, $serverEmail, $logfile);
                 $mailresult = $mail->sendEmail($adminEmail, false, $subject, $body);
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/../../../vendor/autoload.php";

class MyPHPMailer extends PHPMailer
{
    /**
     * MyPHPMailer constructor.
     *
     * @param bool|null $exceptions
     * @param string    $body A default HTML message body
     */
    private $smtpParameters;
    private $serverEmail;
    private $logfile;
    private $message;
    private $siteTitle;

    public function __construct($exceptions, $smtpParameters, $serverEmail, $logfile, $siteTitle = null)
    {
        //Don't forget to do this or other things may not be set correctly!
        parent::__construct($exceptions);
        // Set variables
        $this->smtpParameters = $smtpParameters;
        $this->serverEmail = $serverEmail;
        $this->logfile = $logfile;
        $this->message = new \MTG\Core\Message($this->logfile);
        $this->siteTitle = $siteTitle ?: $GLOBALS['siteTitle'];

        // Set defaults for PHPMailer from ini.file
        $this->setFrom($this->serverEmail, $this->siteTitle);
        $this->addReplyTo($this->serverEmail, $this->siteTitle);
        $this->isSMTP();
        $this->Host       = $smtpParameters['SMTPHost'];
        $this->Helo       = $smtpParameters['SMTPHelo'] ?? gethostname();
        $this->Port       = $smtpParameters['SMTPPort'];
        $this->SMTPAuth   = $smtpParameters['SMTPAuth'];
        $this->Username   = $smtpParameters['SMTPUsername'];
        $this->Password   = $smtpParameters['SMTPPassword'];
        $this->SMTPSecure = $smtpParameters['SMTPSecure'];
        $this->SMTPAutoTLS = true;
        if (isset($smtpParameters['SMTPVerifySSL']) && !$smtpParameters['SMTPVerifySSL']) {
            $this->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }

        // Check if debugging is required
        if ($smtpParameters['SMTPDebug'] === 'SMTP::DEBUG_OFF') :
            $this->message->logMessage(
                '[DEBUG]',
                "SMTP debug is off ({$smtpParameters['SMTPDebug']},{$this->SMTPDebug})"
            );
        elseif ($smtpParameters['SMTPDebug'] !== 'SMTP::DEBUG_OFF' && $smtpParameters['globalDebug'] == 3) :
            $this->SMTPDebug  = $smtpParameters['SMTPDebug'];
            $this->message->logMessage('[DEBUG]', "SMTP debug is on ({$this->SMTPDebug})");
        else :
            $this->message->logMessage(
                '[NOTICE]',
                "SMTP debug is on ({$this->SMTPDebug}), but site log level not at DEBUG; NOT setting to SMTP debug"
            );
        endif;
    }

    public function sendEmail(
        $recipient,
        $html,
        $subject,
        $body,
        $altbody = '',
        $attachment = '',
        $attachmentname = '',
        $attachments = []
    ) {
        if (!isset($GLOBALS['emailEnabled']) || $GLOBALS['emailEnabled'] !== true) :
            $this->message->logMessage(
                '[NOTICE]',
                "Email disabled; skipping send to $recipient with subject '$subject'"
            );
            return false;
        endif;

        try {
            $this->addAddress($recipient);
            $this->Subject = $subject;
            $this->Body    = $body;

            if ($html === true) :
                $this->isHTML(true);
                if ($altbody !== '') :
                    $this->AltBody = $altbody;
                endif;
            endif;

            if ($attachment !== '') :
                $this->addAttachment($attachment, $attachmentname);
            endif;
            if (!empty($attachments)) :
                $this->message->logMessage(
                    '[DEBUG]',
                    "Adding " . count($attachments) . " additional attachments for $recipient"
                );
                foreach ($attachments as $extra) :
                    $path = $extra['path'] ?? ($extra[0] ?? '');
                    $name = $extra['name'] ?? ($extra[1] ?? '');
                    if ($path !== '') :
                        $this->addAttachment($path, $name);
                    endif;
                endforeach;
            endif;

            // Send
            $this->send();
            $this->message->logMessage('[DEBUG]', "Email sent to $recipient");
            return true;
        } catch (Exception $e) {
            $this->message->logMessage('[ERROR]', "Email NOT sent to $recipient ({$e->getMessage()})");
            return false;
        }
    }
}
