<?php

/*
Version:     1.1
Date:        18/09/26
Name:        ScryfallMigrationSafetyAdapter.php
Purpose:     Compatibility adapter mapping ScryfallCardDeletionSafety structured results
             to the legacy numeric score contract used by safeDeleteCheck().
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\Validation;

class ScryfallMigrationSafetyAdapter
{
    private ScryfallCardDeletionSafety $safety;

    /**
     * @param ScryfallCardDeletionSafety $safety The Stage 1 deletion safety service
     */
    public function __construct(ScryfallCardDeletionSafety $safety)
    {
        $this->safety = $safety;
    }

    /**
     * Delegates to the Stage 1 service and maps the structured result to the legacy numeric score.
     *
     * @return int Legacy score: 0 (safe), 1 (reference), or 10001 (failure)
     */
    public function check(string $id): int
    {
        return self::mapSafetyScore($this->safety->checkSingle($id));
    }

    /**
     * Map ScryfallCardDeletionSafety structured results to the legacy numeric score.
     *
     * Validates the structured result contract before mapping. Any malformed result
     * fails closed with score 10001 (safety failure).
     *
     * @param array<string, mixed> $result The structured result from checkSingle()
     * @return int Legacy score: 0 (safe), 1 (reference), or 10001 (failure)
     */
    public static function mapSafetyScore(array $result): int
    {
        // Fail closed: missing required keys
        if (!isset($result['id'], $result['deck_references'], $result['collection_quantity'], $result['check_failed'])) {
            return 10001;
        }

        // Fail closed: wrong types or negative counts
        if (
            !is_string($result['id'])
            || !is_int($result['deck_references'])
            || !is_int($result['collection_quantity'])
            || !is_bool($result['check_failed'])
            || $result['deck_references'] < 0
            || $result['collection_quantity'] < 0
            || !isset($result['failure_reasons'])
            || !is_array($result['failure_reasons'])
        ) {
            return 10001;
        }

        // Fail closed: invalid UUID
        if (Validation::validUUID($result['id']) === false) {
            return 10001;
        }

        // Fail closed: any failure flag or failure reasons
        if ($result['check_failed'] === true || $result['failure_reasons'] !== []) {
            return 10001;
        }

        // Safe to delete: no references
        if ($result['deck_references'] === 0 && $result['collection_quantity'] === 0) {
            return 0;
        }

        // Reference found: hold
        return 1;
    }
}
