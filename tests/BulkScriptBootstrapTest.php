<?php

use PHPUnit\Framework\TestCase;

class BulkScriptBootstrapTest extends TestCase
{
    public function testBulkScriptsRequireLocalBulkIni()
    {
        $scripts = [
            'bulk/scryfall_bulk.php',
            'bulk/scryfall_rulings.php',
            'bulk/scryfall_sets.php',
            'bulk/scryfall_migrations.php',
            'bulk/weekly_exports.php',
            'bulk/collection_snapshots.php',
            'bulk/setimgreload.php',
        ];

        foreach ($scripts as $script) :
            $path = __DIR__ . '/../' . $script;
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertNotFalse($source);
            $this->assertMatchesRegularExpression(
                '/require\\s+__DIR__\\s*\\.\\s*[\'"]\\/bulk_ini\\.php[\'"]\\s*;/',
                $source
            );
            $this->assertDoesNotMatchRegularExpression(
                '/APP_ROOT\\s*\\.\\s*[\'"]\\/bulk\\/bulk_ini\\.php[\'"]/',
                $source
            );
        endforeach;
    }
}
