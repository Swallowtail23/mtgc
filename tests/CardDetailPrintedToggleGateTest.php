<?php

/*
Version:     1.0
Date:        26/02/26
Name:        CardDetailPrintedToggleGateTest.php
Purpose:     Guards carddetail printed/oracle header toggle gating behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CardDetailPrintedToggleGateTest extends TestCase
{
    private function getCardDetailSource(): string
    {
        return (string) file_get_contents(__DIR__ . '/../carddetail.php');
    }

    public function testPrintedFieldListIncludesCoreAndFaceVariants()
    {
        $source = $this->getCardDetailSource();
        $fields = [
            "'printed_name'",
            "'printed_type_line'",
            "'printed_text'",
            "'f1_printed_name'",
            "'f1_printed_type_line'",
            "'f1_printed_text'",
            "'f2_printed_name'",
            "'f2_printed_type_line'",
            "'f2_printed_text'",
        ];

        foreach ($fields as $field) :
            $this->assertStringContainsString($field, $source);
        endforeach;
    }

    public function testGlobalToggleUsesPrintedDataGate()
    {
        $source = $this->getCardDetailSource();

        $this->assertStringContainsString("id='global-card-text-toggle'", $source);
        $this->assertStringContainsString("data-has-printed='", $source);
        $this->assertMatchesRegularExpression(
            '/hasPrintedData\\s*\\|\\|\\s*toggleableSectionCount\\s*>\\s*0/',
            $source
        );
    }
}
