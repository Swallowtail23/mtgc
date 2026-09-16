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
            'bulk/scryfall_manifest.php',
            'bulk/scryfall_reset_data.php',
            'bulk/scryfall_sync_state.php',
            'bulk/weekly_exports.php',
            'bulk/collection_snapshots.php',
            'bulk/setimgreload.php',
            'bulk/image_webp_migrate.php',
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

    public function testImageWebpMigrationLoadsAutoloaderBeforeParsingOptions()
    {
        $path = __DIR__ . '/../bulk/image_webp_migrate.php';
        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        $autoloadPosition = strpos($source, "require_once dirname(__DIR__) . '/vendor/autoload.php';");
        $parsePosition = strpos($source, 'parseImageWebpMigrationOptions();');
        $this->assertNotFalse($autoloadPosition);
        $this->assertNotFalse($parsePosition);
        $this->assertLessThan($parsePosition, $autoloadPosition);
    }
}
