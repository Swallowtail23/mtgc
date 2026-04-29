<?php

/*
Version:     1.17
Date:        29/04/26
Name:        MyPHPMailer.php
Purpose:     Extends PHPMailer with standard options.
Notes:       Usage:
                 $mail = new MyPHPMailer(true, $appConfig);
                 $mailresult = $mail->sendEmail($adminEmail, false, $subject, $body);
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require APP_ROOT . '/vendor/autoload.php';

class MyPHPMailer extends PHPMailer
{
    /**
     * MyPHPMailer constructor.
     *
     * @param bool|null $exceptions
     * @param AppConfig $appConfig
     */
    private Message $message;
    private bool $emailEnabled;
    private AppConfig $appConfig;

    public function __construct(?bool $exceptions, AppConfig $appConfig)
    {
        //Don't forget to do this or other things may not be set correctly!
        parent::__construct($exceptions);
        // Set variables
        $this->appConfig = $appConfig;
        $this->message = new Message($this->appConfig);
        $this->emailEnabled = (bool) $this->appConfig->email('enabled', false);
        $serverEmail = (string) $this->appConfig->email('serverEmail', '');
        $siteTitle = (string) $this->appConfig->general('title', '');
        $smtpParameters = $this->appConfig->getSmtpParameters();

        // Set defaults for PHPMailer from ini.file
        $this->setFrom($serverEmail, $siteTitle);
        $this->addReplyTo($serverEmail, $siteTitle);
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
        string $recipient,
        bool $html,
        string $subject,
        string $body,
        string $altbody = '',
        string $attachment = '',
        string $attachmentname = '',
        array $attachments = []
    ): bool {
        if ($this->emailEnabled !== true) :
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
