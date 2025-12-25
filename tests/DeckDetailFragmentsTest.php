<?php

use PHPUnit\Framework\TestCase;

class DeckDetailFragmentsTest extends TestCase
{
    public function testColourIdentityFragmentRendersForCommander()
    {
        $decktype = 'Commander';
        $commander_decktypes = ['Commander'];
        $i = 1;
        $cdr_colours = 'five';

        ob_start();
        include __DIR__ . '/../includes/fragments/deckdetail_colour_identity.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('deck-colour-identity-fragment', $html);
        $this->assertStringContainsString('Colour identity', $html);
    }

    public function testFragmentRendererAlwaysIncludesDecklist()
    {
        require_once __DIR__ . '/../ajax/ajaxdeckfragments_lib.php';

        $fragmentMap = [
            'decklist' => __DIR__ . '/stubs/fragments/decklist.php',
            'warnings' => __DIR__ . '/stubs/fragments/warnings.php'
        ];

        $fragments = deckdetailRenderFragments(['warnings'], $fragmentMap);

        $this->assertArrayHasKey('decklist', $fragments);
        $this->assertArrayHasKey('warnings', $fragments);
        $this->assertStringContainsString('stub-decklist', $fragments['decklist']);
        $this->assertStringContainsString('stub-warnings', $fragments['warnings']);
    }
}
