<?php

/*
Version:     1.1
Date:        04/02/26
Name:        SessionUser.php
Purpose:     Typed session user accessor for secure pages.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Auth;

class SessionUser
{
    /**
    * @var array<string,mixed>
    */
    private $data;
    /**
    * @var string
    */
    private $email;

    /**
    * @param array<string,mixed> $data
    */
    public function __construct(array $data, string $email)
    {
        $this->data = $data;
        $this->email = $email;
    }

    public function id(): int
    {
        return (int) ($this->data['usernumber'] ?? 0);
    }

    public function userName(): string
    {
        return (string) ($this->data['username'] ?? '');
    }

    public function adminLevel(): int
    {
        return (int) ($this->data['admin'] ?? 0);
    }

    public function groupInOut(): int
    {
        return (int) ($this->data['grpinout'] ?? 0);
    }

    public function groupId(): int
    {
        return (int) ($this->data['groupid'] ?? 0);
    }

    public function collectionView(): string
    {
        return (string) ($this->data['collection_view'] ?? '');
    }

    public function table(): string
    {
        return (string) ($this->data['table'] ?? '');
    }

    public function fxEnabled(): bool
    {
        return (bool) ($this->data['fx'] ?? false);
    }

    public function currency(): string
    {
        return (string) ($this->data['currency'] ?? '');
    }

    public function rate(): float
    {
        $rate = $this->data['rate'] ?? 0.0;
        return is_numeric($rate) ? (float) $rate : 0.0;
    }

    public function fxPending(): bool
    {
        return (bool) ($this->data['fx_pending'] ?? false);
    }

    public function fxMissing(): bool
    {
        return (bool) ($this->data['fx_missing'] ?? false);
    }

    public function email(): string
    {
        return $this->email;
    }

    /**
    * @return array<string,mixed>
    */
    public function toArray(): array
    {
        return $this->data + ['email' => $this->email];
    }
}
