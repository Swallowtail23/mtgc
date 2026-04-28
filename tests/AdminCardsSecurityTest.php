<?php

use PHPUnit\Framework\TestCase;

class AdminCardsSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../admin/cards.php');
    }

    public function testAdminCardMutationFormsUsePostAndCsrf(): void
    {
        $this->assertStringContainsString('method="POST"', $this->source);
        $this->assertStringContainsString('name="csrf_token"', $this->source);
        $this->assertStringContainsString('SessionManager::validateCsrfToken', $this->source);
    }

    public function testAdminCardMutationsAreNotHandledFromGet(): void
    {
        $this->assertStringNotContainsString("isset(\$_GET['delete'])", $this->source);
        $this->assertStringNotContainsString("isset(\$_GET['deleteimg'])", $this->source);
        $this->assertStringContainsString("\$_SERVER['REQUEST_METHOD'] === 'POST'", $this->source);
    }

    public function testAdminCardPostIdIsUuidValidated(): void
    {
        $this->assertStringContainsString("filter_input(INPUT_POST, 'id', FILTER_UNSAFE_RAW)", $this->source);
        $this->assertStringContainsString('Validation::validUUID($idRaw, $appConfig)', $this->source);
    }
}
