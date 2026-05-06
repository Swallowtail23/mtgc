<?php

/*
Version:     1.0
Date:        06/05/26
Name:        AjaxSetImageSecurityTest.php
Purpose:     Tests set image reload AJAX authorization.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class AjaxSetImageSecurityTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(__DIR__ . '/../ajax/ajaxsetimg.php');
    }

    public function testSetImageReloadRequiresServerSideAdmin(): void
    {
        $this->assertStringContainsString('$admin                      = $ctx->sessionUser()->adminLevel();', $this->source);
        $this->assertStringContainsString('if ($admin !== 1) :', $this->source);
        $this->assertStringContainsString('"Admin access required"', $this->source);
        $this->assertStringContainsString('AjaxResponse::json(["status" => "error", "message" => "Admin access required"], 403);', $this->source);
    }

    public function testAdminCheckRunsBeforeCommandExecution(): void
    {
        $adminPosition = strpos($this->source, 'if ($admin !== 1) :');
        $execPosition = strpos($this->source, 'exec($cmd);');

        $this->assertNotFalse($adminPosition);
        $this->assertNotFalse($execPosition);
        $this->assertLessThan($execPosition, $adminPosition);
    }

    public function testSetcodeValidationStillRunsBeforeCommandBuild(): void
    {
        $validationPosition = strpos($this->source, "preg_match('/^[A-Za-z0-9_]+$/'");
        $commandPosition = strpos($this->source, '$cmd = "php $safeRoot $safeSetcode > /dev/null 2>&1 &";');

        $this->assertNotFalse($validationPosition);
        $this->assertNotFalse($commandPosition);
        $this->assertLessThan($commandPosition, $validationPosition);
    }
}
