<?php
/* Version:     1.0
    Date:       23/10/16
    Name:       inidebug.class.php
    Purpose:    If we are set in the ini file to do pre-database connection debugging, 
 *              this class will log messages to logfiles or syslog
    Notes:      - 
    To do:      -
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2016 Simon Wilson
    
 *  1.0
                Initial version
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

class IniDebug {
    public function inidebugging($loglevelini,$logfile,$message) {
        if($loglevelini === '3' AND $logfile !== 0):
            $fd = fopen($logfile, "a");
            $msg = "[DEBUG] $message";
            $str = "[" . date("Y/m/d H:i:s", time()) . "] ".$msg;
            fwrite($fd, $str . "\n");
            fclose($fd); 
        elseif($loglevelini === '3' AND $logfile === 0):
            openlog("MTG", LOG_NDELAY, LOG_USER);
            syslog(LOG_INFO, "[MTG-DEBUG] $message");
            closelog();
        endif;
    }
}
