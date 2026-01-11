<?php

use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    private function runFixture(string $script, array $env): array
    {
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

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
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
