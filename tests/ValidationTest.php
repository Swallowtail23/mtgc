<?php

use MTG\Core\Validation;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testValidUuidAcceptsAndRejects()
    {
        $valid = '550e8400-e29b-41d4-a716-446655440000';
        $invalid = 'not-a-uuid';

        $this->assertSame($valid, Validation::validUUID($valid));
        $this->assertFalse(Validation::validUUID($invalid));
    }

    public function testValidTableName()
    {
        $this->assertSame('123collection', Validation::validTableName('123collection'));
        $this->assertFalse(Validation::validTableName('collection123'));
    }

    public function testSetCardLanguageValidation()
    {
        $this->assertTrue(Validation::isValidSetcode('abc1'));
        $this->assertTrue(Validation::isValidSetcode(''));
        $this->assertFalse(Validation::isValidSetcode('ab'));

        $this->assertTrue(Validation::isValidCardName('Shock'));
        $this->assertTrue(Validation::isValidCardName(''));
        $this->assertFalse(Validation::isValidCardName('12345'));

        $this->assertTrue(Validation::isValidLanguageCode('en'));
        $this->assertTrue(Validation::isValidLanguageCode(''));
        $this->assertFalse(Validation::isValidLanguageCode('en-GB'));
    }

    public function testCaseInsensitiveArrayMatch()
    {
        $this->assertTrue(Validation::inArrayCaseInsensitive('Test', ['foo', 'test']));
        $this->assertFalse(Validation::inArrayCaseInsensitive('Nope', ['foo', 'bar']));
        $this->assertFalse(Validation::inArrayCaseInsensitive('Test', 'not-array'));
    }

    public function testValidateTrueDecimal()
    {
        $this->assertTrue(Validation::validateTrueDecimal(1.5));
        $this->assertFalse(Validation::validateTrueDecimal(2));
        $this->assertFalse(Validation::validateTrueDecimal('3'));
    }
}
