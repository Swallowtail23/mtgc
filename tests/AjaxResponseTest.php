<?php

use PHPUnit\Framework\TestCase;

class AjaxResponseTest extends TestCase
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

    public function testJsonResponseOutputsPayloadAndHeaders()
    {
        $metaPath = tempnam(sys_get_temp_dir(), 'ajax_meta_');
        $script = __DIR__ . '/fixtures/ajax_response_json.php';

        [$stdout, $stderr, $code] = $this->runFixture($script, [
            'AJAX_META_PATH' => $metaPath
        ]);

        $this->assertSame(0, $code, $stderr);
        $this->assertSame('{"ok":true}', $stdout);
        $meta = json_decode((string) file_get_contents($metaPath), true);
        $this->assertSame(201, $meta['status']);
        if (!empty($meta['headers'])) :
            $this->assertContains('Content-Type: application/json', $meta['headers']);
        endif;
        unlink($metaPath);
    }

    public function testTextResponseOutputsPayloadAndStatus()
    {
        $metaPath = tempnam(sys_get_temp_dir(), 'ajax_meta_');
        $script = __DIR__ . '/fixtures/ajax_response_text.php';

        [$stdout, $stderr, $code] = $this->runFixture($script, [
            'AJAX_META_PATH' => $metaPath
        ]);

        $this->assertSame(0, $code, $stderr);
        $this->assertSame('ok', $stdout);
        $meta = json_decode((string) file_get_contents($metaPath), true);
        $this->assertSame(202, $meta['status']);
        if (!empty($meta['headers'])) :
            $this->assertNotContains('Content-Type: application/json', $meta['headers']);
        endif;
        unlink($metaPath);
    }
}
