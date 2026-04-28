<?php

/*
Version:     1.0
Date:        28/04/26
Name:        ErrorHandlerTest.php
Purpose:     Tests error handler output and logging in isolated fixture processes.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    private function runFixture(string $script, array $env): array
    {
        $pipes = [];
        $process = proc_open(
            ['php', $script],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            array_merge($_ENV, $env)
        );

        if ($process === false) :
            return ['', 'Failed to start fixture process.', 1];
        endif;

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) :
            fclose($pipe);
        endforeach;
        $code = proc_close($process);

        return [$stdout, $stderr, $code];
    }

    public function testHandleErrorWritesLogAndOutputsRedirect()
    {
        $logPath = tempnam(sys_get_temp_dir(), 'mtg_err_');
        $script = __DIR__ . '/fixtures/error_handler_error.php';

        [$stdout, $stderr, $code] = $this->runFixture($script, [
            'ERROR_LOG_PATH' => $logPath
        ]);

        $this->assertSame(0, $code, $stderr);
        $this->assertStringContainsString("http-equiv='refresh'", $stdout);
        $log = file_get_contents($logPath);
        $this->assertStringContainsString('E_USER_NOTICE', $log);
        unlink($logPath);
    }

    public function testHandleExceptionWritesLogAndOutputsRedirect()
    {
        $logPath = tempnam(sys_get_temp_dir(), 'mtg_exc_');
        $script = __DIR__ . '/fixtures/error_handler_exception.php';

        [$stdout, $stderr, $code] = $this->runFixture($script, [
            'ERROR_LOG_PATH' => $logPath
        ]);

        $this->assertSame(0, $code, $stderr);
        $this->assertStringContainsString("http-equiv='refresh'", $stdout);
        $log = file_get_contents($logPath);
        $this->assertStringContainsString('Fatal exception', $log);
        unlink($logPath);
    }
}
