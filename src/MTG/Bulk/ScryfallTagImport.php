<?php

/*
Version:     1.1
Date:        08/07/26
Name:        ScryfallTagImport.php
Purpose:     Import Scryfall Oracle and art tag bulk data.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use Throwable;

class ScryfallTagImport
{
    /**
    * @param \mysqli|object $db
    * @return array<string, mixed>
    */
    public static function import(
        string $mode,
        $db,
        AppConfig $appConfig,
        GameRules $gameRules,
        Message $msg
    ): array {
        $mode = strtolower(trim($mode));
        if ($mode === '') :
            $mode = 'all';
        endif;

        $tagImports = match ($mode) {
            'oracle' => ['oracle'],
            'art' => ['art'],
            'all' => ['oracle', 'art'],
            default => throw new \InvalidArgumentException("Invalid Scryfall tag import mode '$mode'")
        };

        foreach (['scryfall_tag_definitions', 'scryfall_tag_assignments'] as $requiredTable) :
            $tableCheck = $db->execute_query(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$requiredTable]
            );
            if ($tableCheck === false) :
                throw new \Exception("[ERROR] scryfall_tag_definitions: Checking $requiredTable table: " . $db->error);
            endif;
            if ($tableCheck->num_rows === 0) :
                throw new \Exception(
                    "[ERROR] scryfall_tag_definitions: $requiredTable table missing; apply schema updates first"
                );
            endif;
            $tableCheck->free();
        endforeach;

        $imgLocation = (string) $appConfig->general('imageBaseDir', '');
        $tagConfig = [
            'oracle' => [
                'rule' => 'oracleTagsUrl',
                'expectedType' => 'oracle_tags',
                'file' => $imgLocation . 'json/oracle_tags.jsonl.gz',
                'payloadType' => 'oracle',
                'subjectField' => 'oracle_id',
                'label' => 'Oracle Tags',
            ],
            'art' => [
                'rule' => 'artTagsUrl',
                'expectedType' => 'art_tags',
                'file' => $imgLocation . 'json/art_tags.jsonl.gz',
                'payloadType' => 'illustration',
                'subjectField' => 'illustration_id',
                'label' => 'Art Tags',
            ],
        ];

        $tagStmt = $db->prepare("INSERT INTO
                                `scryfall_tag_definitions`
                                    (id, tag_type, label, slug, uri, description, parent_ids, child_ids, aliases,
                                    content_hash)
                                VALUES
                                    (?,?,?,?,?,?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE
                                    label = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(label), label),
                                    slug = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(slug), slug),
                                    uri = IF(NOT (content_hash <=> VALUES(content_hash)), VALUES(uri), uri),
                                    description = IF(
                                        NOT (content_hash <=> VALUES(content_hash)),
                                        VALUES(description),
                                        description
                                    ),
                                    parent_ids = IF(
                                        NOT (content_hash <=> VALUES(content_hash)),
                                        VALUES(parent_ids),
                                        parent_ids
                                    ),
                                    child_ids = IF(
                                        NOT (content_hash <=> VALUES(content_hash)),
                                        VALUES(child_ids),
                                        child_ids
                                    ),
                                    aliases = IF(
                                        NOT (content_hash <=> VALUES(content_hash)),
                                        VALUES(aliases),
                                        aliases
                                    ),
                                    content_hash = IF(
                                        NOT (content_hash <=> VALUES(content_hash)),
                                        VALUES(content_hash),
                                        content_hash
                                    )");
        if ($tagStmt === false) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Preparing tag SQL: ' . $db->error);
        endif;

        $taggingStmt = $db->prepare("INSERT INTO
                                    `scryfall_tag_assignments`
                                        (tag_id, tag_type, subject_id, weight)
                                    VALUES
                                        (?,?,?,?)
                                    ON DUPLICATE KEY UPDATE
                                        weight = VALUES(weight)");
        if ($taggingStmt === false) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Preparing tagging SQL: ' . $db->error);
        endif;

        $tagId = null;
        $tagType = null;
        $label = null;
        $slug = null;
        $uri = null;
        $description = null;
        $parentIds = null;
        $childIds = null;
        $aliases = null;
        $contentHash = null;
        $subjectId = null;
        $weight = null;

        $tagBind = $tagStmt->bind_param(
            "ssssssssss",
            $tagId,
            $tagType,
            $label,
            $slug,
            $uri,
            $description,
            $parentIds,
            $childIds,
            $aliases,
            $contentHash
        );
        if (!$tagBind) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Binding tag parameters: ' . $db->error);
        endif;
        if (!$taggingStmt->bind_param("ssss", $tagId, $tagType, $subjectId, $weight)) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Binding tagging parameters: ' . $db->error);
        endif;
        $grandTotalTags = 0;
        $grandTotalTaggings = 0;
        $summary = [];
        $maxFileAge = 23 * 3600;
        $batchSize = 5000;
        $logInterval = 2500;

        try {
            foreach ($tagImports as $importType) :
                $config = $tagConfig[$importType];
                $url = self::requireGameRuleUrl($gameRules, $config['rule']);
                $msg->logMessage('[NOTICE]', "Scryfall {$config['label']} API: fetching $url");
                $bulkInfo = ScryfallBulkFiles::fetchJson($url, $msg, "Scryfall {$config['label']} API", $appConfig);
                if ($bulkInfo === false || ($bulkInfo['type'] ?? '') !== $config['expectedType']) :
                    throw new \Exception("[ERROR] scryfall_tag_definitions: {$config['label']} bulk metadata unavailable");
                endif;

                $downloadUri = $bulkInfo['jsonl_download_uri'] ?? null;
                if (!is_string($downloadUri) || $downloadUri === '') :
                    throw new \Exception("[ERROR] scryfall_tag_definitions: {$config['label']} jsonl_download_uri missing");
                endif;

                $downloadResult = ScryfallBulkFiles::getBulkDataFile(
                    $downloadUri,
                    $config['file'],
                    $maxFileAge,
                    $appConfig
                );
                if ($downloadResult === false) :
                    throw new \Exception("[ERROR] scryfall_tag_definitions: {$config['label']} data download failed");
                endif;

                $summary[] = self::importTagFile(
                    $config,
                    $importType,
                    $db,
                    $msg,
                    $tagStmt,
                    $taggingStmt,
                    $tagId,
                    $tagType,
                    $label,
                    $slug,
                    $uri,
                    $description,
                    $parentIds,
                    $childIds,
                    $aliases,
                    $contentHash,
                    $subjectId,
                    $weight,
                    $grandTotalTags,
                    $grandTotalTaggings,
                    $batchSize,
                    $logInterval
                );
            endforeach;
        } finally {
            $tagStmt->close();
            $taggingStmt->close();
        }

        return [
            'tags' => $grandTotalTags,
            'assignments' => $grandTotalTaggings,
            'summary' => $summary,
        ];
    }

    private static function requireGameRuleUrl(GameRules $gameRules, string $key): string
    {
        $value = $gameRules->get($key);
        if (!is_string($value) || trim($value) === '') :
            throw new \InvalidArgumentException(
                "Missing Scryfall game rule '$key'. Define it in includes/game_rules.php."
            );
        endif;

        return trim($value);
    }

    /**
    * @param array<string, mixed> $config
    * @param \mysqli|object $db
    * @param object $tagStmt
    * @param object $taggingStmt
    */
    private static function importTagFile(
        array $config,
        string $importType,
        $db,
        Message $msg,
        $tagStmt,
        $taggingStmt,
        ?string &$tagId,
        ?string &$tagType,
        ?string &$label,
        ?string &$slug,
        ?string &$uri,
        ?string &$description,
        ?string &$parentIds,
        ?string &$childIds,
        ?string &$aliases,
        ?string &$contentHash,
        ?string &$subjectId,
        ?string &$weight,
        int &$grandTotalTags,
        int &$grandTotalTaggings,
        int $batchSize,
        int $logInterval
    ): string {
        $db->query("DROP TEMPORARY TABLE IF EXISTS `scryfall_tag_keys`");
        $tempTagsResult = $db->query("CREATE TEMPORARY TABLE `scryfall_tag_keys` (
            `tag_id` varchar(36) NOT NULL,
            PRIMARY KEY (`tag_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if ($tempTagsResult === false) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Creating tag key temp table: ' . $db->error);
        endif;

        $db->query("DROP TEMPORARY TABLE IF EXISTS `scryfall_tagging_keys`");
        $tempTaggingsResult = $db->query("CREATE TEMPORARY TABLE `scryfall_tagging_keys` (
            `tag_id` varchar(36) NOT NULL,
            `subject_id` varchar(36) NOT NULL,
            PRIMARY KEY (`tag_id`,`subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if ($tempTaggingsResult === false) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Creating tagging key temp table: ' . $db->error);
        endif;

        $tagKeyStmt = $db->prepare("INSERT IGNORE INTO `scryfall_tag_keys` (tag_id) VALUES (?)");
        if ($tagKeyStmt === false) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Preparing tag key SQL: ' . $db->error);
        endif;

        $taggingKeyStmt = $db->prepare(
            "INSERT IGNORE INTO `scryfall_tagging_keys` (tag_id, subject_id) VALUES (?,?)"
        );
        if ($taggingKeyStmt === false) :
            $tagKeyStmt->close();
            throw new \Exception('[ERROR] scryfall_tag_definitions: Preparing tagging key SQL: ' . $db->error);
        endif;

        if (!$tagKeyStmt->bind_param("s", $tagId)) :
            $tagKeyStmt->close();
            $taggingKeyStmt->close();
            throw new \Exception('[ERROR] scryfall_tag_definitions: Binding tag key parameters: ' . $db->error);
        endif;
        if (!$taggingKeyStmt->bind_param("ss", $tagId, $subjectId)) :
            $tagKeyStmt->close();
            $taggingKeyStmt->close();
            throw new \Exception('[ERROR] scryfall_tag_definitions: Binding tagging key parameters: ' . $db->error);
        endif;

        $countTags = 0;
        $countTagAdd = 0;
        $countTagUpdate = 0;
        $countTagUnchanged = 0;
        $countTagSkipped = 0;
        $countTaggings = 0;
        $countTaggingAdd = 0;
        $countTaggingUpdate = 0;
        $countTaggingUnchanged = 0;
        $timesliceStart = microtime(true);

        if ($db->begin_transaction() === false) :
            throw new \Exception('[ERROR] scryfall_tag_definitions: Starting transaction batch: ' . $db->error);
        endif;

        try {
            foreach (ScryfallBulkFiles::iterateBulkRecords($config['file']) as $value) :
                $countTags++;
                $grandTotalTags++;
                $commitDue = (($countTags + $countTaggings) % $batchSize === 0);
                $logDue = ($countTags % $logInterval === 0);

                if (
                    !isset($value['id'], $value['label'], $value['slug'], $value['type'], $value['uri'])
                    || $value['type'] !== $config['payloadType']
                ) :
                    $countTagSkipped++;
                    $msg->logMessage('[WARNING]', "Skipping {$config['label']} tag $countTags: missing field");
                    continue;
                endif;

                $tagId = (string) $value['id'];
                $tagType = $importType;
                $label = (string) $value['label'];
                $slug = (string) $value['slug'];
                $uri = (string) $value['uri'];
                $description = isset($value['description']) ? (string) $value['description'] : null;
                $parentIds = json_encode($value['parent_ids'] ?? [], JSON_UNESCAPED_SLASHES);
                $childIds = json_encode($value['child_ids'] ?? [], JSON_UNESCAPED_SLASHES);
                $aliases = json_encode($value['aliases'] ?? [], JSON_UNESCAPED_SLASHES);
                if ($parentIds === false || $childIds === false || $aliases === false) :
                    throw new \Exception("[ERROR] scryfall_tag_definitions: Encoding tag metadata failed for $tagId");
                endif;
                $contentHash = sha1(implode("\n", [
                    $tagId,
                    $tagType,
                    $label,
                    $slug,
                    $uri,
                    (string) $description,
                    $parentIds,
                    $childIds,
                    $aliases,
                ]));

                if (!$tagKeyStmt->execute()) :
                    throw new \Exception('[ERROR] scryfall_tag_definitions: Writing tag key: ' . $db->error);
                endif;
                if (!$tagStmt->execute()) :
                    throw new \Exception('[ERROR] scryfall_tag_definitions: Writing tag metadata: ' . $db->error);
                endif;

                if ($tagStmt->affected_rows === 1) :
                    $countTagAdd++;
                elseif ($tagStmt->affected_rows === 2) :
                    $countTagUpdate++;
                else :
                    $countTagUnchanged++;
                endif;

                $taggings = $value['taggings'] ?? [];
                if (!is_array($taggings)) :
                    $taggings = [];
                endif;

                foreach ($taggings as $tagging) :
                    if (!is_array($tagging) || !isset($tagging[$config['subjectField']])) :
                        continue;
                    endif;
                    $subjectId = (string) $tagging[$config['subjectField']];
                    $weight = isset($tagging['weight']) ? (string) $tagging['weight'] : null;

                    if (!$taggingKeyStmt->execute()) :
                        throw new \Exception('[ERROR] scryfall_tag_definitions: Writing tagging key: ' . $db->error);
                    endif;
                    if (!$taggingStmt->execute()) :
                        throw new \Exception('[ERROR] scryfall_tag_definitions: Writing tagging: ' . $db->error);
                    endif;

                    if ($taggingStmt->affected_rows === 1) :
                        $countTaggingAdd++;
                    elseif ($taggingStmt->affected_rows === 2) :
                        $countTaggingUpdate++;
                    else :
                        $countTaggingUnchanged++;
                    endif;
                    $countTaggings++;
                    $grandTotalTaggings++;
                endforeach;

                if ($commitDue) :
                    if ($db->commit() === false) :
                        throw new \Exception('[ERROR] scryfall_tag_definitions: Committing transaction batch: ' . $db->error);
                    endif;
                    if ($db->begin_transaction() === false) :
                        throw new \Exception('[ERROR] scryfall_tag_definitions: Starting transaction batch: ' . $db->error);
                    endif;
                endif;

                if ($logDue) :
                    $timeslice = microtime(true) - $timesliceStart;
                    $msg->logMessage(
                        '[NOTICE]',
                        "Scryfall {$config['label']} progress: $countTags tags and $countTaggings assignments; "
                        . 'timeslice: ' . sprintf('%.2f', $timeslice) . 's'
                    );
                    $timesliceStart = microtime(true);
                endif;
            endforeach;
        } catch (Throwable $e) {
            $msg->logMessage('[ERROR]', "Scryfall {$config['label']} import aborted: " . $e->getMessage());
            $db->rollback();
            $tagKeyStmt->close();
            $taggingKeyStmt->close();
            throw $e;
        }

        if ($db->commit() === false) :
            $tagKeyStmt->close();
            $taggingKeyStmt->close();
            throw new \Exception('[ERROR] scryfall_tag_definitions: Final commit failed: ' . $db->error);
        endif;

        $tagKeyStmt->close();
        $taggingKeyStmt->close();

        $deleteTaggings = $db->execute_query(
            "DELETE stg FROM `scryfall_tag_assignments` stg
            LEFT JOIN `scryfall_tagging_keys` stk
                ON stg.tag_id = stk.tag_id
                AND stg.subject_id = stk.subject_id
            WHERE stg.tag_type = ?
                AND stk.tag_id IS NULL",
            [$importType]
        );
        if ($deleteTaggings === false) :
            throw new \Exception("[ERROR] scryfall_tag_definitions: Deleting stale {$config['label']} assignments: " . $db->error);
        endif;
        $deletedTaggings = $db->affected_rows;

        $deleteTags = $db->execute_query(
            "DELETE st FROM `scryfall_tag_definitions` st
            LEFT JOIN `scryfall_tag_keys` stk
                ON st.id = stk.tag_id
            WHERE st.tag_type = ?
                AND stk.tag_id IS NULL",
            [$importType]
        );
        if ($deleteTags === false) :
            throw new \Exception("[ERROR] scryfall_tag_definitions: Deleting stale {$config['label']} tags: " . $db->error);
        endif;
        $deletedTags = $db->affected_rows;

        $line = "{$config['label']}: $countTags tags, $countTaggings assignments "
            . "(tags added: $countTagAdd; updated: $countTagUpdate; unchanged: $countTagUnchanged; "
            . "skipped: $countTagSkipped; assignments added: $countTaggingAdd; updated: $countTaggingUpdate; "
            . "unchanged: $countTaggingUnchanged; removed tags: $deletedTags; "
            . "removed assignments: $deletedTaggings)";
        $msg->logMessage('[NOTICE]', $line);
        return $line;
    }
}
