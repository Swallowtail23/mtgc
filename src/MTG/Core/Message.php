<?php

/*
Version:     1.10
Date:        25/07/26
Name:        Message.php
Purpose:     Simple message and log writing class with internal logging.
Notes:       Usage:
                 $msg = new Message($appConfig);
                 $msg->logMessage('[DEBUG]', "Message text");
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

class Message
{
    private mixed $logfile;
    private int $logLevel;
    private AppConfig $appConfig;
    public string $textstring = '';

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
        $this->logfile = $this->appConfig->general('logFile', '');
        $this->logLevel = (int) $this->appConfig->general('logLevel', 3);
    }

    public function logMessage(string $errorlevel, string $text, string $logfile = ''): void
    {
        $effectiveLogfile = $logfile ?: $this->logfile;
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller_info = $this->findCallerInfo($backtrace);

        $this->textstring = "$errorlevel {$caller_info}: $text";
        $this->writelog($this->textstring, $effectiveLogfile);
    }

    private function writelog(string $msg, string $log = ''): void
    {
        $log = $log ?: $this->logfile;

        // Empty log path means syslog fallback
        if ($log === '') :
            openlog("MTG", LOG_NDELAY, LOG_USER);
            syslog(LOG_NOTICE, $msg);
            closelog();
            return;
        endif;

        if (strpos($msg, "[DEBUG]") === 0) :
            $msglevel = 3;
        elseif (strpos($msg, "[NOTICE]") === 0) :
            $msglevel = 2;
        elseif (strpos($msg, "[ERROR]") === 0) :
            $msglevel = 1;
        else :
            $msglevel = 1;
        endif;

        $loglevel = (int) $this->logLevel;

        if ($msglevel <= $loglevel) :
            $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
            if (($fd = fopen($log, "a")) !== false) :
                fwrite($fd, $str . "\n");
                fclose($fd);
            else :
                openlog("MTG", LOG_NDELAY, LOG_USER);
                syslog(
                    LOG_ERR,
                    "Can't write to MTG log file $log - check path and permissions. Falling back to syslog."
                );
                syslog(LOG_NOTICE, $str);
                closelog();
            endif;
        endif;
    }

    /**
    * @param array<int, array<string, mixed>> $backtrace
    */
    private function findCallerInfo(array $backtrace): string
    {
        $caller = $backtrace[0] ?? null;

        if ($caller) :
            $file = isset($caller['file']) ? basename($caller['file']) : 'Unknown file';
            $line = isset($caller['line']) ? $caller['line'] : 'Unknown line';

            $functionName = '';
            $callerFunction = $backtrace[1]['function'] ?? '';
            if (
                is_string($callerFunction)
                && $callerFunction !== 'logMessage'
                && !str_ends_with($callerFunction, '{closure}')
            ) :
                $functionName = ": Function " . $callerFunction;
            endif;

            return "$file $line$functionName";
        endif;

        return 'Unknown file Unknown line';
    }
}
