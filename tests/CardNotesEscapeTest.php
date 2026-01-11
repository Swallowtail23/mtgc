<?php

use MTG\Cards\CardUtils;
use PHPUnit\Framework\TestCase;

class CardNotesEscapeTest extends TestCase
{
    public function testEscapeCardNotesForTextareaEncodesHtml()
    {
        $payload = '</textarea><script>alert("x")</script> & "\'';
        $expected = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

        $this->assertSame($expected, CardUtils::escapeCardNotesForTextarea($payload));
        $this->assertStringNotContainsString('<script>', CardUtils::escapeCardNotesForTextarea($payload));
        $this->assertStringContainsString('&lt;script&gt;', CardUtils::escapeCardNotesForTextarea($payload));
    }
}
