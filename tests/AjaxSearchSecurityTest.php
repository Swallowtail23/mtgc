<?php

/*
Version:     1.0
Date:        29/04/26
Name:        AjaxSearchSecurityTest.php
Purpose:     Tests quick-search HTML output hardening.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class AjaxSearchSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../ajax/ajaxsearch.php');
    }

    public function testQuickSearchEscapesHtmlAttributes(): void
    {
        $this->assertStringContainsString('htmlspecialchars("$displaysetcode - $displayName"', $this->source);
        $this->assertStringContainsString('$setcodeEsc = htmlspecialchars($displaysetcode', $this->source);
        $this->assertStringContainsString('$ajaxidEsc = htmlspecialchars($ajaxid', $this->source);
        $this->assertStringContainsString('<td title="<?php echo $titleEsc; ?>" class="name">', $this->source);
        $this->assertStringContainsString('href=\"carddetail.php?id=$ajaxidEsc\"', $this->source);
    }

    public function testQuickSearchValidatesCardIdBeforeRenderingUrl(): void
    {
        $validationPosition = strpos($this->source, 'Validation::validUUID($id, $appConfig)');
        $hrefPosition = strpos($this->source, 'href=\"carddetail.php?id=$ajaxidEsc\"');

        $this->assertNotFalse($validationPosition);
        $this->assertNotFalse($hrefPosition);
        $this->assertLessThan($hrefPosition, $validationPosition);
    }
}
