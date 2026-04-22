<?php

namespace Tests\Unit\Concerns;

use App\Http\Concerns\ValidatesExternalUrls;
use Tests\TestCase;

class ValidatesExternalUrlsTest extends TestCase
{
    use ValidatesExternalUrls;

    public function test_rejects_non_http_urls(): void
    {
        $this->assertFalse($this->isExternalUrl('ftp://example.com'));
        $this->assertFalse($this->isExternalUrl('file:///path/to/file'));
        $this->assertFalse($this->isExternalUrl('javascript:alert(1)'));
    }

    public function test_rejects_urls_without_protocol(): void
    {
        $this->assertFalse($this->isExternalUrl('example.com'));
        $this->assertFalse($this->isExternalUrl('//example.com'));
    }

    public function test_rejects_urls_with_empty_host(): void
    {
        $this->assertFalse($this->isExternalUrl('http://'));
        $this->assertFalse($this->isExternalUrl('https://'));
    }

    public function test_rejects_localhost(): void
    {
        $this->assertFalse($this->isExternalUrl('http://localhost'));
        $this->assertFalse($this->isExternalUrl('https://localhost/path'));
    }

    public function test_rejects_private_ip_127(): void
    {
        $this->assertFalse($this->isExternalUrl('http://127.0.0.1'));
        $this->assertFalse($this->isExternalUrl('http://127.0.0.1:8080'));
    }

    public function test_accepts_reserved_example_domains_without_dns(): void
    {
        $this->assertTrue($this->isExternalUrl('https://example.com/webhook'));
        $this->assertTrue($this->isExternalUrl('https://example.org/path'));
        $this->assertTrue($this->isExternalUrl('https://example.net/hook'));
    }

    public function test_rejects_unresolvable_host(): void
    {
        $this->assertFalse($this->isExternalUrl('http://this-host-definitely-does-not-exist.invalid'));
    }

    public function test_allows_reserved_example_hosts_without_dns(): void
    {
        $this->assertTrue($this->isExternalUrl('https://example.com/webhook'));
        $this->assertTrue($this->isExternalUrl('https://hooks.example.net/incoming'));
        $this->assertTrue($this->isExternalUrl('https://ops.example.org/parkhub'));
    }
}
