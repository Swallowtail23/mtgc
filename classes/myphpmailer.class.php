<?php
/* Version:     1.1
    Date:       20/01/24
    Name:       myPHPMailer.class.php
    Purpose:    extends PHPMailer with standard options
    Notes:      To use, instantiate a new:
 *                  $mail = new myPHPMailer(true, $smtpParameters, $serveremail, $logfile);
 *              Call as follows:
 *                  $mailresult = $mail->sendEmail($adminemail, FALSE, $subject, $body);
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2024 Simon Wilson
    
 *  1.0         13/01/24
                Initial version
 * 
 *  1.1         20/01/24
 *              Move to logMessage
*/

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/../vendor/autoload.php";

class myPHPMailer extends PHPMailer
{
    /**
     * myPHPMailer constructor.
     *
     * @param bool|null $exceptions
     * @param string    $body A default HTML message body
     */
    private $smtpParameters;
    private $serveremail;
    private $logfile;
    private $message;
    
    public function __construct($exceptions, $smtpParameters, $serveremail, $logfile)
    {
        //Don't forget to do this or other things may not be set correctly!
        parent::__construct($exceptions);
        // Set variables
        $this->smtpParameters = $smtpParameters;
        $this->serveremail = $serveremail;
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
        
        // Set defaults for PHPMailer from ini.file
        $this->setFrom($this->serveremail, 'MtG Collection');
        $this->addReplyTo($this->serveremail, 'MtG Collection');
        $this->isSMTP();
        $this->Host       = $smtpParameters['SMTPHost'];
        $this->Port       = $smtpParameters['SMTPPort'];
        $this->SMTPAuth   = $smtpParameters['SMTPAuth'];
        $this->Username   = $smtpParameters['SMTPUsername'];
        $this->Password   = $smtpParameters['SMTPPassword'];
        $this->SMTPSecure = $smtpParameters['SMTPSecure'];
        
        // Check if debugging is required
        if($smtpParameters['SMTPDebug'] === 'SMTP::DEBUG_OFF'):
            $this->message->logMessage('[DEBUG]',"SMTP debug is off ({$smtpParameters['SMTPDebug']},{$this->SMTPDebug})");
        elseif($smtpParameters['SMTPDebug'] !== 'SMTP::DEBUG_OFF' && $smtpParameters['globalDebug'] == 3):
            $this->SMTPDebug  = $smtpParameters['SMTPDebug'];
            $this->message->logMessage('[DEBUG]',"SMTP debug is on ({$this->SMTPDebug})");
        else:
            $this->message->logMessage('[NOTICE]',"SMTP debug is on ({$this->SMTPDebug}), but site log level not at DEBUG; NOT setting to SMTP debug");
        endif;
    }
    
    public function sendEmail($recipient, $html, $subject, $body, $altbody = '', $attachment = '', $attachmentname = '')
    {
        try {
            $this->addAddress($recipient);
            $this->Subject = $subject;
            $this->Body    = $body;
            
            if($html === TRUE):
                $this->isHTML(true);
                if($altbody !== ''):
                    $this->AltBody = $altbody;
                endif;
            endif;

            if($attachment !== ''):
                $this->addAttachment($attachment, $attachmentname);
            endif;

            // Send
            $this->send();
            $this->message->logMessage('[DEBUG]',"Email sent to $recipient");
            return TRUE;
        } catch (Exception $e) {
            $this->message->logMessage('[ERROR]',"Email NOT sent to $recipient ({$e->getMessage()})");
            return FALSE;
        }
    }
}
