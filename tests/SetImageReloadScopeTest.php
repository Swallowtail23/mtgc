<?php

/*
Version:     1.0
Date:        26/08/26
Name:        SetImageReloadScopeTest.php
Purpose:     Tests set image reload scope validation and query selection.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\SetImageReloadScope;
use PHPUnit\Framework\TestCase;

class SetImageReloadScopeTest extends TestCase
{
    public function testOnlyNamedScopesAreValid(): void
    {
        $this->assertTrue(SetImageReloadScope::isValid('primary'));
        $this->assertTrue(SetImageReloadScope::isValid('all'));
        $this->assertFalse(SetImageReloadScope::isValid(''));
        $this->assertFalse(SetImageReloadScope::isValid('english'));
    }

    public function testPrimaryScopeFiltersOnPrimaryCard(): void
    {
        $query = SetImageReloadScope::cardIdQuery(SetImageReloadScope::PRIMARY);

        $this->assertStringContainsString('WHERE setcode = ? AND primary_card = 1', $query);
    }

    public function testAllScopeDoesNotFilterOnPrimaryCard(): void
    {
        $query = SetImageReloadScope::cardIdQuery(SetImageReloadScope::ALL);

        $this->assertStringContainsString('WHERE setcode = ?', $query);
        $this->assertStringNotContainsString('primary_card', $query);
    }

    public function testInvalidScopeCannotProduceAQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SetImageReloadScope::cardIdQuery('invalid');
    }
}
