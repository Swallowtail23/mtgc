<?php

/*
Version:     1.0
Date:        06/05/26
Name:        ValueUpdateSecurityTest.php
Purpose:     Tests collection value update ownership boundaries.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class ValueUpdateSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../valueupdate.php');
    }

    public function testValueUpdateUsesSessionCollectionTable(): void
    {
        $this->assertStringContainsString('$mytable                    = $sessionUser->table();', $this->source);
        $this->assertStringContainsString('Validation::validTableName($mytable, $appConfig)', $this->source);
        $this->assertStringContainsString('$obj->updateCollectionValues($mytable);', $this->source);
    }

    public function testValueUpdateRejectsMismatchedRequestedTable(): void
    {
        $this->assertStringContainsString('$requestedTable = filter_input(INPUT_GET, \'table\'', $this->source);
        $this->assertStringContainsString('if ($requestedTable !== $mytable) :', $this->source);
        $this->assertStringContainsString('http_response_code(403);', $this->source);
        $this->assertStringContainsString('Collection table access denied', $this->source);
    }

    public function testValueUpdateDoesNotUpdateCallerSuppliedTable(): void
    {
        $this->assertStringNotContainsString('updateCollectionValues($table)', $this->source);
        $this->assertStringNotContainsString('updateCollectionValues($requestedTable)', $this->source);
    }
}
