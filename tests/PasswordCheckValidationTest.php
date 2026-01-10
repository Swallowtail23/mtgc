<?php

use PHPUnit\Framework\TestCase;

function getRealPasswordCheckValidationClass(): string
{
    if (class_exists('PasswordCheckReal', false)) :
        return 'PasswordCheckReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Auth/PasswordCheck.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Auth;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+PasswordCheck\\b/', 'class PasswordCheckReal', $source, 1);
    eval($source);
    return 'PasswordCheckReal';
}

class PasswordCheckValidationTest extends TestCase
{
    public function testValidPassAcceptsStrongPassword()
    {
        $class = getRealPasswordCheckValidationClass();
        $this->assertTrue($class::validPass('ValidPass1'));
    }

    public function testValidPassRejectsMissingUppercase()
    {
        $class = getRealPasswordCheckValidationClass();
        $this->assertFalse($class::validPass('validpass1'));
    }

    public function testValidPassRejectsMissingLowercase()
    {
        $class = getRealPasswordCheckValidationClass();
        $this->assertFalse($class::validPass('INVALIDPASS1'));
    }

    public function testValidPassRejectsMissingDigit()
    {
        $class = getRealPasswordCheckValidationClass();
        $this->assertFalse($class::validPass('ValidPass'));
    }

    public function testValidPassRejectsWhitespace()
    {
        $class = getRealPasswordCheckValidationClass();
        $this->assertFalse($class::validPass('Valid Pass1'));
    }

    public function testValidPassRejectsShortPassword()
    {
        $class = getRealPasswordCheckValidationClass();
        $this->assertFalse($class::validPass('Abc1def'));
    }
}
