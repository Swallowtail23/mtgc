<?php

/*
Version:     1.0
Date:        08/07/26
Name:        ScryfallCardImportStatementTest.php
Purpose:     Tests Scryfall card import SQL and bind field ordering.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallCardImportStatement;
use MTG\Bulk\ScryfallCardRecordMapper;
use PHPUnit\Framework\TestCase;

class ScryfallCardImportStatementTest extends TestCase
{
    public function testInsertColumnsAndBindFieldsStayAligned(): void
    {
        $columns = ScryfallCardImportStatement::insertColumns();
        $fields = ScryfallCardImportStatement::bindFields();

        $this->assertCount(count($columns) + 1, $fields);
        $this->assertSame('id', $columns[0]);
        $this->assertSame('id', $fields[0]);
        $this->assertSame('primary_card', $columns[array_key_last($columns)]);
        $this->assertSame('primary_card', $fields[count($columns) - 1]);
        $this->assertSame('primary_card_update', $fields[array_key_last($fields)]);
        $this->assertSame(str_repeat('s', count($fields) - 2) . 'ii', ScryfallCardImportStatement::bindTypes());
    }

    public function testMappedCardProvidesEveryCardFieldRequiredForBinding(): void
    {
        $mapped = ScryfallCardRecordMapper::map([
            'id' => 'card-1',
            'collector_number' => '1',
        ]);
        $missing = [];
        foreach (ScryfallCardImportStatement::bindFields() as $field) :
            if (in_array($field, ['date_added', 'primary_card', 'primary_card_update'], true)) :
                continue;
            endif;
            if (!array_key_exists($field, $mapped)) :
                $missing[] = $field;
            endif;
        endforeach;

        $this->assertSame([], $missing);
    }

    public function testGeneratedSqlContainsExpectedCardFieldsAndHashLookup(): void
    {
        $insertSql = ScryfallCardImportStatement::insertSql('cards_scry_test');
        $hashSql = ScryfallCardImportStatement::hashLookupSql('cards_scry_test');

        $this->assertStringContainsString('INSERT INTO', $insertSql);
        $this->assertStringContainsString('`cards_scry_test`', $insertSql);
        $this->assertStringContainsString('illustration_id', $insertSql);
        $this->assertStringContainsString('f1_illustration_id', $insertSql);
        $this->assertStringContainsString('f2_illustration_id', $insertSql);
        $this->assertStringContainsString('printed_type_line', $insertSql);
        $this->assertStringContainsString('f1_printed_text', $insertSql);
        $this->assertStringContainsString('price_sort = IF(NOT (price_hash <=> VALUES(price_hash))', $insertSql);
        $this->assertStringContainsString('primary_card = IF(?, 1, primary_card)', $insertSql);
        $this->assertSame(
            count(ScryfallCardImportStatement::bindFields()),
            substr_count($insertSql, '?')
        );
        $this->assertSame(
            'SELECT content_hash, price_hash FROM `cards_scry_test` WHERE id = ? LIMIT 1',
            $hashSql
        );
    }

    public function testInitialBindValuesAndMappedCardApplicationUseStableOrder(): void
    {
        $bindValues = ScryfallCardImportStatement::initialBindValues('2026-07-08', 1);
        ScryfallCardImportStatement::applyMappedCard($bindValues, [
            'id' => 'card-1',
            'oracle_id' => 'oracle-1',
            'price_sort' => '1.00',
            'content_hash' => 'content-hash',
            'price_hash' => 'price-hash',
        ]);
        $ordered = ScryfallCardImportStatement::orderedBindValues($bindValues);

        $this->assertSame('card-1', $ordered[0]);
        $this->assertSame('oracle-1', $ordered[1]);
        $this->assertSame('2026-07-08', $ordered[count($ordered) - 3]);
        $this->assertSame(1, $ordered[count($ordered) - 2]);
        $this->assertSame(1, $ordered[count($ordered) - 1]);
        $this->assertContains('content-hash', $ordered);
        $this->assertContains('price-hash', $ordered);
    }
}
