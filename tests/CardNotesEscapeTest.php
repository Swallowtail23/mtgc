<?php

use PHPUnit\Framework\TestCase;

class CardNotesEscapeTest extends TestCase
{
    public function testEscapeCardNotesForTextareaEncodesHtml()
    {
        $payload = '</textarea><script>alert("x")</script> & "\'';
        $expected = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

        $this->assertSame($expected, escapeCardNotesForTextarea($payload));
        $this->assertStringNotContainsString('<script>', escapeCardNotesForTextarea($payload));
        $this->assertStringContainsString('&lt;script&gt;', escapeCardNotesForTextarea($payload));
    }
}
