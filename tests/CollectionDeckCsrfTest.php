<?php

/*
Version:     1.0
Date:        29/04/26
Name:        CollectionDeckCsrfTest.php
Purpose:     Tests collection/deck CSRF protections.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CollectionDeckCsrfTest extends TestCase
{
    public function testCollectionDeleteUsesPostAndCsrf(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../collection.php');

        $this->assertStringContainsString('SessionManager::generateCsrfToken()', $source);
        $this->assertStringContainsString('SessionManager::validateCsrfToken', $source);
        $this->assertStringContainsString('name="csrf_token"', $source);
        $this->assertStringContainsString('name="deletecollection" value="DELETE"', $source);
        $this->assertStringContainsString('method="POST"', $source);
        $this->assertStringContainsString("isset(\$_POST['deletecollection'])", $source);
        $this->assertStringNotContainsString("isset(\$_GET['deletecollection'])", $source);
    }

    public function testDeckCreateAndDeleteUseCsrfBeforeProcessing(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../decks.php');

        $guardPosition = strpos($source, "if (\$requestMethod === 'POST') :");
        $newDeckPosition = strpos($source, 'if ($newdeck == "yes") :');
        $deleteDeckPosition = strpos($source, 'if ($deletedeck == "yes") :');

        $this->assertStringContainsString('SessionManager::generateCsrfToken()', $source);
        $this->assertStringContainsString('SessionManager::validateCsrfToken', $source);
        $this->assertStringContainsString('name="csrf_token"', $source);
        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($newDeckPosition);
        $this->assertNotFalse($deleteDeckPosition);
        $this->assertLessThan($newDeckPosition, $guardPosition);
        $this->assertLessThan($deleteDeckPosition, $guardPosition);
    }

    public function testDeckDeleteAssertsOwnershipBeforeDeleting(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../decks.php');

        $assertPosition = strpos($source, 'assertDeckOwner($deckToDelete, $user,');
        $deletePosition = strpos($source, '$obj->delDeck($deckToDelete);');

        $this->assertNotFalse($assertPosition);
        $this->assertNotFalse($deletePosition);
        $this->assertLessThan($deletePosition, $assertPosition);
    }
}
