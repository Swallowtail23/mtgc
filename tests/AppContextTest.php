<?php

use MTG\Core\AppConfig;
use MTG\Core\AppContext;
use MTG\Core\GameRules;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

class AppContextTest extends TestCase
{
    public function testFromIniPathBuildsContextWithOverrides()
    {
        $iniPath = tempnam(sys_get_temp_dir(), 'mtg_ini_');
        if ($iniPath === false) :
            $this->fail('Failed to create temporary ini file');
        endif;

        $iniContents = <<<INI
[general]
URL = "https://example.test"
title = "Test Site"
tier = "dev"
Loglevel = 0
Logfile = "/tmp/mtg_test.log"
ImgLocation = "/cardimg/"
Timezone = "UTC"
Locale = "en_US"
Copyright = "Test"

[security]
Turnstile = "disabled"
Turnstile_site_key = ""
Turnstile_secret_key = ""
AdminIP = ""
Badloginlimit = 3
TrustDuration = 30

[email]
Email = "disabled"
AdminEmail = "admin@example.test"
ServerEmail = "server@example.test"
SMTPDebug = 0
Host = ""
SMTPAuth = false
Username = ""
Password = ""
SMTPSecure = ""
Port = 0

[fx]
FreecurrencyAPI = ""
TargetCurrency = "USD"

[comments]
Disqus = "disabled"
DisqusDevURL = ""
DisqusProdURL = ""

[database]
DBServer = "localhost"
DBUser = "user"
DBPass = "pass"
DBName = "db"
INI;

        file_put_contents($iniPath, $iniContents);

        $db = new DummyMysqli();
        $ctx = AppContext::fromIniPath($iniPath, $db);

        $this->assertInstanceOf(AppConfig::class, $ctx->config());
        $this->assertSame($db, $ctx->db());
        $this->assertInstanceOf(GameRules::class, $ctx->rules());
        $this->assertInstanceOf(Message::class, $ctx->message());
        $this->assertSame('https://example.test', $ctx->iniArray()['general']['URL']);
        $this->assertSame('dev', $ctx->config()->general('tier'));

        unlink($iniPath);
    }
}
