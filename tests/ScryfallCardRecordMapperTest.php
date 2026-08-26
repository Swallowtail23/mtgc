<?php

/*
Version:     1.1
Date:        26/08/26
Name:        ScryfallCardRecordMapperTest.php
Purpose:     Tests Scryfall card record normalisation before database import.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallCardRecordMapper;
use PHPUnit\Framework\TestCase;

class ScryfallCardRecordMapperTest extends TestCase
{
    public function testMapsCoreFieldsFacesPartsAndIllustrationIds(): void
    {
        $mapped = ScryfallCardRecordMapper::map($this->buildCard(), 123456);

        $this->assertSame('card-1', $mapped['id']);
        $this->assertSame('oracle-1', $mapped['oracle_id']);
        $this->assertSame('main-art', $mapped['illustration_id']);
        $this->assertSame('https://img/main.webp', $mapped['image_uri']);
        $this->assertSame('Face One', $mapped['name_1']);
        $this->assertSame('Face Two', $mapped['name_2']);
        $this->assertSame('face-one-art', $mapped['illustration_id_1']);
        $this->assertSame('face-two-art', $mapped['illustration_id_2']);
        $this->assertSame('Printed Face One', $mapped['printed_name_1']);
        $this->assertSame('Printed Face Two', $mapped['printed_name_2']);
        $this->assertSame('5', $mapped['toughness_2']);
        $this->assertSame('part-token', $mapped['id_p1']);
        $this->assertSame('token', $mapped['component_p1']);
        $this->assertSame('part-meld', $mapped['id_p2']);
        $this->assertNull($mapped['id_p3']);
        $this->assertSame(111, $mapped['multi_1']);
        $this->assertSame(222, $mapped['multi_2']);
        $this->assertSame(123456, $mapped['time']);
    }

    public function testFallsBackToNormalJpegWhenGridWebpIsUnavailable(): void
    {
        $card = $this->buildCard();
        unset($card['image_uris']['grid']);
        unset($card['card_faces'][0]['image_uris']['grid']);
        unset($card['card_faces'][1]['image_uris']['grid']);

        $mapped = ScryfallCardRecordMapper::map($card);

        $this->assertSame('https://img/main.jpg', $mapped['image_uri']);
        $this->assertSame('https://img/face-one.jpg', $mapped['image_1']);
        $this->assertSame('https://img/face-two.jpg', $mapped['image_2']);
    }

    public function testMapsJsonLegalitiesStatsAndCollectorNumber(): void
    {
        $mapped = ScryfallCardRecordMapper::map($this->buildCard());

        $this->assertSame('["U","B"]', $mapped['colors']);
        $this->assertSame('["paper","arena"]', $mapped['game_types']);
        $this->assertSame('["U"]', $mapped['produced_mana']);
        $this->assertSame('legal', $mapped['legality_modern']);
        $this->assertSame('not_legal', $mapped['legality_pauper']);
        $this->assertSame(5, $mapped['maxpower']);
        $this->assertSame(3, $mapped['minpower']);
        $this->assertSame(5, $mapped['maxtoughness']);
        $this->assertSame(4, $mapped['mintoughness']);
        $this->assertSame(7, $mapped['maxloyalty']);
        $this->assertSame(7, $mapped['minloyalty']);
        $this->assertSame(5012, $mapped['number_int']);
    }

    public function testPriceHashChangesIndependentlyFromContentHash(): void
    {
        $card = $this->buildCard();
        $mapped = ScryfallCardRecordMapper::map($card);

        $priceChangedCard = $card;
        $priceChangedCard['prices']['usd'] = '9.99';
        $priceChanged = ScryfallCardRecordMapper::map($priceChangedCard);

        $contentChangedCard = $card;
        $contentChangedCard['illustration_id'] = 'changed-art';
        $contentChanged = ScryfallCardRecordMapper::map($contentChangedCard);

        $this->assertSame('1.00', $mapped['price_sort']);
        $this->assertSame($mapped['content_hash'], $priceChanged['content_hash']);
        $this->assertNotSame($mapped['price_hash'], $priceChanged['price_hash']);
        $this->assertNotSame($mapped['content_hash'], $contentChanged['content_hash']);
    }

    public function testCollectorNumberSpecialCases(): void
    {
        $promo = ScryfallCardRecordMapper::map([
            'id' => 'promo',
            'collector_number' => '268p',
        ]);
        $meld = ScryfallCardRecordMapper::map([
            'id' => 'meld',
            'layout' => 'meld',
            'collector_number' => '113a',
        ]);

        $this->assertSame(268, $promo['number_int']);
        $this->assertSame(113, $meld['number_int']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCard(): array
    {
        return [
            'id' => 'card-1',
            'oracle_id' => 'oracle-1',
            'tcgplayer_id' => 123,
            'multiverse_ids' => [111, 222, 333],
            'name' => 'Mapped Test',
            'printed_name' => 'Printed Test',
            'flavor_name' => 'Flavor Test',
            'lang' => 'en',
            'released_at' => '2026-01-01',
            'uri' => 'https://api.example/card-1',
            'scryfall_uri' => 'https://scryfall.example/card-1',
            'layout' => 'transform',
            'image_uris' => [
                'grid' => 'https://img/main.webp',
                'normal' => 'https://img/main.jpg',
            ],
            'illustration_id' => 'main-art',
            'mana_cost' => '{3}{U}',
            'cmc' => 4,
            'type_line' => 'Creature',
            'oracle_text' => 'Do a thing.',
            'printed_type_line' => 'Printed Creature',
            'printed_text' => 'Printed text.',
            'power' => '5',
            'toughness' => '4',
            'loyalty' => '7',
            'colors' => ['U', 'B'],
            'color_identity' => ['U', 'B'],
            'keywords' => ['Flying'],
            'produced_mana' => ['U'],
            'legalities' => [
                'standard' => 'legal',
                'pioneer' => 'legal',
                'modern' => 'legal',
                'legacy' => 'legal',
                'pauper' => 'not_legal',
                'vintage' => 'legal',
                'commander' => 'legal',
                'alchemy' => 'not_legal',
                'historic' => 'legal',
            ],
            'reserved' => false,
            'foil' => true,
            'nonfoil' => true,
            'oversized' => false,
            'promo' => false,
            'set_id' => 'set-1',
            'games' => ['paper', 'arena'],
            'finishes' => ['nonfoil', 'foil'],
            'promo_types' => ['prerelease'],
            'set' => 'tst',
            'set_name' => 'Test Set',
            'collector_number' => '12s',
            'rarity' => 'rare',
            'flavor_text' => 'A test.',
            'card_back_id' => 'back-1',
            'artist' => 'Tester',
            'prices' => [
                'usd' => '1.00',
                'usd_foil' => '2.50',
                'usd_etched' => null,
            ],
            'related_uris' => [
                'gatherer' => 'https://gatherer.example/card-1',
            ],
            'card_faces' => [
                [
                    'name' => 'Face One',
                    'printed_name' => 'Printed Face One',
                    'flavor_name' => 'Flavor Face One',
                    'mana_cost' => '{1}{U}',
                    'power' => '3',
                    'toughness' => '4',
                    'type_line' => 'Creature Face',
                    'printed_type_line' => 'Printed Face Type',
                    'oracle_text' => 'Face text.',
                    'printed_text' => 'Printed face text.',
                    'colors' => ['U'],
                    'artist' => 'Face Artist',
                    'flavor_text' => 'Face flavor.',
                    'image_uris' => [
                        'grid' => 'https://img/face-one.webp',
                        'normal' => 'https://img/face-one.jpg',
                    ],
                    'illustration_id' => 'face-one-art',
                    'cmc' => 2,
                ],
                [
                    'name' => 'Face Two',
                    'printed_name' => 'Printed Face Two',
                    'defense' => '5',
                    'type_line' => 'Battle Face',
                    'oracle_text' => 'Back face text.',
                    'image_uris' => [
                        'grid' => 'https://img/face-two.webp',
                        'normal' => 'https://img/face-two.jpg',
                    ],
                    'illustration_id' => 'face-two-art',
                ],
                [
                    'name' => 'Ignored Face Three',
                ],
            ],
            'all_parts' => [
                [
                    'id' => 'combo-piece',
                    'component' => 'combo_piece',
                    'name' => 'Ignored Combo Piece',
                    'type_line' => 'Card',
                    'uri' => 'https://api.example/combo',
                ],
                [
                    'id' => 'part-token',
                    'component' => 'token',
                    'name' => 'Token Part',
                    'type_line' => 'Token',
                    'uri' => 'https://api.example/token',
                ],
                [
                    'id' => 'part-meld',
                    'component' => 'meld_part',
                    'name' => 'Meld Part',
                    'type_line' => 'Creature',
                    'uri' => 'https://api.example/meld',
                ],
            ],
        ];
    }
}
