<?php

/*
Version:     1.3
Date:        10/07/26
Name:        ScryfallCardImportRunner.php
Purpose:     Runs Scryfall card import batching and persistence orchestration.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use Throwable;
use MTG\Cards\ImageManager;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;

class ScryfallCardImportRunner
{
    public static function run(
        string $file_location,
        string $type,
        string $tableName,
        mixed $db,
        AppConfig $appConfig,
        GameRules $gameRules,
        ?array &$stats = null,
        bool $collectStats = true
    ): string|false {
        $msg = new Message($appConfig);
        $importPolicy = new ScryfallCardImportPolicy($gameRules);

        $allowedTables = ['cards_scry', 'cards_scry_test'];
        if (!in_array($tableName, $allowedTables, true)) :
            $msg->logMessage('[ERROR]', "Invalid table name '$tableName' for scryfallImport");
            throw new \Exception('[ERROR] scryfall_bulk.php: Invalid cards table name supplied');
        endif;
        $msg->logMessage('[DEBUG]', "Using cards table '$tableName' for scryfall import");
        $schema = new ScryfallSchemaGuard($db, $msg, 'scryfall_bulk.php');
        $schema->requireColumns(
            $tableName,
            ['content_hash', 'price_hash', 'illustration_id', 'f1_illustration_id', 'f2_illustration_id']
        );

        $count_inc = $count_skip = $total_count = $count_add = $count_update = $count_other = 0;
        $count_update_content = $count_update_price = $count_update_both = 0;
        $data = ScryfallBulkFiles::iterateBulkRecords($file_location);

        $date = date('Y-m-d');
        $timeslice_start = microtime(true);
        $batch_size = 5000;
        $log_interval = 2500;

        if ($type === 'default') :
            $primary = 1;

            // By default, set to TRUE. This will download all images for cards in the Default Cards file when run
            // with an empty database (about 90,000 images, i.e. potentially about 20GB)
            $imageDownloads = true;
        elseif ($type === 'all') :
            $primary = 0;

            // Don't by default download all images for all cards.
            // Images will be obtained on first card detail load or search result inclusion
            $imageDownloads = false;
        else :
            $msg->logMessage('[ERROR]', "Invalid import type '$type' for scryfallImport");
            throw new \Exception('[ERROR] scryfall_bulk.php: Invalid import type supplied');
        endif;

        $imageManager = null;
        if ($tableName === 'cards_scry_test') :
            $imageDownloads = false;
        endif;

        if ($imageDownloads === true) :
            $imageManager = new ImageManager($db, $appConfig, $gameRules);
        endif;

        $syncStateUpdater = ScryfallSyncStateUpdater::prepareForCardsTable($tableName, $db);

        $insertSql = ScryfallCardImportStatement::insertSql($tableName);
        $stmt = $db->prepare($insertSql);
        if ($stmt === false) :
            throw new \Exception('[ERROR] cards.php: Preparing SQL: ' . $db->error);
        endif;
        $hashSql = ScryfallCardImportStatement::hashLookupSql($tableName);
        $hashStmt = $db->prepare($hashSql);
        if ($hashStmt === false) :
            throw new \Exception('[ERROR] scryfall_bulk.php: Preparing hash lookup SQL: ' . $db->error);
        endif;
        $bindValues = ScryfallCardImportStatement::initialBindValues($date, (int) $primary);
        $orderedBindValues = ScryfallCardImportStatement::orderedBindValues($bindValues);
        $bind = $stmt->bind_param(
            ScryfallCardImportStatement::bindTypes(),
            ...$orderedBindValues
        );

        if ($bind === false) :
            mtgError(
                E_USER_ERROR,
                '[ERROR] scryfall_bulk.php: Binding parameters: ' . $db->error,
                __FILE__,
                __LINE__,
                $appConfig
            );
        endif;
        $hash_id = null;
        $hashBind = $hashStmt->bind_param("s", $hash_id);
        if ($hashBind === false) :
            mtgError(
                E_USER_ERROR,
                '[ERROR] scryfall_bulk.php: Binding hash id: ' . $db->error,
                __FILE__,
                __LINE__,
                $appConfig
            );
        endif;
        $lastGoodId = null;
        $lastGoodCount = 0;

        $msg->logMessage('[DEBUG]', 'Starting bulk import transaction batch');
        $batchStart = $db->begin_transaction();
        if ($batchStart === false) :
            mtgError(
                E_USER_ERROR,
                '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                __FILE__,
                __LINE__,
                $appConfig
            );
        endif;

        try {
            foreach ($data as $key => $value) :
                $total_count = $total_count + 1;
                $commit_due = ($total_count % $batch_size === 0);
                $log_due = ($total_count % $log_interval === 0);

                $id = $value["id"] ?? null;
                if ($id === null) :
                    $count_skip = $count_skip + 1;
                    $msg->logMessage('[WARNING]', "Skipping record {$total_count}: missing id");
                    if ($commit_due) :
                        $commitResult = $db->commit();
                        if ($commitResult === false) :
                            mtgError(
                                E_USER_ERROR,
                                '[ERROR] scryfall_bulk.php: Committing transaction batch: ' . $db->error,
                                __FILE__,
                                __LINE__,
                                $appConfig
                            );
                        endif;
                        $msg->logMessage('[DEBUG]', "Committed transaction batch at record $total_count");
                        $batchStart = $db->begin_transaction();
                        if ($batchStart === false) :
                            mtgError(
                                E_USER_ERROR,
                                '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                                __FILE__,
                                __LINE__,
                                $appConfig
                            );
                        endif;
                    endif;
                    if ($log_due) :
                        $timeslice = microtime(true) - $timeslice_start;
                        $commit_note = $commit_due ? '; batch committed' : '';
                        $msg->logMessage(
                            '[NOTICE]',
                            "Scryfall bulk API ($type) progress: $total_count records processed; timeslice: "
                            . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                        );
                        $timeslice_start = microtime(true);
                    endif;
                    continue;
                endif;

                $msg->logMessage('[DEBUG]', "Scryfall bulk API ($type), Record $id: $total_count");

                $mappedCard = ScryfallCardRecordMapper::map($value);
                ScryfallCardImportStatement::applyMappedCard($bindValues, $mappedCard);
                $lang = $mappedCard['lang'] ?? null;
                $layout = $mappedCard['layout'] ?? null;
                $content_hash = $mappedCard['content_hash'] ?? null;
                $price_hash = $mappedCard['price_hash'] ?? null;
                $set_code = $mappedCard['set_code'] ?? null;

                $decision = $importPolicy->decide($value, $type);
                $skip = $decision['include'] ? 0 : 1;

                if ($skip === 1) :
                    $count_skip = $count_skip + 1;
                elseif ($skip === 0) :
                    $count_inc = $count_inc + 1;

                    $content_changed = false;
                    $price_changed = false;
                    $existing_content_hash = null;
                    $existing_price_hash = null;

                    $hash_id = $id;
                    $hashExec = $hashStmt->execute();
                    if ($hashExec === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Checking existing hashes: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    $hashStore = $hashStmt->store_result();
                    if ($hashStore === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Storing hash results: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    $hashBindResult = $hashStmt->bind_result($existing_content_hash, $existing_price_hash);
                    if ($hashBindResult === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Binding hash results: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    if ($hashStmt->num_rows > 0) :
                        $hashStmt->fetch();
                        $content_changed = ($existing_content_hash !== $content_hash);
                        $price_changed = ($existing_price_hash !== $price_hash);
                    endif;
                    $hashStmt->free_result();

                    $exec = $stmt->execute();

                    if ($exec === false) :
                        mtgError(
                            E_USER_ERROR,
                            "[ERROR] scryfall_bulk.php: Writing new card details: " . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    else :
                        $lastGoodId = $id;
                        $lastGoodCount = $total_count;
                        $status = $stmt->affected_rows;

                        if ($status === 1) :
                            $count_add = $count_add + 1;
                            $msg->logMessage('[DEBUG]', "Added card - no error returned; return code: $status");

                            if ($syncStateUpdater !== null) :
                                $syncStateUpdater->update($id, 'added card');
                            endif;

                            if ($imageDownloads === true) :
                                $imageManager->getImage(
                                    $set_code,
                                    $id,
                                    $layout
                                );
                            endif;
                        elseif ($status === 2) :
                            $count_update = $count_update + 1;
                            if ($content_changed === true and $price_changed === true) :
                                $count_update_both = $count_update_both + 1;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Updated card - content and price hash change; return code: $status"
                                );
                                if ($syncStateUpdater !== null) :
                                    $syncStateUpdater->update($id, 'content update');
                                endif;
                            elseif ($content_changed === true) :
                                $count_update_content = $count_update_content + 1;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Updated card - content hash change; return code: $status"
                                );
                                if ($syncStateUpdater !== null) :
                                    $syncStateUpdater->update($id, 'content update');
                                endif;
                            elseif ($price_changed === true) :
                                $count_update_price = $count_update_price + 1;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Updated card - price hash change; return code: $status"
                                );
                            else :
                                $msg->logMessage(
                                    '[WARNING]',
                                    "Updated card - hash change not detected; return code: $status"
                                );
                            endif;
                        else :
                            $count_other = $count_other + 1;
                            $msg->logMessage('[DEBUG]', "No change - no error returned; return code: $status");
                        endif;
                    endif;
                endif;
                if ($commit_due) :
                    $commitResult = $db->commit();
                    if ($commitResult === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Committing transaction batch: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                    $msg->logMessage('[DEBUG]', "Committed transaction batch at record $total_count");
                    $batchStart = $db->begin_transaction();
                    if ($batchStart === false) :
                        mtgError(
                            E_USER_ERROR,
                            '[ERROR] scryfall_bulk.php: Starting transaction batch: ' . $db->error,
                            __FILE__,
                            __LINE__,
                            $appConfig
                        );
                    endif;
                endif;
                if ($log_due) :
                    $timeslice = microtime(true) - $timeslice_start;
                    $commit_note = $commit_due ? '; batch committed' : '';
                    $msg->logMessage(
                        '[NOTICE]',
                        "Scryfall bulk API ($type) progress: $total_count records processed; timeslice: "
                        . sprintf('%.2f', $timeslice) . "s{$commit_note}"
                    );
                    $timeslice_start = microtime(true);
                endif;
            endforeach;
        } catch (Throwable $e) {
            $msg->logMessage(
                '[ERROR]',
                "Bulk import aborted (likely truncated JSON). Last good: {$lastGoodId} at {$lastGoodCount}. "
                . "File: {$file_location}. Error: " . $e->getMessage()
            );
            $db->rollback();
            if ($syncStateUpdater !== null) :
                $syncStateUpdater->close();
            endif;
            $stmt->close();
            $hashStmt->close();

            $badPath = $file_location . '.bad-' . date('Ymd-His');
            $renamed = @rename($file_location, $badPath);
            $msg->logMessage(
                $renamed ? '[NOTICE]' : '[WARNING]',
                $renamed
                    ? "Quarantined bad JSON to {$badPath}"
                    : "Failed to quarantine JSON from {$file_location} to {$badPath}"
            );

            return "FAILED: aborted at {$lastGoodCount} (id {$lastGoodId}). Quarantined to {$badPath}";
        }
        $commitResult = $db->commit();
        if ($commitResult === false) :
            throw new \Exception('[ERROR] scryfall_bulk.php: Final commit failed: ' . $db->error);
        endif;
        $stmt->close();
        $hashStmt->close();
        if ($syncStateUpdater !== null) :
            $syncStateUpdater->close();
        endif;

        $msg->logMessage(
            '[NOTICE]',
            "Bulk update completed: Total $total_count, added: $count_add, skipped $count_skip, "
            . "included $count_inc, updated: $count_update (content: $count_update_content, "
            . "price: $count_update_price, both: $count_update_both), unchanged: $count_other"
        );
        if ($collectStats === true) :
            $stats = [
                'total' => $total_count,
                'included' => $count_inc,
                'skipped' => $count_skip,
                'added' => $count_add,
                'updated' => $count_update,
                'content_only' => $count_update_content,
                'price_only' => $count_update_price,
                'both' => $count_update_both,
                'other' => $count_other
            ];
        endif;
        $message = "Total: $total_count; total added: $count_add; total skipped: $count_skip; "
            . "total included: $count_inc; total updated: $count_update (content: $count_update_content; "
            . "price: $count_update_price; both: $count_update_both)";
        return $message;
    }
}
