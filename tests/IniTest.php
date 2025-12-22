<?php

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
}
