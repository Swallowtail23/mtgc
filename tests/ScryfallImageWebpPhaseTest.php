<?php

/*
Version:     1.0
Date:        26/08/26
Name:        ScryfallImageWebpPhaseTest.php
Purpose:     Tests phase-one WebP trigger, cache, and deployment configuration.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class ScryfallImageWebpPhaseTest extends TestCase
{
    public function testAsyncImageCheckUsesMissingOnlyResolutionRatherThanForcedRefresh(): void
    {
        $source = $this->readProjectFile('ajax/ajaximagecheck.php');

        $this->assertStringContainsString('checkAndRefreshImage($cardUUID)', $source);
        $this->assertStringNotContainsString('refreshImage($cardUUID)', $source);
        $this->assertStringNotContainsString("\$_POST['refresh']", $source);
    }

    public function testBulkImportDownloadsImagesOnlyForInsertedCards(): void
    {
        $source = $this->readProjectFile('src/MTG/Bulk/ScryfallCardImportRunner.php');
        $insertStart = strpos($source, 'if ($status === 1) :');
        $updateStart = strpos($source, 'elseif ($status === 2) :');

        $this->assertNotFalse($insertStart);
        $this->assertNotFalse($updateStart);
        $this->assertGreaterThan($insertStart, $updateStart);

        $insertBlock = substr($source, $insertStart, $updateStart - $insertStart);
        $remainingStatusBlocks = substr($source, $updateStart);
        $this->assertStringContainsString('$imageManager->getImage(', $insertBlock);
        $this->assertStringNotContainsString('$imageManager->getImage(', $remainingStatusBlocks);
    }

    public function testBrowserImageCacheUsesWebpPhaseNamespace(): void
    {
        foreach (['service-worker.js', 'index.php', 'carddetail.php', 'deckdetail.php'] as $path) {
            $this->assertStringContainsString('mtg-images-webp1-', $this->readProjectFile($path), $path);
        }

        $asyncSource = $this->readProjectFile('js/asyncImageRefresh.js');
        $this->assertStringContainsString('mtg-images-webp1-v1', $asyncSource);
        $this->assertStringContainsString('(?:webp|jpe?g)', $asyncSource);
        $this->assertStringContainsString(
            "data-front-fallback-src='\$expectedFrontFallback'",
            $this->readProjectFile('index.php')
        );
        $this->assertStringContainsString("\$img.attr('data-front-fallback-src')", $asyncSource);
        $this->assertStringContainsString("'.card-info-refresh'", $asyncSource);
        $this->assertStringContainsString(
            "class='material-symbols-outlined card-info-refresh'",
            $this->readProjectFile('index.php')
        );
    }

    public function testApacheConfigurationsServeAndCacheWebpWithoutCompression(): void
    {
        foreach (['docker/mtgc_ctr.conf', 'setup/mtgc.conf'] as $path) {
            $source = $this->readProjectFile($path);
            $this->assertStringContainsString('AddType image/webp .webp', $source, $path);
            $this->assertStringContainsString('ExpiresByType image/webp', $source, $path);
            $this->assertMatchesRegularExpression('/Request_URI .*webp.* no-gzip/', $source, $path);
        }
    }

    public function testDockerGdBuildIncludesWebpSupport(): void
    {
        $source = $this->readProjectFile('docker/Dockerfile');

        $this->assertStringContainsString('libwebp-dev', $source);
        $this->assertStringContainsString('--with-webp', $source);
    }

    public function testBareMetalUpgradeDocumentationCoversRequiredDeploymentSteps(): void
    {
        $source = $this->readProjectFile('INSTALL.md');

        $this->assertStringContainsString('## Existing bare-metal WebP upgrade', $source);
        $this->assertStringContainsString('$info["WebP Support"]', $source);
        $this->assertStringContainsString('AddType image/webp .webp', $source);
        $this->assertStringContainsString("source_type IN ('all_cards', 'default_cards')", $source);
        $this->assertStringContainsString('php bulk/scryfall_bulk.php refresh', $source);
        $this->assertStringContainsString('Do **not** run `setup/data_updates.sh refresh --confirm`', $source);
        $this->assertStringContainsString('Keep the existing `.jpg` files', $source);
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(__DIR__ . '/../' . $path);
        $this->assertNotFalse($contents, $path);
        return (string) $contents;
    }
}
