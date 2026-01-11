<?php

use MTG\Core\Text\TextHelper;
use PHPUnit\Framework\TestCase;

class TextHelperTest extends TestCase
{
    public function testAutoLinkAddsAnchor()
    {
        $text = 'Visit http://example.test for info';
        $linked = TextHelper::autoLink($text, ['class' => 'link']);

        $this->assertStringContainsString('<a href="http://example.test" class="link">', $linked);
    }
}
