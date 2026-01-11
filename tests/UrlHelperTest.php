<?php

use MTG\Core\Http\UrlHelper;
use PHPUnit\Framework\TestCase;

class UrlHelperTest extends TestCase
{
    public function testNormalizeRedirectUrl()
    {
        $this->assertNull(UrlHelper::normalizeRedirectUrl('https://evil.test/path'));
        $this->assertNull(UrlHelper::normalizeRedirectUrl('http://example.test'));
        $this->assertNull(UrlHelper::normalizeRedirectUrl(''));
        $this->assertNull(UrlHelper::normalizeRedirectUrl('?no-path'));
        $this->assertNull(UrlHelper::normalizeRedirectUrl('#frag'));
        $this->assertNull(UrlHelper::normalizeRedirectUrl(''));
        $this->assertSame('/path', UrlHelper::normalizeRedirectUrl('path'));
        $this->assertSame('/path?x=1#frag', UrlHelper::normalizeRedirectUrl('/path?x=1#frag'));
    }

    public function testGetStringParameters()
    {
        $params = [
            'layout' => 'invalid',
            'page' => '2',
            'set' => ['abc', 'def'],
            'q' => 'search'
        ];
        $result = UrlHelper::getStringParameters($params, 'page', 'ignored');
        $this->assertSame('?layout=grid&set%5B0%5D=abc&set%5B1%5D=def&q=search', $result);
        $this->assertSame('', UrlHelper::getStringParameters([], 'page', 'ignored'));
    }

    public function testGetFullUrl()
    {
        $original = $_SERVER;
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_URI'] = '/index.php?x=1';
        $_SERVER['PATH_INFO'] = '';

        $this->assertSame('https://example.test/index.php?x=1', UrlHelper::getFullUrl());
        $_SERVER = $original;
    }
}
