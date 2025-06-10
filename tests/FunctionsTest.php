<?php
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function testValidUuid()
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $this->assertSame($uuid, valid_uuid($uuid));
        $this->assertFalse(valid_uuid('not-a-uuid'));
    }

    public function testValidTablename()
    {
        $this->assertSame('123collection', valid_tablename('123collection'));
        $this->assertFalse(valid_tablename('abc'));
    }

    public function testIsValidSetcode()
    {
        $this->assertSame(1, isValidSetcode('MH3'));
        $this->assertSame(1, isValidSetcode(''));
        $this->assertSame(0, isValidSetcode('ab'));
        $this->assertSame(0, isValidSetcode('toolongcode'));
    }

    public function testIsValidCardName()
    {
        $this->assertSame(1, isValidCardName('Plains'));
        $this->assertSame(1, isValidCardName(''));
        $this->assertSame(0, isValidCardName('12345'));
    }

    public function testIsValidLanguageCode()
    {
        $this->assertSame(1, isValidLanguageCode('en'));
        $this->assertSame(1, isValidLanguageCode(''));
        $this->assertSame(0, isValidLanguageCode('en1'));
    }

    public function testInArrayCaseInsensitive()
    {
        $list = ['Alpha', 'Beta'];
        $this->assertTrue(in_array_case_insensitive('alpha', $list));
        $this->assertFalse(in_array_case_insensitive('gamma', $list));
    }

    public function testCheckInput()
    {
        $this->assertSame(123, check_input(123));
        $this->assertSame("'test'", check_input('test'));
    }

    public function testValidPass()
    {
        $this->assertTrue(valid_pass('Abcdef12'));
        $this->assertFalse(valid_pass('weak'));
    }

    public function testSymbolReplace()
    {
        $input = 'Cost {W}{U}{H}';
        $expected = 'Cost ';
        $expected .= '<img src="images/w.png" alt="{W}" class="manaimg">';
        $expected .= '<img src="images/u.png" alt="{U}" class="manaimg">';
        $expected .= 'Phyrexian mana ';
        $this->assertSame($expected, symbolreplace($input));
    }
}
