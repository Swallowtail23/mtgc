<?php
use PHPUnit\Framework\TestCase;

class IndexRedirectTest extends TestCase
{
    private static $pid;
    private const PORT = 8001;

    public static function setUpBeforeClass(): void
    {
        if (!defined('PHP_BINARY') || !is_executable(PHP_BINARY)) {
            self::markTestSkipped('PHP binary not available');
        }

        // Ensure config file exists for includes/ini.php
        if (!is_dir('/opt/mtg')) {
            @mkdir('/opt/mtg', 0777, true);
        }
        $ini = <<<INI
[general]
title = "MTG Test"
tier = "prod"
ImgLocation = "/tmp"
Logfile = "/tmp/mtgapp.log"
Loglevel = 2
Timezone = "UTC"
Locale = "en_US"
Copyright = ""
URL = "http://localhost"

[database]
DBServer    = "localhost"
DBUser      = ""
DBPass      = ""
DBName      = "test"

[security]
AdminIP              = ""
Badloginlimit        = 5
Turnstile            = "disabled"
Turnstile_site_key   = ""
Turnstile_secret_key = ""
TrustDuration        = 7

[fx]
FreecurrencyAPI = ""
FreecurrencyURL = ""
TargetCurrency  = "usd"

[email]
ServerEmail    = "noreply@example.com"
AdminEmail     = "admin@example.com"
SMTPDebug      = 0
Host           = 'localhost'
SMTPAuth       = false
Username       = ''
Password       = ''
SMTPSecure     = ''
Port           = 25

[comments]
Disqus         = "disabled"
DisqusDevURL   = ""
DisqusProdURL  = ""
INI;
        file_put_contents('/opt/mtg/mtg_new.ini', $ini);

        $docroot = realpath(__DIR__ . '/..');
        $cmd = sprintf('%s -S 127.0.0.1:%d -t %s >/dev/null 2>&1 & echo $!', PHP_BINARY, self::PORT, $docroot);
        self::$pid = (int)shell_exec($cmd);
        sleep(1); // wait for server
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pid) {
            exec('kill ' . self::$pid);
        }
    }

    public function testRootRedirectsToLogin(): void
    {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'max_redirects' => 0,
            ]
        ]);
        @file_get_contents('http://127.0.0.1:' . self::PORT . '/', false, $context);
        global $http_response_header;
        $this->assertNotEmpty($http_response_header);
        $this->assertStringContainsString('302', $http_response_header[0]);
        $location = null;
        foreach ($http_response_header as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }
        $this->assertSame('/login.php', $location);
    }

    public function testLoginPageLoads(): void
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $data = file_get_contents('http://127.0.0.1:' . self::PORT . '/login.php', false, $context);
        $this->assertStringContainsString('loginfield', $data);
    }
}
?>
