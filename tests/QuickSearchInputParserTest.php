<?php

/*
Version:     1.0
Date:        27/03/26
Name:        QuickSearchInputParserTest.php
Purpose:     Regression tests for quick-search input parsing.
Notes:       -
Author:      Codex
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\Text\QuickSearchInputParser;
use PHPUnit\Framework\TestCase;

class QuickSearchInputParserTest extends TestCase
{
    public function testParsePlainInputReturnsDefaultSearchParts()
    {
        $result = QuickSearchInputParser::parse('bal', []);

        $this->assertSame('bal', $result['typed']);
        $this->assertSame('%bal%', $result['search_string']);
        $this->assertSame('', $result['setcode']);
        $this->assertSame('', $result['number']);
    }

    public function testParseBracketedSetcodeAndNumber()
    {
        $result = QuickSearchInputParser::parse('Balance (LEA 5)', []);

        $this->assertSame('Balance', $result['typed']);
        $this->assertSame('%Balance%', $result['search_string']);
        $this->assertSame('LEA', $result['setcode']);
        $this->assertSame('5', $result['number']);
    }

    public function testParseBracketedSetcodeWithTrailingCollectorNumber()
    {
        $result = QuickSearchInputParser::parse('Balance (LEA) 5', []);

        $this->assertSame('Balance', $result['typed']);
        $this->assertSame('%Balance%', $result['search_string']);
        $this->assertSame('LEA', $result['setcode']);
        $this->assertSame('5', $result['number']);
    }

    public function testParseBracketedNameExceptionResetsSetAndNumber()
    {
        $result = QuickSearchInputParser::parse('Name (abc)', ['abc']);

        $this->assertSame('Name (abc)', $result['typed']);
        $this->assertSame('Name (abc)', $result['search_string']);
        $this->assertSame('', $result['setcode']);
        $this->assertSame('', $result['number']);
    }
}
