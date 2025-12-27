<?php

/*
Version:     1.2
Date:        27/12/25
Name:        Message.php
Purpose:     Simple message and log writing class with internal logging.
Notes:       Usage:
                 $msg = new \MTG\Core\Message($logfile);
                 $msg->logMessage('[DEBUG]', "Message text");
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

class Message
{
    private $logfile;
    private $logLevel;
    public $textstring;

    public function __construct($logfile = null, $logLevel = null)
    {
        $this->logfile = $logfile ?: ($GLOBALS['logfile'] ?? '');
        $this->logLevel = $logLevel ?? ($GLOBALS['logLevelIni'] ?? 3);
    }

    public function logMessage($errorlevel, $text, $logfile = '')
    {
        $effectiveLogfile = $logfile ?: $this->logfile;
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller_info = $this->findCallerInfo($backtrace);

        $this->textstring = "$errorlevel {$caller_info}: $text";
        $this->writelog($this->textstring, $effectiveLogfile);
    }

    public function isBulkDiagnosticEnabled()
    {
        return (int) $this->logLevel === 4;
    }

    public function logBulkDiagnostic($text, $logfile = '')
    {
        if (!$this->isBulkDiagnosticEnabled()) :
            return;
        endif;

        $effectiveLogfile = $logfile ?: $this->logfile;
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller_info = $this->findCallerInfo($backtrace);

        $this->textstring = "[NOTICE] Bulk diagnostic {$caller_info}: $text";
        $this->writelog($this->textstring, $effectiveLogfile);
    }

    private function writelog($msg, $log = '')
    {
        $log = $log ?: $this->logfile;

        // Short-circuit if log path is empty or explicitly disabled
        if (empty($log) || $log === 0) :
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
        if ($loglevel === 4) :
            $loglevel = 2;
        endif;

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

    private function findCallerInfo($backtrace)
    {
        $caller = $backtrace[0] ?? null;

        if ($caller) :
            $file = isset($caller['file']) ? basename($caller['file']) : 'Unknown file';
            $line = isset($caller['line']) ? $caller['line'] : 'Unknown line';

            $functionName = '';
            if (isset($backtrace[1]['function']) && $backtrace[1]['function'] !== 'logMessage') :
                $functionName = ": Function " . $backtrace[1]['function'];
            endif;

            return "$file $line$functionName";
        endif;

        return 'Unknown file Unknown line';
    }
}
