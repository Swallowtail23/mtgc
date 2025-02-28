<?php
/* Version:     2.3
    Date:       28/02/25
    Name:       error_handling.php
    Purpose:    PHP script to process page initiation and setup
    Notes:      {none}
 * 
    1.0
                Initial version
 *  2.0 
 *              Removed hard-coded email address, now uses ini file variables
 *  2.1
 *              Fix empty variable ($context)
 * 
 *  2.2         20/01/24
 *              Move to logMessage
 * 
 *  2.3         28/02/25
 *              Removed writelog() to message class file
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

//Error handling
function mtg_error($number,$string,$file,$line,$context='')
{
    global $logfile,$adminemail,$serveremail; //set in ini.php
    $msg = new Message($logfile);
    
    if (isset($_SESSION['useremail']) AND !empty($_SESSION['useremail'])):
        $useremail = $_SESSION['useremail'];
    else:
        $useremail = $serveremail;
    endif;
    if (!(error_reporting() & $number)):
        // This error code is not included in error_reporting
        return;
    endif;
    switch ($number):
        case E_USER_ERROR:
            $msg->logMessage('[ERROR]',"$string (E_USER_ERROR) in $file on line $line");
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error (E_USER_ERROR) on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
        case E_USER_WARNING:    
            $msg->logMessage('[ERROR]',"$string (E_USER_WARNING) in $file on line $line");
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error (E_USER_WARNING) on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
        case E_USER_NOTICE:
            $msg->logMessage('[ERROR]',"$string (E_USER_NOTICE) in $file on line $line");
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error (E_USER_NOTICE) on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
        default:
            $msg->logMessage('[ERROR]',"$string Error in $file on line $line");
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
    endswitch;
}

function mtg_exception($err) {  //Don't rely on writelog function being available
    global $logfile,$adminemail,$serveremail; //set in ini.php
    if(($fd = fopen($logfile, "a")) !== false):
        $msg = "[ERROR] Fatal exception: {$err->getMessage()}";
        $str = "[" . date("Y/m/d H:i:s", time()) . "] ".$msg;
        fwrite($fd, $str . "\n");
        fclose($fd); 
    else:
        openlog("MTG", LOG_NDELAY, LOG_USER);
        syslog(LOG_ERR, "[MTG-DEBUG] Fatal exception: {$err->getMessage()}");
        closelog();
    endif;
    $from = "From: ".$serveremail;
    $subject = "Exception on MTGCollection";
    $message = wordwrap($err->getMessage(),70);
    mail($adminemail, $subject, $message, $from);
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    exit();
}
//Set error handlers to be the functions just defined
set_error_handler('mtg_error');
set_exception_handler('mtg_exception');