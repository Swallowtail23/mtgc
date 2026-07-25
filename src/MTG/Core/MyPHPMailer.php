<?php

/*
Version:     1.21
Date:        25/07/26
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
use PHPMailer\PHPMailer\SMTP;

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
        $senderEmail = trim((string) $this->appConfig->email('senderEmail', ''));
        if ($senderEmail === '') :
            $senderEmail = $serverEmail;
        endif;
        $siteTitle = (string) $this->appConfig->general('title', '');
        $smtpParameters = $this->appConfig->getSmtpParameters();

        // Set defaults for PHPMailer from ini.file
        $this->setFrom($serverEmail, $siteTitle);
        $this->addReplyTo($serverEmail, $siteTitle);
        $this->Sender = $senderEmail;
        $this->CharSet = self::CHARSET_UTF8;
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

        // SMTP protocol traces can expose email metadata and content, so only enable them at site DEBUG level.
        $configuredDebugLevel = $this->resolveSmtpDebugLevel($smtpParameters['SMTPDebug'] ?? 'off');
        if ($configuredDebugLevel === null) :
            $this->message->logMessage(
                '[NOTICE]',
                "Invalid SMTP debug setting '{$smtpParameters['SMTPDebug']}'; disabling SMTP debug"
            );
        elseif ($configuredDebugLevel === SMTP::DEBUG_OFF) :
            $this->message->logMessage(
                '[DEBUG]',
                "SMTP debug is off ({$smtpParameters['SMTPDebug']})"
            );
        elseif ((int) $smtpParameters['globalDebug'] === 3) :
            $this->SMTPDebug = $configuredDebugLevel;
            $this->Debugoutput = function (string $message, int $level): void {
                $message = trim($message);
                if ($message !== '') :
                    $this->message->logMessage('[DEBUG]', "[SMTP:$level] $message");
                endif;
            };
            $this->message->logMessage('[DEBUG]', "SMTP debug is on (level $this->SMTPDebug)");
        else :
            $this->message->logMessage(
                '[NOTICE]',
                "SMTP debug level $configuredDebugLevel requested, but site log level is not DEBUG; "
                . 'disabling SMTP debug'
            );
        endif;
    }

    private function resolveSmtpDebugLevel(mixed $value): ?int
    {
        $levels = [
            '' => SMTP::DEBUG_OFF,
            'off' => SMTP::DEBUG_OFF,
            '0' => SMTP::DEBUG_OFF,
            'smtp::debug_off' => SMTP::DEBUG_OFF,
            'client' => SMTP::DEBUG_CLIENT,
            '1' => SMTP::DEBUG_CLIENT,
            'smtp::debug_client' => SMTP::DEBUG_CLIENT,
            'server' => SMTP::DEBUG_SERVER,
            '2' => SMTP::DEBUG_SERVER,
            'smtp::debug_server' => SMTP::DEBUG_SERVER,
            'connection' => SMTP::DEBUG_CONNECTION,
            '3' => SMTP::DEBUG_CONNECTION,
            'smtp::debug_connection' => SMTP::DEBUG_CONNECTION,
            'lowlevel' => SMTP::DEBUG_LOWLEVEL,
            '4' => SMTP::DEBUG_LOWLEVEL,
            'smtp::debug_lowlevel' => SMTP::DEBUG_LOWLEVEL,
        ];
        $setting = strtolower(trim((string) $value));

        return $levels[$setting] ?? null;
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
                $this->addEmailAttachment($attachment, $attachmentname);
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
                        $this->addEmailAttachment($path, $name);
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

    private function addEmailAttachment(string $path, string $name = ''): void
    {
        $filename = $name !== '' ? $name : $path;
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'csv') :
            $this->addAttachment($path, $name, self::ENCODING_BASE64, 'text/csv; charset=utf-8');
            return;
        endif;

        $this->addAttachment($path, $name);
    }
}
