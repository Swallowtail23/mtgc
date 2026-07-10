<?php

/*
Version:     1.0
Date:        10/07/26
Name:        ScryfallBulkCommand.php
Purpose:     Coordinate Scryfall card, tag, and sync-state bulk commands.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\AppConfig;
use MTG\Core\Filesystem;
use MTG\Core\GameRules;
use MTG\Core\Message;
use MTG\Core\MyPHPMailer;

class ScryfallBulkCommand
{
    /** @param \mysqli|object $db */
    public function __construct(
        private mixed $db,
        private AppConfig $appConfig,
        private GameRules $gameRules,
        private Message $msg
    ) {
    }

    /** @param array<int, string> $arguments */
    public function run(array $arguments): int
    {
        $argument = strtolower(trim($arguments[1] ?? ''));

        Filesystem::ensureDirectoryExists(
            (string) $this->appConfig->general('imageBaseDir', '') . 'json',
            $this->appConfig,
            $this->msg
        );

        if ($argument === 'sync-state') :
            return $this->runSyncState();
        endif;
        if (in_array($argument, ['tags', 'oracle-tags', 'art-tags'], true)) :
            return $this->runTags($argument);
        endif;

        $type = $argument === 'all' ? 'all' : ($argument === 'refresh' ? 'refresh' : 'default');
        $useTestTable = $argument === 'test';
        if ($useTestTable) :
            $type = 'default';
            $this->msg->logMessage('[NOTICE]', 'Scryfall Bulk API: test mode enabled; using cards_scry_test');
            return $this->runTest();
        endif;

        return $this->runCardImport($type, 'cards_scry');
    }

    private function runSyncState(): int
    {
        $affected = ScryfallImport::backfillDataSyncState($this->db, $this->msg);
        $this->write("Scryfall sync state: data backfill completed; affected rows: $affected");
        return 0;
    }

    private function runTags(string $argument): int
    {
        $mode = $argument === 'oracle-tags' ? 'oracle' : ($argument === 'art-tags' ? 'art' : 'all');
        $result = ScryfallImport::importTags($mode, $this->db, $this->appConfig, $this->gameRules, $this->msg);
        $body = implode("\n", $result['summary']);
        $subject = "MTG Scryfall tag update completed ($mode)";
        $this->sendEmail($subject, $body, "scryfall_bulk tag alert not sent for $mode");
        $this->write("Scryfall Tags API: {$result['tags']} tags and {$result['assignments']} assignments completed");
        foreach ($result['summary'] as $line) :
            $this->write($line);
        endforeach;
        return 0;
    }

    private function runTest(): int
    {
        $targetTable = 'cards_scry_test';
        $firstFile = APP_ROOT . '/tests/test_data/bulk_sample_10.json';
        $secondFile = APP_ROOT . '/tests/test_data/bulk_sample_10_copy.json';
        if (!$this->prepareTestTable() || !is_file($firstFile) || !is_file($secondFile)) :
            if (!is_file($firstFile) || !is_file($secondFile)) :
                $this->error("Scryfall Bulk API: Test files missing: $firstFile or $secondFile");
            endif;
            return 1;
        endif;

        $this->msg->logMessage('[NOTICE]', 'Scryfall Bulk API: test run 1 (baseline) starting');
        $firstResult = ScryfallImport::scryfallImport(
            $firstFile,
            'default',
            $targetTable,
            $this->db,
            $this->appConfig,
            $this->gameRules,
            $firstStats
        );
        if ($firstResult === false) :
            $this->error("Scryfall Bulk API: Test run 1 failed for $firstFile");
            return 1;
        endif;

        $this->msg->logMessage('[NOTICE]', 'Scryfall Bulk API: test run 2 (mutated) starting');
        $secondResult = ScryfallImport::scryfallImport(
            $secondFile,
            'default',
            $targetTable,
            $this->db,
            $this->appConfig,
            $this->gameRules,
            $secondStats
        );
        if ($secondResult === false) :
            $this->error("Scryfall Bulk API: Test run 2 failed for $secondFile");
            return 1;
        endif;

        $report = sprintf(
            'Test summary: total %d, added %d, price only %d, content only %d, both %d',
            $secondStats['total'] ?? 0,
            $secondStats['added'] ?? 0,
            $secondStats['price_only'] ?? 0,
            $secondStats['content_only'] ?? 0,
            $secondStats['both'] ?? 0
        );
        $this->msg->logMessage('[NOTICE]', $report);
        $this->write($report);
        return 0;
    }

    private function prepareTestTable(): bool
    {
        $this->msg->logMessage('[DEBUG]', 'Preparing cards_scry_test for bulk import test');
        $check = $this->db->query("SHOW TABLES LIKE 'cards_scry_test'");
        if ($check === false) :
            $this->error("Scryfall Bulk API: Failed to check cards_scry_test existence: {$this->db->error}");
            return false;
        endif;
        if ($check->num_rows > 0) :
            $this->msg->logMessage('[NOTICE]', 'cards_scry_test exists; dropping to refresh schema from cards_scry');
            if ($this->db->query('DROP TABLE `cards_scry_test`') === false) :
                $this->error("Scryfall Bulk API: Failed to drop cards_scry_test: {$this->db->error}");
                return false;
            endif;
        endif;
        $check->free();
        $this->msg->logMessage('[NOTICE]', 'Creating cards_scry_test from cards_scry structure');
        if ($this->db->query('CREATE TABLE `cards_scry_test` LIKE `cards_scry`') === false) :
            $this->error("Scryfall Bulk API: Failed to create cards_scry_test: {$this->db->error}");
            return false;
        endif;
        return true;
    }

    private function runCardImport(string $type, string $targetTable): int
    {
        $start = microtime(true);
        $bulkInfo = ScryfallImport::getBulkInfo($type, $this->appConfig, $this->gameRules);
        $required = $type === 'refresh'
            ? ['bulkUrlAll', 'bulkUrlDefault', 'fileLocationAll', 'fileLocationDefault']
            : ['bulkUrl', 'fileLocation'];
        if (!is_array($bulkInfo)) :
            $this->error('Scryfall Bulk API: Download URI: bulk_info function failed to return usable results');
            return 1;
        endif;
        foreach ($required as $key) :
            if (!isset($bulkInfo[$key]) || $bulkInfo[$key] === '') :
                $this->error("Scryfall Bulk API: bulkInfo missing key '$key'");
                return 1;
            endif;
        endforeach;

        if ($type === 'refresh') :
            return $this->runRefresh($bulkInfo, $targetTable, $start);
        endif;
        return $this->runSingleImport($bulkInfo['bulkUrl'], $bulkInfo['fileLocation'], $type, $targetTable, $start);
    }

    /** @param array<string, mixed> $bulkInfo */
    private function runRefresh(array $bulkInfo, string $targetTable, float $start): int
    {
        $this->requireSourceTracking();
        $tracker = new ScryfallBulkSourceTracker($this->db);
        $this->msg->logMessage(
            '[NOTICE]',
            "Scryfall Bulk API: Download URIs: {$bulkInfo['bulkUrlAll']} / {$bulkInfo['bulkUrlDefault']}; File locations: "
            . "{$bulkInfo['fileLocationAll']} / {$bulkInfo['fileLocationDefault']}"
        );
        if (ScryfallImport::getBulkDataFile($bulkInfo['bulkUrlAll'], $bulkInfo['fileLocationAll'], 0, $this->appConfig) === false) :
            $this->error("Scryfall Bulk API: getBulkDataFile (all) returned error for {$bulkInfo['bulkUrlAll']}");
            return 1;
        endif;
        if (ScryfallImport::getBulkDataFile($bulkInfo['bulkUrlDefault'], $bulkInfo['fileLocationDefault'], 0, $this->appConfig) === false) :
            $this->error("Scryfall Bulk API: getBulkDataFile (default) returned error for {$bulkInfo['bulkUrlDefault']}");
            return 1;
        endif;
        $this->logElapsed('Time after bulk files obtained', $start);
        foreach ([['all', 'fileLocationAll'], ['default', 'fileLocationDefault']] as [$importType, $fileKey]) :
            $sourceType = $importType . '_cards';
            if ($tracker->isCurrent($sourceType, $bulkInfo['bulkUrl' . ucfirst($importType)], $bulkInfo[$fileKey])) :
                $this->write("Scryfall Bulk API: $importType cards source unchanged; import skipped");
                continue;
            endif;
            $tracker->markStarted($sourceType, $bulkInfo['bulkUrl' . ucfirst($importType)], $bulkInfo[$fileKey]);
            $result = ScryfallImport::scryfallImport(
                $bulkInfo[$fileKey],
                $importType,
                $targetTable,
                $this->db,
                $this->appConfig,
                $this->gameRules
            );
            $this->logElapsed("Time after \"$importType\" import completed", $start);
            if ($result === false) :
                $tracker->markFailed($sourceType, $bulkInfo['bulkUrl' . ucfirst($importType)], $bulkInfo[$fileKey]);
                $this->error("Scryfall Bulk API: scryfallImport from {$bulkInfo[$fileKey]} failed for type '$importType'");
                return 1;
            endif;
            $tracker->markCompleted($sourceType, $bulkInfo['bulkUrl' . ucfirst($importType)], $bulkInfo[$fileKey]);
            $this->write("Scryfall Bulk API: MTG bulk update completed ($importType), $result");
        endforeach;
        return 0;
    }

    private function runSingleImport(string $url, string $file, string $type, string $targetTable, float $start): int
    {
        $this->requireSourceTracking();
        $tracker = new ScryfallBulkSourceTracker($this->db);
        $this->msg->logMessage('[NOTICE]', "Scryfall Bulk API: Download URI: $url; File location: $file");
        if (ScryfallImport::getBulkDataFile($url, $file, 23 * 3600, $this->appConfig) === false) :
            $this->error("Scryfall Bulk API: Download URI: getBulkDataFile returned error for $url");
            return 1;
        endif;
        $this->logElapsed('Time after bulk files obtained', $start);
        $sourceType = $type . '_cards';
        if ($tracker->isCurrent($sourceType, $url, $file)) :
            $subject = "MTG bulk update skipped ($type)";
            $body = 'Source unchanged; import skipped.';
            $this->sendEmail($subject, $body, "scryfall_bulk alert not sent for $type");
            $this->write("Scryfall Bulk API: $subject, $body");
            return 0;
        endif;
        $tracker->markStarted($sourceType, $url, $file);
        $result = ScryfallImport::scryfallImport(
            $file,
            $type,
            $targetTable,
            $this->db,
            $this->appConfig,
            $this->gameRules
        );
        if ($result === false) :
            $tracker->markFailed($sourceType, $url, $file);
            $this->error("Scryfall Bulk API: scryfallImport from $file failed for type '$type'");
            return 1;
        endif;
        $tracker->markCompleted($sourceType, $url, $file);
        $this->logElapsed('Time after import completed', $start);
        $subject = "MTG bulk update completed ($type)";
        $this->sendEmail($subject, $result, "scryfall_bulk alert not sent for $type");
        $this->write("Scryfall Bulk API: $subject, $result");
        return 0;
    }

    private function requireSourceTracking(): void
    {
        $schema = new ScryfallSchemaGuard($this->db, $this->msg, 'scryfall_bulk.php');
        $schema->requireTable('scryfall_bulk_sources');
    }

    private function sendEmail(string $subject, string $body, string $disabledMessage): void
    {
        if ((bool) $this->appConfig->email('enabled', false)) :
            $mail = new MyPHPMailer(true, $this->appConfig);
            $result = $mail->sendEmail((string) $this->appConfig->email('adminEmail', ''), false, $subject, $body);
            $this->msg->logMessage('[DEBUG]', "Mail result is '$result'");
            return;
        endif;
        $this->msg->logMessage('[NOTICE]', "Email disabled; $disabledMessage");
    }

    private function logElapsed(string $label, float $start): void
    {
        $this->msg->logMessage('[NOTICE]', sprintf('%s: %.2f seconds', $label, microtime(true) - $start));
    }

    private function error(string $text): void
    {
        $this->msg->logMessage('[ERROR]', $text);
        if (PHP_SAPI === 'cli') :
            fwrite(STDERR, $text . PHP_EOL);
        endif;
    }

    private function write(string $text): void
    {
        if (PHP_SAPI === 'cli') :
            echo $text . PHP_EOL;
        endif;
    }
}
