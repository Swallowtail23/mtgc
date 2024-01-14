<?php
/* Version:     3.0
    Date:       14/01/24
    Name:       message.class.php
    Purpose:    Simple message and log writing class, currently still using 
                writelog function to complete.
    Notes:      Usage:
                    $obj = new Message($logfile);
                    $obj->MessageFunction('[DEBUG]',"Message text");
  
    To do:      
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
 *  2.0
 *              PHP 8.1 compatibility
 * 
 *  3.0        14/01/24
               Bring 'source' into the message function - all calls to be migrated, 
               but the function and class remain backwards compatible for now
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class Message 
{
    /*
     * Call with optional last parameter for different logfile - otherwise will 
     * write to globally set $logfile variable
     */
    
    private $logfile;
    public $textstring;
    
    // When migration to MessageFunction is complete, the fallback should be removed
    public function __construct($logfile = null)
    {
        $this->logfile = $logfile ?: $GLOBALS['logfile'];
    }

    public function MessageTxt($errorlevel,$source,$text,$logfile = '') 
    {
        $logfile = $logfile ?: $this->logfile;
        $this->textstring = "$errorlevel $source: $text";
        writelog($this->textstring,$logfile);
    }
    
    // Migrating to class function which does not need to have the source set, but gets it automatically
    // This is to improve logging consistency
    
    public function logMessage($errorlevel,$text,$logfile = '') 
    {
        $logfile = $logfile ?: $this->logfile;
        $backtrace = debug_backtrace();
        $functionName = isset($backtrace[1]['function']) ? " " . $backtrace[1]['function'] . " " : "";
        $source = basename(__FILE__) . " " . __LINE__ . $functionName;
        $this->textstring = "$errorlevel $source: $text";
        writelog($this->textstring,$logfile);
    }
}