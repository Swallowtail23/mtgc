<?php

/*
Version:     1.0
Date:        29/04/26
Name:        AdminUsersSecurityTest.php
Purpose:     Tests admin user management CSRF protections.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class AdminUsersSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../admin/users.php');
    }

    public function testAdminUserFormsUsePostAndCsrf(): void
    {
        $this->assertStringContainsString('SessionManager::generateCsrfToken()', $this->source);
        $this->assertStringContainsString('name="csrf_token"', $this->source);
        $this->assertStringContainsString('SessionManager::validateCsrfToken', $this->source);
    }

    public function testAdminUserPostActionsRequireCsrfBeforeProcessing(): void
    {
        $guardPosition = strpos($this->source, "if (\$requestMethod === 'POST') :");
        $newUserPosition = strpos($this->source, "if (isset(\$_POST['newuser'])) :");
        $updateUsersPosition = strpos($this->source, "if (isset(\$_POST['updateusers'])) :");

        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($newUserPosition);
        $this->assertNotFalse($updateUsersPosition);
        $this->assertLessThan($newUserPosition, $guardPosition);
        $this->assertLessThan($updateUsersPosition, $guardPosition);
    }
}
