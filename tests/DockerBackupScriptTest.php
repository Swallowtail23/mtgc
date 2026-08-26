<?php

/*
Version:     1.0
Date:        26/08/26
Name:        DockerBackupScriptTest.php
Purpose:     Tests automatic container-engine selection in the backup helper.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class DockerBackupScriptTest extends TestCase
{
    public function testDetectsTheSoleEngineRunningTheDatabaseContainer(): void
    {
        foreach (['docker', 'podman'] as $runningEngine) :
            $result = $this->runBackup(
                $runningEngine === 'docker',
                $runningEngine === 'podman'
            );

            $this->assertSame(0, $result['status'], $result['output']);
            $this->assertStringContainsString("[INFO] Using container engine: $runningEngine", $result['output']);
            $this->assertTrue($result['backupCreated']);
        endforeach;
    }

    public function testRejectsAnAmbiguousAutomaticSelection(): void
    {
        $result = $this->runBackup(true, true);

        $this->assertNotSame(0, $result['status']);
        $this->assertStringContainsString('is running under both Docker and Podman', $result['output']);
        $this->assertFalse($result['backupCreated']);
    }

    public function testExplicitEngineOverrideResolvesAmbiguity(): void
    {
        $result = $this->runBackup(true, true, 'podman');

        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('[INFO] Using container engine: podman', $result['output']);
        $this->assertTrue($result['backupCreated']);
    }

    /**
     * @return array{status: int, output: string, backupCreated: bool}
     */
    private function runBackup(bool $dockerRunning, bool $podmanRunning, ?string $override = null): array
    {
        $tempRoot = sys_get_temp_dir() . '/mtg_backup_test_' . bin2hex(random_bytes(6));
        $binDir = $tempRoot . '/bin';
        $baseDir = $tempRoot . '/data/mtgc';
        $backupRoot = $tempRoot . '/backups';
        $secretsFile = $baseDir . '/secrets/mysql.env';
        $envFile = $tempRoot . '/docker.env';

        mkdir($binDir, 0700, true);
        mkdir($baseDir . '/config', 0700, true);
        mkdir($baseDir . '/secrets', 0700, true);
        mkdir($baseDir . '/logs', 0700, true);
        file_put_contents($secretsFile, "MYSQL_ROOT_PASSWORD=test-password\n");
        file_put_contents(
            $envFile,
            "BASE_DIR=$baseDir\nMYSQL_SECRETS_FILE=$secretsFile\n"
        );
        $this->writeEngineStub($binDir . '/docker', $dockerRunning);
        $this->writeEngineStub($binDir . '/podman', $podmanRunning);

        $environment = getenv();
        $environment['PATH'] = $binDir . PATH_SEPARATOR . ($environment['PATH'] ?? '');
        $environment['ENV_FILE'] = $envFile;
        $environment['BACKUP_ROOT'] = $backupRoot;
        if ($override === null) :
            unset($environment['CONTAINER_ENGINE']);
        else :
            $environment['CONTAINER_ENGINE'] = $override;
        endif;

        $pipes = [];
        $process = proc_open(
            ['bash', APP_ROOT . '/docker/backup.sh'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            APP_ROOT,
            $environment
        );
        if (!is_resource($process)) :
            $this->removeDirectory($tempRoot);
            $this->fail('Unable to start docker/backup.sh');
        endif;

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        $backupCreated = glob($backupRoot . '/*/mtgc.sql.gz') !== []
            && glob($backupRoot . '/*/mtgc_files.tar.gz') !== [];
        $this->removeDirectory($tempRoot);

        return [
            'status' => $status,
            'output' => $stdout . $stderr,
            'backupCreated' => $backupCreated
        ];
    }

    private function writeEngineStub(string $path, bool $running): void
    {
        $runningValue = $running ? 'true' : 'false';
        $script = <<<BASH
#!/bin/bash
if [[ "\${1:-}" == "inspect" ]]; then
    if [[ "$runningValue" == "true" ]]; then
        echo "true"
        exit 0
    fi
    exit 1
fi
if [[ "\${1:-}" == "exec" ]]; then
    echo "-- mock database dump"
    exit 0
fi
exit 1
BASH;
        file_put_contents($path, $script);
        chmod($path, 0700);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) :
            return;
        endif;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $path) :
            if ($path->isDir()) :
                rmdir($path->getPathname());
            else :
                unlink($path->getPathname());
            endif;
        endforeach;
        rmdir($directory);
    }
}
