<?php

/*
Version:     1.3
Date:        25/11/25
Name:        myphpmailer.class.php
Purpose:     Extends PHPMailer with standard options.
Notes:       Usage:
                 $mail = new MyPHPMailer(true, $smtpParameters, $serveremail, $logfile);
                 $mailresult = $mail->sendEmail($adminemail, false, $subject, $body);
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 13/01/24 Initial version
    1.1 20/01/24 Move to logMessage
    1.2 25/11/25 Standard tidy-up
    1.3 25/11/25 Rename class to PascalCase
*/

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/../vendor/autoload.php";

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, PSR1.Files.SideEffects.FoundWithSymbols
class MyPHPMailer extends PHPMailer
{
    /**
     * MyPHPMailer constructor.
     *
     * @param bool|null $exceptions
     * @param string    $body A default HTML message body
     */
    private $smtpParameters;
    private $serveremail;
    private $logfile;
    private $message;
    private $siteTitle;

    public function __construct($exceptions, $smtpParameters, $serveremail, $logfile, $siteTitle = null)
    {
        //Don't forget to do this or other things may not be set correctly!
        parent::__construct($exceptions);
        // Set variables
        $this->smtpParameters = $smtpParameters;
        $this->serveremail = $serveremail;
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
        $this->siteTitle = $siteTitle ?: $GLOBALS['siteTitle'];

        // Set defaults for PHPMailer from ini.file
        $this->setFrom($this->serveremail, $this->siteTitle);
        $this->addReplyTo($this->serveremail, $this->siteTitle);
        $this->isSMTP();
        $this->Host       = $smtpParameters['SMTPHost'];
        $this->Port       = $smtpParameters['SMTPPort'];
        $this->SMTPAuth   = $smtpParameters['SMTPAuth'];
        $this->Username   = $smtpParameters['SMTPUsername'];
        $this->Password   = $smtpParameters['SMTPPassword'];
        $this->SMTPSecure = $smtpParameters['SMTPSecure'];

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

    public function sendEmail($recipient, $html, $subject, $body, $altbody = '', $attachment = '', $attachmentname = '')
    {
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
