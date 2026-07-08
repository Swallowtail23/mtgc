<?php

/*
Version:     1.3
Date:        08/07/26
Name:        CriteriaTagSearchTest.php
Purpose:     Tests advanced-search Scryfall tag criteria generation.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

class CriteriaTagSearchTest extends TestCase
{
    private array $originalGet = [];
    private string $originalPhpSelf = '';

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalPhpSelf = (string) ($_SERVER['PHP_SELF'] ?? '');
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SERVER['PHP_SELF'] = $this->originalPhpSelf;
    }

    public function testOracleTagSearchCanStandAloneWithSearchText(): void
    {
        $result = $this->buildCriteria([
            'name' => 'dragon',
            'searchoracletag' => 'yes',
        ]);

        $this->assertSame('true', $result['validsearch']);
        $this->assertStringContainsString('sta.tag_type = ?', $result['criteria']);
        $this->assertStringContainsString('cards_scry.oracle_id IN', $result['criteria']);
        $this->assertStringContainsString('SELECT sta.subject_id', $result['criteria']);
        $this->assertStringContainsString('std.label REGEXP ?', $result['criteria']);
        $this->assertStringContainsString('std.slug REGEXP ?', $result['criteria']);
        $this->assertStringNotContainsString('cards_scry.illustration_id', $result['criteria']);
        $this->assertSame(
            ['oracle', 'oracle', '(^|[^[:alnum:]])dragon([^[:alnum:]]|$)', '(^|-)dragon(-|$)'],
            $result['params']
        );
    }

    public function testImageTagSearchCanStandAloneWithSearchText(): void
    {
        $result = $this->buildCriteria([
            'name' => 'moon',
            'searchimagetag' => 'yes',
        ]);

        $this->assertSame('true', $result['validsearch']);
        $this->assertStringContainsString('sta.tag_type = ?', $result['criteria']);
        $this->assertStringContainsString('cards_scry.illustration_id', $result['criteria']);
        $this->assertStringContainsString('cards_scry.f1_illustration_id', $result['criteria']);
        $this->assertStringContainsString('cards_scry.f2_illustration_id', $result['criteria']);
        $this->assertSame(
            [
                'art',
                'art',
                '(^|[^[:alnum:]])moon([^[:alnum:]]|$)',
                '(^|-)moon(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])moon([^[:alnum:]]|$)',
                '(^|-)moon(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])moon([^[:alnum:]]|$)',
                '(^|-)moon(-|$)',
            ],
            $result['params']
        );
    }

    public function testNameAndOracleTagSearchNarrowsWithAnd(): void
    {
        $result = $this->buildCriteria([
            'name' => 'dragon',
            'searchname' => 'yes',
            'searchoracletag' => 'yes',
        ]);

        $this->assertSame('true', $result['validsearch']);
        $this->assertStringContainsString('cards_scry.name LIKE ?', $result['criteria']);
        $this->assertStringContainsString(') AND (cards_scry.oracle_id IN', $result['criteria']);
        $this->assertCount(13, $result['params']);
        $this->assertSame('%dragon%', $result['params'][0]);
        $this->assertSame('oracle', $result['params'][9]);
        $this->assertSame('oracle', $result['params'][10]);
        $this->assertSame('(^|[^[:alnum:]])dragon([^[:alnum:]]|$)', $result['params'][11]);
        $this->assertSame('(^|-)dragon(-|$)', $result['params'][12]);
    }

    public function testTagSearchWithoutTextIsRejected(): void
    {
        $result = $this->buildCriteria([
            'name' => '',
            'searchoracletag' => 'yes',
        ]);

        $this->assertSame('false', $result['validsearch']);
        $this->assertSame('', $result['query']);
    }

    public function testOracleAndImageTagSearchTogetherIsRejected(): void
    {
        $result = $this->buildCriteria([
            'name' => 'dragon',
            'searchoracletag' => 'yes',
            'searchimagetag' => 'yes',
        ]);

        $this->assertSame('false', $result['validsearch']);
        $this->assertSame('', $result['query']);
    }

    public function testTagSearchCanAppendSetCriteria(): void
    {
        $result = $this->buildCriteria([
            'name' => 'dragon',
            'searchimagetag' => 'yes',
            'selectedSets' => ['fdn'],
        ]);

        $this->assertSame('true', $result['validsearch']);
        $this->assertStringContainsString('sta.tag_type = ?', $result['criteria']);
        $this->assertStringContainsString('cards_scry.setcode LIKE ?', $result['criteria']);
        $this->assertSame(
            [
                'art',
                'art',
                '(^|[^[:alnum:]])dragon([^[:alnum:]]|$)',
                '(^|-)dragon(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])dragon([^[:alnum:]]|$)',
                '(^|-)dragon(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])dragon([^[:alnum:]]|$)',
                '(^|-)dragon(-|$)',
                'fdn',
            ],
            $result['params']
        );
    }

    public function testImageTagSearchSplitsMultipleTermsAndRequiresEveryTerm(): void
    {
        $result = $this->buildCriteria([
            'name' => 'marvel glasses',
            'searchimagetag' => 'yes',
        ]);

        $this->assertSame('true', $result['validsearch']);
        $this->assertStringContainsString(
            'cards_scry.illustration_id IN',
            $result['criteria']
        );
        $this->assertStringContainsString(' AND cards_scry.illustration_id IN', $result['criteria']);
        $this->assertStringContainsString(' OR (cards_scry.f1_illustration_id IN', $result['criteria']);
        $this->assertSame(
            [
                'art',
                'art',
                '(^|[^[:alnum:]])marvel([^[:alnum:]]|$)',
                '(^|-)marvel(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])glasses([^[:alnum:]]|$)',
                '(^|-)glasses(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])marvel([^[:alnum:]]|$)',
                '(^|-)marvel(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])glasses([^[:alnum:]]|$)',
                '(^|-)glasses(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])marvel([^[:alnum:]]|$)',
                '(^|-)marvel(-|$)',
                'art',
                'art',
                '(^|[^[:alnum:]])glasses([^[:alnum:]]|$)',
                '(^|-)glasses(-|$)',
            ],
            $result['params']
        );
    }

    public function testTagTermsDoNotUseSubstringLikeMatching(): void
    {
        $result = $this->buildCriteria([
            'name' => 'ice',
            'searchimagetag' => 'yes',
        ]);

        $this->assertSame('true', $result['validsearch']);
        $this->assertStringNotContainsString('LIKE ?', $result['criteria']);
        $this->assertStringContainsString('REGEXP ?', $result['criteria']);
        $this->assertNotContains('%ice%', $result['params']);
        $this->assertContains('(^|[^[:alnum:]])ice([^[:alnum:]]|$)', $result['params']);
        $this->assertContains('(^|-)ice(-|$)', $result['params']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{validsearch: string, criteria: string, query: string, params: array<int, mixed>}
     */
    private function buildCriteria(array $overrides): array
    {
        $_GET = ['complex' => 'yes'];
        $_SERVER['PHP_SELF'] = '/index.php';

        $adv = 'yes';
        $sortBy = 'auto';
        $selectAll = 'SELECT * FROM cards_scry WHERE ';
        $sorting = '';
        $mytable = 'collection_test';
        $name = (string) ($overrides['name'] ?? '');
        $searchname = (string) ($overrides['searchname'] ?? '');
        $searchoracletag = (string) ($overrides['searchoracletag'] ?? '');
        $searchimagetag = (string) ($overrides['searchimagetag'] ?? '');
        $selectedSets = $overrides['selectedSets'] ?? [];

        require APP_ROOT . '/includes/criteria.php';

        return [
            'validsearch' => $validsearch,
            'criteria' => $criteria,
            'query' => $query,
            'params' => $params,
        ];
    }
}
