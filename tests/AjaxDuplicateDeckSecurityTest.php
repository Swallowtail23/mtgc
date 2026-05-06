<?php

/*
Version:     1.0
Date:        06/05/26
Name:        AjaxDuplicateDeckSecurityTest.php
Purpose:     Tests duplicate deck AJAX authorization boundaries.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class AjaxDuplicateDeckSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../ajax/ajaxduplicatedeck.php');
    }

    public function testDuplicateDeckDoesNotTrustPostedOwner(): void
    {
        $this->assertStringNotContainsString("filter_input(INPUT_POST, 'user'", $this->source);
        $this->assertStringContainsString('$user                       = $ctx->sessionUser()->id();', $this->source);
        $this->assertStringContainsString('$decksuccess = $obj->addDeck($user, $newdeckname);', $this->source);
    }

    public function testDuplicateDeckAssertsSourceDeckOwnerBeforeExport(): void
    {
        $assertPosition = strpos($this->source, "assertDeckOwner(\$deckNumber, \$user, 'ajaxduplicatedeck.php')");
        $exportPosition = strpos($this->source, '$cardlist = $obj->exportDeck($deckNumber, "variable");');

        $this->assertNotFalse($assertPosition);
        $this->assertNotFalse($exportPosition);
        $this->assertLessThan($exportPosition, $assertPosition);
    }

    public function testDuplicateDeckRejectsUnauthorizedSourceDeck(): void
    {
        $this->assertStringContainsString("http_response_code(403);", $this->source);
        $this->assertStringContainsString("\$response['error'] = 'Deck access denied';", $this->source);
    }

    public function testDuplicateDeckValidatesDeckNumberAsInteger(): void
    {
        $this->assertStringContainsString("filter_input(INPUT_POST, 'decknumber', FILTER_VALIDATE_INT", $this->source);
        $this->assertStringContainsString("'min_range' => 1", $this->source);
    }
}
