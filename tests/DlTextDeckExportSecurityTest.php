<?php

/*
Version:     1.0
Date:        06/05/26
Name:        DlTextDeckExportSecurityTest.php
Purpose:     Tests deck text export ownership enforcement.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class DlTextDeckExportSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../dltext.php');
    }

    public function testDeckNumberIsValidatedAsPositiveInteger(): void
    {
        $this->assertStringContainsString('FILTER_VALIDATE_INT', $this->source);
        $this->assertStringContainsString("'min_range' => 1", $this->source);
    }

    public function testDeckExportAssertsOwnerBeforeExport(): void
    {
        $assertPosition = strpos($this->source, "assertDeckOwner(\$deckNumber, \$user, 'dltext.php')");
        $exportPosition = strpos($this->source, '$obj->exportDeck($deckNumber, "download");');

        $this->assertNotFalse($assertPosition);
        $this->assertNotFalse($exportPosition);
        $this->assertLessThan($exportPosition, $assertPosition);
    }

    public function testUnauthorizedDeckExportIsRejected(): void
    {
        $this->assertStringContainsString("http_response_code(403);", $this->source);
        $this->assertStringContainsString("deck export ownership failed", $this->source);
        $this->assertStringContainsString("header('Location: decks.php');", $this->source);
    }

    public function testDeckExportUsesSessionUserForOwnership(): void
    {
        $this->assertStringContainsString('$user                       = $sessionUser->id();', $this->source);
        $this->assertStringNotContainsString("filter_input(INPUT_POST, 'user'", $this->source);
    }
}
