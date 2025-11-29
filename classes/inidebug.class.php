<?php

/*
Version:     1.1
Date:        25/11/25
Name:        inidebug.class.php
Purpose:     Pre-database debugging; logs messages to logfiles or syslog when enabled.
Notes:       Not currently used in code.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 23/10/16 Initial version
    1.1 25/11/25 Standard tidy-up
*/

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, PSR1.Files.SideEffects.FoundWithSymbols
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

class IniDebug
{
    private $logfile;
    private $message;

    public function __construct($logfile)
    {
        $this->logfile = $logfile;
        $this->message = new Message($this->logfile);
    }

    public function inidebugging($logLevelIni, $logfile, $message)
    {
        if ($logLevelIni === '3' and $logfile !== 0) :
            $fd = fopen($logfile, "a");
            $msg = "[DEBUG] $message";
            $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
            fwrite($fd, $str . "\n");
            fclose($fd);
        elseif ($logLevelIni === '3' and $logfile === 0) :
            openlog("MTG", LOG_NDELAY, LOG_USER);
            syslog(LOG_INFO, "[MTG-DEBUG] $message");
            closelog();
        endif;
    }

    public function __toString()
    {
        $this->message->logMessage("[ERROR]", "Called as string");
        return "Called as a string";
    }
}
// phpcs:enable
