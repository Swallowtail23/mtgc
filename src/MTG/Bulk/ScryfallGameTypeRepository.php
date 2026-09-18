<?php

/*
Version:     1.0
Date:        18/09/26
Name:        ScryfallGameTypeRepository.php
Purpose:     Database-backed repository for Scryfall game-type catalogue and
             selection state. Provides methods to read the game-type catalogue,
             query selected types, validate proposed selections, and apply
             selection changes transactionally.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\Message;

class ScryfallGameTypeRepository
{
    private object $db;

    private Message $logger;

    /**
     * @param object $db      mysqli-compatible database object
     * @param Message $logger Structured logger for DEBUG/NOTICE/ERROR output
     */
    public function __construct(object $db, Message $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Return all catalogue rows ordered by sort_order, code.
     *
     * @return array<int, array{code: string, label: string, sort_order: int, selected: int}>
     */
    public function getAll(): array
    {
        $result = $this->db->execute_query(
            "SELECT code, label, sort_order, selected
             FROM scryfall_game_types
             ORDER BY sort_order, code"
        );

        if ($result === false) {
            throw new \RuntimeException(
                'ScryfallGameTypeRepository::getAll - query failed'
            );
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'code' => $row['code'],
                'label' => $row['label'],
                'sort_order' => (int) $row['sort_order'],
                'selected' => (int) $row['selected'],
            ];
        }
        $result->free();

        $this->logger->logMessage(
            '[DEBUG]',
            "ScryfallGameTypeRepository::getAll - loaded " . count($rows) . " catalogue row(s)"
        );

        return $rows;
    }

    /**
     * Return only the selected canonical codes.
     *
     * @return array<int, string>
     */
    public function getSelectedCodes(): array
    {
        $result = $this->db->execute_query(
            "SELECT code FROM scryfall_game_types
             WHERE selected = 1
             ORDER BY sort_order, code"
        );

        if ($result === false) {
            throw new \RuntimeException(
                'ScryfallGameTypeRepository::getSelectedCodes - query failed'
            );
        }

        $codes = [];
        while ($row = $result->fetch_assoc()) {
            $codes[] = $row['code'];
        }
        $result->free();

        $this->logger->logMessage(
            '[DEBUG]',
            "ScryfallGameTypeRepository::getSelectedCodes - loaded " . count($codes) . " selected code(s)"
        );

        if ($codes === []) {
            throw new \RuntimeException(
                'ScryfallGameTypeRepository::getSelectedCodes - no game types are selected'
            );
        }

        return $codes;
    }

    /**
     * Return the full selected rows (code + label + sort_order + selected).
     *
     * @return array<int, array{code: string, label: string, sort_order: int, selected: int}>
     */
    public function getSelected(): array
    {
        $result = $this->db->execute_query(
            "SELECT code, label, sort_order, selected
             FROM scryfall_game_types
             WHERE selected = 1
             ORDER BY sort_order, code"
        );

        if ($result === false) {
            throw new \RuntimeException(
                'ScryfallGameTypeRepository::getSelected - query failed'
            );
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'code' => $row['code'],
                'label' => $row['label'],
                'sort_order' => (int) $row['sort_order'],
                'selected' => (int) $row['selected'],
            ];
        }
        $result->free();

        $this->logger->logMessage(
            '[DEBUG]',
            "ScryfallGameTypeRepository::getSelected - loaded " . count($rows) . " selected row(s)"
        );

        return $rows;
    }

    /**
     * Validate a proposed selection: must contain at least one valid canonical code.
     *
     * Validation contract:
     *   - Reject non-string values (after casting, values that cannot be cast to string are rejected).
     *   - Trim whitespace; lowercase.
     *   - Reject empty values after trimming.
     *   - Deduplicate after normalization, retaining the first occurrence.
     *   - Reject any code not present in the catalogue.
     *
     * @param array<int, mixed> $codes
     * @return array{valid: bool, normalized: array<int, string>, errors: array<int, string>}
     */
    public function validateSelection(array $codes): array
    {
        $errors = [];
        $normalized = [];
        $seen = [];
        $hasErrors = false;

        foreach ($codes as $index => $value) {
            // Reject non-string values (including int, float, bool, null, resource, array, object)
            if (!is_string($value)) {
                $errors[] = "Invalid value at index {$index}: expected string, got " . gettype($value);
                $hasErrors = true;
                continue;
            }

            $str = $value;
            $str = trim($str);
            $str = strtolower($str);

            if ($str === '') {
                $errors[] = "Empty value at index {$index}";
                $hasErrors = true;
                continue;
            }

            if (isset($seen[$str])) {
                $this->logger->logMessage(
                    '[DEBUG]',
                    "ScryfallGameTypeRepository::validateSelection - duplicate '{$str}' at index {$index}, skipping"
                );
                continue;
            }

            $seen[$str] = true;
            $normalized[] = $str;
        }

        if (empty($normalized)) {
            if ($errors === []) {
                $errors[] = 'At least one game type is required';
            }
            return ['valid' => false, 'normalized' => [], 'errors' => $errors];
        }

        // Load catalogue codes for validation
        $catalogueCodes = $this->getCatalogueCodes();

        // Check each normalized code against the catalogue
        $validCodes = [];
        foreach ($normalized as $code) {
            if (!in_array($code, $catalogueCodes, true)) {
                $errors[] = "Unknown game type code: {$code}";
                $hasErrors = true;
            } else {
                $validCodes[] = $code;
            }
        }

        if (empty($validCodes)) {
            return ['valid' => false, 'normalized' => [], 'errors' => $errors];
        }

        // If any input values were invalid, the overall result is not valid
        if ($hasErrors) {
            return ['valid' => false, 'normalized' => [], 'errors' => $errors];
        }

        $this->logger->logMessage(
            '[DEBUG]',
            "ScryfallGameTypeRepository::validateSelection - valid codes: " . implode(', ', $validCodes)
        );

        return ['valid' => true, 'normalized' => $validCodes, 'errors' => $errors];
    }

    /**
     * Apply a selection change transactionally.
     *
     * Uses SELECT ... FOR UPDATE to lock catalogue rows, validates against the
     * locked catalogue, deselects all codes, then selects the validated codes.
     * Verifies at least one row is selected before committing.
     *
     * @param array<int, mixed> $codes
     * @return array{success: bool, errors: array<int, string>}
     */
    public function applySelection(array $codes): array
    {
        try {
            // Begin transaction first; if this fails, no locking query runs outside a transaction.
            $beginResult = $this->db->execute_query('BEGIN');
            if ($beginResult === false) {
                throw new \RuntimeException('applySelection: transaction begin failed');
            }

            // Lock catalogue rows and read codes from the locked result.
            $lockResult = $this->db->execute_query(
                'SELECT code FROM scryfall_game_types ORDER BY code FOR UPDATE'
            );
            if ($lockResult === false) {
                throw new \RuntimeException('applySelection: FOR UPDATE query failed');
            }

            // Read catalogue codes from the locked result directly (no second query needed).
            $catalogueCodes = [];
            while ($row = $lockResult->fetch_assoc()) {
                $catalogueCodes[] = $row['code'];
            }
            $lockResult->free();

            // Private validation against locked catalogue codes.
            $validated = $this->validateSelectionAgainst($codes, $catalogueCodes);
            if (!$validated['valid']) {
                // Rollback failure is logged; original validation error preserved.
                $rb = $this->db->execute_query('ROLLBACK');
                if ($rb === false) {
                    $this->logger->logMessage(
                        '[ERROR]',
                        'applySelection: ROLLBACK failed after validation error'
                    );
                }
                return ['success' => false, 'errors' => $validated['errors']];
            }

            // Deselect all
            $deselectResult = $this->db->execute_query(
                'UPDATE scryfall_game_types SET selected = 0'
            );
            if ($deselectResult === false) {
                $rb = $this->db->execute_query('ROLLBACK');
                if ($rb === false) {
                    $this->logger->logMessage(
                        '[ERROR]',
                        'applySelection: ROLLBACK failed after deselect'
                    );
                }
                throw new \RuntimeException('applySelection: deselect failed');
            }

            // Select the validated codes
            $placeholders = implode(',', array_fill(0, count($validated['normalized']), '?'));
            $sql = "UPDATE scryfall_game_types SET selected = 1 WHERE code IN ({$placeholders})";
            $selectResult = $this->db->execute_query($sql, $validated['normalized']);
            if ($selectResult === false) {
                $rb = $this->db->execute_query('ROLLBACK');
                if ($rb === false) {
                    $this->logger->logMessage(
                        '[ERROR]',
                        'applySelection: ROLLBACK failed after select'
                    );
                }
                throw new \RuntimeException('applySelection: select failed');
            }

            // Verify at least one row is selected
            $verifyResult = $this->db->execute_query(
                'SELECT COUNT(*) AS cnt FROM scryfall_game_types WHERE selected = 1'
            );
            if ($verifyResult === false) {
                $rb = $this->db->execute_query('ROLLBACK');
                if ($rb === false) {
                    $this->logger->logMessage(
                        '[ERROR]',
                        'applySelection: ROLLBACK failed after verify'
                    );
                }
                throw new \RuntimeException('applySelection: verify query failed');
            }
            $row = $verifyResult->fetch_assoc();
            if ((int) $row['cnt'] === 0) {
                $rb = $this->db->execute_query('ROLLBACK');
                if ($rb === false) {
                    $this->logger->logMessage(
                        '[ERROR]',
                        'applySelection: ROLLBACK failed after zero-selected check'
                    );
                }
                throw new \RuntimeException('applySelection: no game types selected after update');
            }

            // Commit; check result before returning success.
            $commitResult = $this->db->execute_query('COMMIT');
            if ($commitResult === false) {
                // Commit failed: attempt rollback to clean up, then return failure.
                $rb = $this->db->execute_query('ROLLBACK');
                if ($rb === false) {
                    $this->logger->logMessage(
                        '[ERROR]',
                        'applySelection: ROLLBACK failed after commit failure'
                    );
                }
                $this->logger->logMessage('[ERROR]', 'applySelection: commit failed');
                return ['success' => false, 'errors' => ['commit failed']];
            }

            $this->logger->logMessage(
                '[NOTICE]',
                "applySelection: committed selection for " . count($validated['normalized']) . " code(s)"
            );

            return ['success' => true, 'errors' => []];
        } catch (\Throwable $e) {
            $rb = $this->db->execute_query('ROLLBACK');
            if ($rb === false) {
                $this->logger->logMessage(
                    '[ERROR]',
                    'applySelection: ROLLBACK failed in catch block'
                );
            }
            $this->logger->logMessage('[ERROR]', 'applySelection failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Validate against a pre-loaded catalogue (used inside applySelection after FOR UPDATE lock).
     *
     * @param array<int, mixed> $codes
     * @param array<int, string> $catalogueCodes
     * @return array{valid: bool, normalized: array<int, string>, errors: array<int, string>}
     */
    private function validateSelectionAgainst(array $codes, array $catalogueCodes): array
    {
        $errors = [];
        $normalized = [];
        $seen = [];

        foreach ($codes as $index => $value) {
            if (!is_string($value)) {
                continue;
            }

            $str = $value;
            $str = trim($str);
            $str = strtolower($str);

            if ($str === '') {
                continue;
            }

            if (isset($seen[$str])) {
                continue;
            }

            $seen[$str] = true;
            $normalized[] = $str;
        }

        if (empty($normalized)) {
            if ($errors === []) {
                $errors[] = 'At least one game type is required';
            }
            return ['valid' => false, 'normalized' => [], 'errors' => $errors];
        }

        $catalogueSet = array_flip($catalogueCodes);
        $validCodes = [];

        foreach ($normalized as $code) {
            if (!isset($catalogueSet[$code])) {
                $errors[] = "Unknown game type code: {$code}";
            } else {
                $validCodes[] = $code;
            }
        }

        if (empty($validCodes)) {
            return ['valid' => false, 'normalized' => [], 'errors' => $errors];
        }

        if ($errors !== []) {
            return ['valid' => false, 'normalized' => [], 'errors' => $errors];
        }

        return ['valid' => true, 'normalized' => $validCodes, 'errors' => $errors];
    }

    /**
     * Return a deterministic fingerprint of the current selection.
     *
     * Canonical codes are sorted, joined with a delimiter, and SHA-256 hashed.
     * This is a game-type-only fingerprint; the complete policy fingerprint
     * (including langs_to_skip, layouts_to_skip) is integrated in Stage 6.
     */
    public function getSelectionFingerprint(): string
    {
        $codes = $this->getSelectedCodes();
        sort($codes);
        $hash = hash('sha256', implode(',', $codes));

        $this->logger->logMessage(
            '[DEBUG]',
            "ScryfallGameTypeRepository::getSelectionFingerprint - fingerprint: {$hash}"
        );

        return $hash;
    }

    /**
     * Load catalogue codes from the database.
     *
     * @return array<int, string>
     */
    private function getCatalogueCodes(): array
    {
        $result = $this->db->execute_query(
            "SELECT code FROM scryfall_game_types ORDER BY code"
        );

        if ($result === false) {
            throw new \RuntimeException(
                'ScryfallGameTypeRepository::getCatalogueCodes - query failed'
            );
        }

        $codes = [];
        while ($row = $result->fetch_assoc()) {
            $codes[] = $row['code'];
        }
        $result->free();

        return $codes;
    }
}
