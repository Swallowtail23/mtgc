<?php
/* Version:     4.0
    Date:       28/02/25
    Name:       message.class.php
    Purpose:    Simple message and log writing class, now with internal logging.
    Notes:      Usage:
                    $msg = new Message($logfile);
                    $msg->logMessage('[DEBUG]', "Message text");
  
    To do:      
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2025 Simon Wilson

 *  1.0
                Initial version
 *  2.0
 *              PHP 8.1 compatibility
 
 *  3.0         14/01/24
                Bring 'source' into the message function - all calls to be migrated.
 * 
 *  4.0         28/02/25
 *              Moved writelog() here from error_handling.php
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class Message 
{
    private $logfile;
    public $textstring;

    public function __construct($logfile = null)
    {
        $this->logfile = $logfile ?: $GLOBALS['logfile'];
    }
    
    public function logMessage($errorlevel, $text, $logfile = '')
    {
        $logfile = $logfile ?: $this->logfile;
        $backtrace = debug_backtrace();
        $caller_info = $this->findCallerInfo($backtrace);

        $this->textstring = "$errorlevel {$caller_info}: $text";
        $this->writelog($this->textstring, $logfile);
    }
    
    private function writelog($msg, $log = '')
    {
        $log = $log ?: $this->logfile;
        
        if (strpos($msg, "[DEBUG]") === 0):
            $msglevel = 3;
        elseif (strpos($msg, "[NOTICE]") === 0):
            $msglevel = 2;
        elseif (strpos($msg, "[ERROR]") === 0):
            $msglevel = 1;
        else:
            $msglevel = 1;
        endif;
        
        if (isset($GLOBALS['loglevelini'])):
            $loglevel = $GLOBALS['loglevelini'];
        else:
            $loglevel = 3;
        endif;
        
        if ($msglevel < ($loglevel + 1)):
            if (($fd = fopen($log, "a")) !== false):
                $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
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
    
    private function findCallerInfo($backtrace)
    {
        $caller = $backtrace[0] ?? null;
        
        if ($caller):
            $file = isset($caller['file']) ? basename($caller['file']) : 'Unknown file';
            $line = isset($caller['line']) ? $caller['line'] : 'Unknown line';
            
            $functionName = '';
            if (isset($backtrace[1]['function']) && $backtrace[1]['function'] !== 'logMessage'):
                $functionName = ": Function " . $backtrace[1]['function'];
            endif;
            
            return "$file $line$functionName";
        endif;
        
        return 'Unknown file Unknown line';
    }
}