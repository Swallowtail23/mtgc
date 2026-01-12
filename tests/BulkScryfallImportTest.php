<?php

use PHPUnit\Framework\TestCase;

class BulkScryfallImportTest extends TestCase
{
    public function testScryfallBulkScriptTestModeRuns()
    {
        $githubActions = getenv('GITHUB_ACTIONS');
        if ($githubActions === 'true' || $githubActions === '1') :
            $this->markTestSkipped('Skipping bulk import test in GitHub Actions.');
        endif;

        $ci = getenv('CI');
        if ($ci === 'true' || $ci === '1') :
            $this->markTestSkipped('Skipping bulk import test in CI.');
        endif;

        $bulkIniPath = getenv('MTG_BULK_TEST_INI');
        if ($bulkIniPath === false || $bulkIniPath === '') :
            $bulkIniPath = '/opt/mtg/mtg_new.ini';
        endif;
        if (!is_file($bulkIniPath)) :
            $this->markTestSkipped("Bulk test ini not found: {$bulkIniPath}");
        endif;

        $iniData = parse_ini_file($bulkIniPath, true);
        if (!is_array($iniData)) :
            $this->markTestSkipped("Bulk test ini unreadable: {$bulkIniPath}");
        endif;
        $tier = strtolower((string) ($iniData['general']['tier'] ?? ''));
        if ($tier !== 'dev') :
            $this->markTestSkipped("Bulk test ini tier not dev: {$bulkIniPath}");
        endif;

        $previousIniPath = getenv('MTG_INI_PATH');
        putenv("MTG_INI_PATH={$bulkIniPath}");

        $script = __DIR__ . '/../bulk/scryfall_bulk.php';
        $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script) . ' test';
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($previousIniPath !== false && $previousIniPath !== '') :
            putenv("MTG_INI_PATH={$previousIniPath}");
        else :
            putenv('MTG_INI_PATH');
        endif;

        $outputText = implode("\n", $output);
        $this->assertSame(0, $exitCode, $outputText);
        $this->assertStringContainsString(
            'Test summary: total 10, added 1, price only 2, content only 2, both 2',
            $outputText
        );
    }
}
