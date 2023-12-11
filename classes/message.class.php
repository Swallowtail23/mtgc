<?php
/* Version:     2.0
    Date:       25/03/23
    Name:       message.class.php
    Purpose:    Simple message and log writing class, currently still using 
                writelog function to complete.
    Notes:      Usage:
                    $obj = new Message;
                    $obj->MessageTxt('[DEBUG]',$_SERVER['PHP_SELF'],"Message text $useremail from {$_SERVER['REMOTE_ADDR']}",$logfile);
  
    To do:      Plan is to move writelog functionality into here and recode 
                site to use this instead of writelog.
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
 *  2.0
 *              PHP 8.1 compatibility
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class Message {

    /*
     * Call with optional last parameter for different logfile - otherwise will 
     * write to globally set $logfile variable
     */
    public $textstring;
    public function MessageTxt($errorlevel,$source,$text,$logfile = '') {
        if ($logfile === ''):
            global $logfile;
        endif;
        $this->textstring = "$errorlevel $source: $text";
        writelog($this->textstring,$logfile);
    }
}