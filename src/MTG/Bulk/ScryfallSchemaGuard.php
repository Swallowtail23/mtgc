<?php

/*
Version:     1.0
Date:        10/07/26
Name:        ScryfallSchemaGuard.php
Purpose:     Validate database schema prerequisites for Scryfall bulk imports.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\Message;

class ScryfallSchemaGuard
{
    /** @param \mysqli|object $db */
    public function __construct(private mixed $db, private Message $msg, private string $context)
    {
    }

    public function requireTable(string $table): void
    {
        $this->validateTable($table);
        $result = $this->db->execute_query(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        if ($result === false) :
            throw new \Exception("[ERROR] {$this->context}: Checking $table table: {$this->db->error}");
        endif;
        $exists = $result->num_rows > 0;
        $result->free();
        if (!$exists) :
            throw new \Exception("[ERROR] {$this->context}: $table table missing; apply schema updates first");
        endif;
        $this->msg->logMessage('[DEBUG]', "$table table present");
    }

    /** @param array<int, string> $columns */
    public function requireColumns(string $table, array $columns): void
    {
        $this->validateTable($table);
        foreach ($columns as $column) :
            $result = $this->db->query(sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $table, $column));
            if ($result === false) :
                throw new \Exception("[ERROR] {$this->context}: Checking $table $column column: {$this->db->error}");
            endif;
            $exists = $result->num_rows > 0;
            $result->free();
            if (!$exists) :
                throw new \Exception(
                    "[ERROR] {$this->context}: $table $column column missing (manual schema update required)"
                );
            endif;
            $this->msg->logMessage('[DEBUG]', "$table $column column present");
        endforeach;
    }

    /** @param array<int, string> $indexes */
    public function requireIndexes(string $table, array $indexes): void
    {
        $this->validateTable($table);
        foreach ($indexes as $index) :
            $result = $this->db->query(sprintf("SHOW INDEX FROM `%s` WHERE Key_name = '%s'", $table, $index));
            if ($result === false) :
                throw new \Exception("[ERROR] {$this->context}: Checking $table $index index: {$this->db->error}");
            endif;
            $exists = $result->num_rows > 0;
            $result->free();
            if (!$exists) :
                throw new \Exception(
                    "[ERROR] {$this->context}: $table $index index missing (manual schema update required)"
                );
            endif;
            $this->msg->logMessage('[DEBUG]', "$table $index index present");
        endforeach;
    }

    private function validateTable(string $table): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) :
            throw new \InvalidArgumentException("Invalid table name '$table'");
        endif;
    }
}
