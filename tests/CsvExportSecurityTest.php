<?php

/*
Version:     1.0
Date:        29/04/26
Name:        CsvExportSecurityTest.php
Purpose:     Tests CSV export CSRF protections.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CsvExportSecurityTest extends TestCase
{
    public function testCsvEndpointRequiresCsrfAndSplitsMethodsByAction(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../csv.php');

        $this->assertStringContainsString('SessionManager::validateCsrfToken', $source);
        $this->assertStringContainsString('$requestMethod === \'GET\' && $requestType === \'echo\'', $source);
        $this->assertStringContainsString('$requestMethod === \'POST\' && $requestType === \'email\'', $source);
        $this->assertStringNotContainsString("isset(\$_GET['type']) && \$_GET['type'] === 'email'", $source);
    }

    public function testCollectionCsvFormsIncludeExpectedMethodsAndTokens(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../collection.php');

        $this->assertStringContainsString('<form action="csv.php"  method="GET">', $source);
        $this->assertStringContainsString('<form action="csv.php" method="POST">', $source);
        $this->assertStringContainsString('name="csrf_token"', $source);
        $this->assertStringContainsString("name='type' value='echo'", $source);
        $this->assertStringContainsString("name='type' value='email'", $source);
    }

    public function testAdminCsvExportIncludesCsrfToken(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../admin/users.php');

        $this->assertStringContainsString('action="/csv.php"  method="GET"', $source);
        $this->assertStringContainsString('name="csrf_token"', $source);
        $this->assertStringContainsString("name='type' value='echo'", $source);
    }
}
