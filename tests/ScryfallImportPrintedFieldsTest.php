<?php

/*
Version:     1.0
Date:        26/02/26
Name:        ScryfallImportPrintedFieldsTest.php
Purpose:     Verifies printed type/text fields are captured in Scryfall bulk import binds.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallImport;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class PrintedFieldsQueryStub
{
    public $num_rows = 1;

    public function free()
    {
    }
}

class PrintedFieldsInsertStmt
{
    public $affected_rows = 1;
    public $captured = [];
    private $refs = [];

    public function bind_param($types, &...$vars)
    {
        $this->refs = &$vars;
        return true;
    }

    public function execute()
    {
        $this->captured = $this->refs;
        return true;
    }

    public function close()
    {
    }
}

class PrintedFieldsHashStmt
{
    public $num_rows = 0;

    public function bind_param($types, &...$vars)
    {
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function store_result()
    {
        return true;
    }

    public function bind_result(&...$vars)
    {
        return true;
    }

    public function fetch()
    {
        return true;
    }

    public function free_result()
    {
    }

    public function close()
    {
    }
}

class PrintedFieldsDbStub
{
    public $error = '';
    private $prepareCount = 0;
    public $insertStmt;
    public $hashStmt;

    public function __construct(PrintedFieldsInsertStmt $insertStmt, PrintedFieldsHashStmt $hashStmt)
    {
        $this->insertStmt = $insertStmt;
        $this->hashStmt = $hashStmt;
    }

    public function query($sql)
    {
        return new PrintedFieldsQueryStub();
    }

    public function prepare($sql)
    {
        $this->prepareCount++;
        if ($this->prepareCount === 1) :
            return $this->insertStmt;
        endif;
        return $this->hashStmt;
    }

    public function begin_transaction()
    {
        return true;
    }

    public function commit()
    {
        return true;
    }

    public function rollback()
    {
        return true;
    }
}

class ScryfallImportPrintedFieldsTest extends TestCase
{
    private function buildAppConfig(): AppConfig
    {
        $ini = [
            'general' => [
                'URL' => '',
                'title' => '',
                'tier' => 'dev',
                'Loglevel' => '',
                'Logfile' => '',
                'ImgLocation' => '',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
                'MaxCardDataAge' => 0,
            ],
            'security' => [],
            'email' => [
                'Email' => 'enabled',
                'AdminEmail' => '',
                'ServerEmail' => '',
                'SMTPDebug' => '',
                'Host' => '',
                'SMTPAuth' => '',
                'Username' => '',
                'Password' => '',
                'SMTPSecure' => '',
                'Port' => 0,
                'SMTPVerifySSL' => 1,
            ],
            'fx' => [],
            'comments' => [],
        ];

        return AppConfig::fromIni($ini);
    }

    public function testImportBindsCoreAndFacePrintedTypeAndTextFields()
    {
        $card = [
            'id' => '11111111-1111-1111-1111-111111111111',
            'oracle_id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Test Walker // Back',
            'printed_name' => 'Nom Imprime',
            'lang' => 'ph',
            'released_at' => '2024-01-01',
            'uri' => 'https://api.scryfall.com/cards/test',
            'scryfall_uri' => 'https://scryfall.com/card/test',
            'layout' => 'transform',
            'image_uris' => ['normal' => 'https://img/front.jpg'],
            'mana_cost' => '{2}{U}',
            'cmc' => 3,
            'type_line' => 'Legendary Planeswalker — Test',
            'oracle_text' => '+1: Do a thing.',
            'printed_type_line' => 'Type Principal Imprime',
            'printed_text' => 'Texte Principal Imprime',
            'loyalty' => '4',
            'colors' => ['U'],
            'color_identity' => ['U'],
            'keywords' => [],
            'produced_mana' => [],
            'games' => ['paper'],
            'finishes' => ['nonfoil'],
            'promo_types' => [],
            'set_id' => 'setid-1',
            'set' => 'tst',
            'set_name' => 'Test Set',
            'collector_number' => '1',
            'rarity' => 'mythic',
            'artist' => 'Tester',
            'legalities' => [
                'standard' => 'not_legal',
                'pioneer' => 'legal',
                'modern' => 'legal',
                'legacy' => 'legal',
                'pauper' => 'not_legal',
                'vintage' => 'legal',
                'commander' => 'legal',
                'alchemy' => 'not_legal',
                'historic' => 'legal',
            ],
            'prices' => [
                'usd' => '1.23',
                'usd_foil' => null,
                'usd_etched' => null,
            ],
            'card_faces' => [
                [
                    'name' => 'Front Face',
                    'type_line' => 'Legendary Planeswalker — Front',
                    'printed_type_line' => 'Type Avant Imprime',
                    'oracle_text' => '-2: Do front thing.',
                    'printed_text' => 'Texte Avant Imprime',
                    'image_uris' => ['normal' => 'https://img/f1.jpg'],
                    'printed_name' => 'Nom Avant Imprime',
                ],
                [
                    'name' => 'Back Face',
                    'type_line' => 'Legendary Planeswalker — Back',
                    'printed_type_line' => 'Type Arriere Imprime',
                    'oracle_text' => '-10: Do back thing.',
                    'printed_text' => 'Texte Arriere Imprime',
                    'image_uris' => ['normal' => 'https://img/f2.jpg'],
                    'printed_name' => 'Nom Arriere Imprime',
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'scry_printed_');
        file_put_contents($tempFile, json_encode([$card]));

        $insertStmt = new PrintedFieldsInsertStmt();
        $hashStmt = new PrintedFieldsHashStmt();
        $db = new PrintedFieldsDbStub($insertStmt, $hashStmt);
        $appConfig = $this->buildAppConfig();
        $gameRules = new GameRules([
            'games_to_include' => ['paper'],
            'langs_to_skip' => [],
            'langs_to_skip_all' => [],
            'layouts_to_skip' => [],
        ]);

        $stats = [];
        ScryfallImport::scryfallImport($tempFile, 'default', 'cards_scry_test', $db, $appConfig, $gameRules, $stats);

        unlink($tempFile);

        $this->assertSame(1, $stats['added']);
        $this->assertContains('Type Principal Imprime', $insertStmt->captured);
        $this->assertContains('Texte Principal Imprime', $insertStmt->captured);
        $this->assertContains('Type Avant Imprime', $insertStmt->captured);
        $this->assertContains('Texte Avant Imprime', $insertStmt->captured);
        $this->assertContains('Type Arriere Imprime', $insertStmt->captured);
        $this->assertContains('Texte Arriere Imprime', $insertStmt->captured);
    }
}
