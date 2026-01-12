<?php

/*
Version:     1.11
Date:        12/01/26
Name:        IniDebug.php
Purpose:     Pre-database debugging; logs messages to logfiles or syslog when enabled.
Notes:       Not currently used in code.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

class IniDebug
{
    private $logfile;
    private $message;
    private $appConfig;

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
        $this->logfile = $this->appConfig->general('logFile', '');
        $this->message = new Message($this->appConfig);
    }

    public function inidebugging($message)
    {
        $logLevel = (string) $this->appConfig->general('logLevel', '');
        $logfile = $this->logfile;

        if ($logLevel === '3' and $logfile !== '') :
            $fd = fopen($logfile, 'a');
            $msg = "[DEBUG] $message";
            $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
            fwrite($fd, $str . "\n");
            fclose($fd);
        elseif ($logLevel === '3' and $logfile === '') :
            openlog("MTG", LOG_NDELAY, LOG_USER);
            syslog(LOG_INFO, "[MTG-DEBUG] $message");
            closelog();
        endif;
    }
}
