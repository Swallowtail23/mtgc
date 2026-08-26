<?php

/*
Version:     1.0
Date:        26/08/26
Name:        SetImageReloadUiTest.php
Purpose:     Tests the admin set image reload scope controls.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class SetImageReloadUiTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../sets.php');
    }

    public function testReloadMenuOffersBothExplicitScopes(): void
    {
        $this->assertStringContainsString("{ scope: 'primary', label: 'Primary language only' }", $this->source);
        $this->assertStringContainsString("{ scope: 'all', label: 'All languages...' }", $this->source);
        $this->assertStringContainsString('data: { setcode: setcode, scope: scope, csrf_token: csrfToken }', $this->source);
    }

    public function testAllLanguageReloadRequiresConfirmation(): void
    {
        $this->assertStringContainsString("scope === 'all'", $this->source);
        $this->assertStringContainsString('window.confirm(', $this->source);
        $this->assertStringContainsString('This can start a large background job.', $this->source);
    }

    public function testReloadControlIsAnAccessibleButtonMenu(): void
    {
        $this->assertStringContainsString("trigger.type = 'button'", $this->source);
        $this->assertStringContainsString("trigger.setAttribute('aria-haspopup', 'menu')", $this->source);
        $this->assertStringContainsString("menu.setAttribute('role', 'menu')", $this->source);
        $this->assertStringContainsString("menuItem.setAttribute('role', 'menuitem')", $this->source);
        $this->assertStringContainsString("event.key === 'ArrowDown' || event.key === 'ArrowUp'", $this->source);
    }
}
