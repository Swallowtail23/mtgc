<?php

/*
Version:     1.12
Date:        11/01/26
Name:        ClassName.php
Purpose:     {Short description of what this class does}.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Core;

class ClassName
{
    /**
     * Database connection handle.
     *
     * @var \mysqli|object
     */
    private $db;
    private $appConfig;

    /**
     * Message logger instance.
     *
     * @var Message
     */
    private $message;

    /**
     * Any per-instance identifier, e.g. email/ID/usernumber.
     *
     * @var string|null
     */
    private $identifier = null;

    /**
     * Example public state storage (adjust/remove as needed).
     *
     * @var array<string,mixed>
     */
    public $state = [];

    /**
     * Constructor.
     *
     * @param \mysqli|object $db      Database connection.
     * @param AppConfig   $appConfig  App configuration container.
     * @param string|null $identifier Optional per-instance identifier (e.g. user email).
     */
    public function __construct($db, AppConfig $appConfig, $identifier = null)
    {
        $this->db         = $db;
        $this->appConfig  = $appConfig;
        $this->identifier = $identifier;
        $this->message    = new Message($this->appConfig);
    }

    /**
     * Example: validate internal state / parameters.
     *
     * @return bool True when valid; false otherwise.
     */
    public function validate(): bool
    {
        if ($this->identifier === null || $this->identifier === '') :
            $this->message->logMessage('[ERROR]', 'Missing identifier');
            return false;
        endif;

        return true;
    }

    /**
     * Example: run a simple SELECT using mysqli::execute_query().
     *
     * @return array<string,mixed>|null
     */
    public function loadRecord(): ?array
    {
        if (!$this->validate()) :
            return null;
        endif;

        $query = "SELECT some_column, other_column FROM some_table WHERE identifier = ? LIMIT 1";

        $result = $this->db->execute_query($query, [$this->identifier]);

        if ($result === false) :
            $this->message->logMessage('[ERROR]', 'SQL failure: ' . $this->db->error);
            return null;
        endif;

        if ($result->num_rows !== 1) :
            $this->message->logMessage('[DEBUG]', "No record for {$this->identifier}");
            return null;
        endif;

        $row = $result->fetch_assoc();

        if (!is_array($row)) :
            $this->message->logMessage('[ERROR]', 'fetch_assoc() failed');
            return null;
        endif;

        $this->state = $row;

        return $row;
    }

    /**
     * Example: run an UPDATE and log result.
     *
     * @param array<string,mixed> $data
     * @return bool
     */
    public function saveRecord(array $data): bool
    {
        if (!$this->validate()) :
            return false;
        endif;

        // Example update — adjust to suit.
        $query = "UPDATE some_table
                     SET some_column = ?
                   WHERE identifier = ?";

        $someValue = $data['some_column'] ?? null;

        $result = $this->db->execute_query($query, [$someValue, $this->identifier]);

        if ($result !== true) :
            $this->message->logMessage(
                '[ERROR]',
                'UPDATE failed: ' . $this->db->error
            );
            return false;
        endif;

        $this->message->logMessage(
            '[DEBUG]',
            "UPDATE ok: {$this->db->info}"
        );

        return true;
    }

    /**
     * String cast handler for debugging misuse.
     *
     * @return string
     */
}
