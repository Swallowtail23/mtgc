<?php
/* Version:     2.1
    Date:       25/03/23
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
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

//Error handling
function mtg_error($number,$string,$file,$line,$context='')
{
    global $logfile,$adminemail,$serveremail; //set in ini.php
    if (!(error_reporting() & $number)):
        // This error code is not included in error_reporting
        return;
    endif;
    switch ($number):
        case E_USER_ERROR:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": $string (E_USER_ERROR) in $file on line $line",$logfile);
            // writelog("[ERROR] mtg_error: $string (E_USER_ERROR) in $file on line $line",$logfile);
            $useremail = str_replace("'","",$_SESSION['useremail']);
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error (E_USER_ERROR) on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
        case E_USER_WARNING:    
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": $string (E_USER_WARNING) in $file on line $line",$logfile);
            //writelog("[ERROR] mtg_error: $string (E_USER_WARNING) in $file on line $line",$logfile);
            $useremail = str_replace("'","",$_SESSION['useremail']);
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error (E_USER_WARNING) on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
        case E_USER_NOTICE:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": $string (E_USER_NOTICE) in $file on line $line",$logfile);
            //writelog("[ERROR] mtg_error: $string (E_USER_NOTICE) in $file on line $line",$logfile);
            $useremail = str_replace("'","",$_SESSION['useremail']);
            $from = "From: $useremail\r\nReturn-path: $useremail";
            $subject = "Error (E_USER_NOTICE) on MTGCollection in file $file line $line";
            $message = wordwrap($string,70);
            mail($adminemail, $subject, $message, $from);
            echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
            exit();
            break;
        default:
            $obj = new Message;
            $obj->MessageTxt('[ERROR]',$_SERVER['PHP_SELF'],"Function ".__FUNCTION__.": $string Error in $file on line $line",$logfile);
            //writelog("[ERROR] mtg_error: $string Error in $file on line $line",$logfile);
            $useremail = str_replace("'","",$_SESSION['useremail']);
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

function writelog($msg,$log = '') 
    //slowly replacing direct calls with Message 
    //Class for later class rewrite to remove this function
{
    global $logfile;
    if ($log == ''):
        $log = $logfile;
    endif;
    // Assess level of message
    if (strpos($msg,"[DEBUG]") === 0):
        $msglevel = 3;
    elseif (strpos($msg,"[NOTICE]") === 0):
        $msglevel = 2;
    elseif (strpos($msg,"[ERROR]") === 0):
        $msglevel = 1;
    else: //catch any unassigned messages to '1' until all code checked
        $msglevel = 1;
    endif;
    
    // Find out currently set log level
    global $loglevelini;
    if(isset($loglevelini)):
        $loglevel = $loglevelini;
    else:
        $loglevel = 3; //If we can't get the log level, assume DEBUG
    endif;
    // Write message to log
    if ($msglevel < ($loglevel + 1)):
        if (($fd = fopen($log, "a")) !== false):
            $str = "[" . date("Y/m/d H:i:s", time()) . "] ".$msg;
            fwrite($fd, $str . "\n");
            fclose($fd); 
        else:
            openlog("MTG", LOG_NDELAY, LOG_USER);
            syslog(LOG_ERR, "Can't write to MTG log file $log - check path and permissions. Falling back to syslog.");
            syslog(LOG_NOTICE, $str);
            closelog();
        endif;
    endif;
}