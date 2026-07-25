<?php

/*
Version:     1.0
Date:        25/07/26
Name:        IniTest.php
Purpose:     Tests INI configuration file read and write behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

function getRealIniClass(): string
{
    if (class_exists('IniReal', false)) :
        return 'IniReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Core/INI.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Core;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+INI\\b/', 'class IniReal', $source, 1);
    eval($source);
    return 'IniReal';
}

class IniTest extends TestCase
{
    public function testReadAndWriteIni()
    {
        $class = getRealIniClass();
        $source = tempnam(sys_get_temp_dir(), 'mtgini_');
        file_put_contents($source, "[general]\nname = \"test\"\ncount = 5\n");

        $ini = new $class($source);
        $this->assertSame('test', $ini->data['general']['name']);
        $this->assertSame('5', $ini->data['general']['count']);

        $output = tempnam(sys_get_temp_dir(), 'mtgini_out_');
        $ini->data['general']['name'] = 'updated';
        $ini->write($output);

        $this->assertFileExists($output);
        $written = file_get_contents($output);
        $this->assertStringContainsString('[general]', $written);
        $this->assertStringContainsString('name = "updated"', $written);
    }

    public function testWriteReportsAnUnwritableTargetWithoutRaisingWarning(): void
    {
        $class = getRealIniClass();
        $missingDirectory = sys_get_temp_dir() . '/mtgini_missing_' . uniqid();
        $ini = new $class();

        $this->assertFalse($ini->write($missingDirectory . '/mtg_new.ini', ['general' => ['name' => 'test']]));
        $this->assertSame('Configuration file is not writable.', $ini->getLastError());
    }
}
