<?php

/*
Version:     1.0
Date:        28/04/26
Name:        ProfilePreferencesTest.php
Purpose:     Tests profile preference persistence helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Core\Message;
use MTG\Profile\ProfilePreferences;
use PHPUnit\Framework\TestCase;

class ProfilePreferencesDbStub
{
    public string $query = '';
    public array $params = [];
    public bool $result = true;
    public string $error = 'db-error';

    public function execute_query(string $query, array $params): bool
    {
        $this->query = $query;
        $this->params = $params;
        return $this->result;
    }
}

class ProfilePreferencesTest extends TestCase
{
    public function testUpdateCurrencyPersistsValidValue()
    {
        $db = new ProfilePreferencesDbStub();
        $msg = new Message($GLOBALS['appConfig']);
        $rulesCurrencies = [
            ['code' => 'AUD', 'db' => 'aud', 'pretty' => 'AUD']
        ];

        $result = ProfilePreferences::updateCurrency($db, $rulesCurrencies, 7, 'AUD', $msg);

        $this->assertSame('AUD', $result);
        $this->assertSame('UPDATE users SET currency = ? WHERE usernumber = ?', $db->query);
        $this->assertSame(['AUD', 7], $db->params);
    }

    public function testUpdateCurrencyNormalizesInvalidValueToNull()
    {
        $db = new ProfilePreferencesDbStub();
        $msg = new Message($GLOBALS['appConfig']);
        $rulesCurrencies = [
            ['code' => 'AUD', 'db' => 'aud', 'pretty' => 'AUD']
        ];

        $result = ProfilePreferences::updateCurrency($db, $rulesCurrencies, 9, 'zzz', $msg);

        $this->assertSame(null, $result);
        $this->assertSame([null, 9], $db->params);
    }

    public function testUpdateCurrencyThrowsOnDbFailure()
    {
        $db = new ProfilePreferencesDbStub();
        $db->result = false;
        $msg = new Message($GLOBALS['appConfig']);
        $rulesCurrencies = [
            ['code' => 'AUD', 'db' => 'aud', 'pretty' => 'AUD']
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('profile.php: Error');

        ProfilePreferences::updateCurrency($db, $rulesCurrencies, 2, 'AUD', $msg);
    }
}
