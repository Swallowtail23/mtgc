<?php

use PHPUnit\Framework\TestCase;

class ScryfallManifestImportStructureTest extends TestCase
{
    public function testManifestImportKeepsHelpersInsideTheService(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/MTG/Bulk/ScryfallManifestImport.php');
        $this->assertNotFalse($source);
        $this->assertDoesNotMatchRegularExpression('/\$GLOBALS|\bglobal\s+\$/', $source);
        $this->assertMatchesRegularExpression('/private static function getManifestData/', $source);
        $this->assertMatchesRegularExpression('/private static function getManifestLanguages/', $source);
        $this->assertMatchesRegularExpression('/private static function manifestDateTime/', $source);
    }
}
