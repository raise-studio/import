<?php

namespace RaiseStudio\Import\Tests\Unit\Pro;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Tests\TestCase;

class LicenseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        License::flushCache();
        Cache::flush();

        // Default config for online tests
        config()->set('raise-import.license_verify_url', 'https://license.test/api/license/verify');
    }

    protected function tearDown(): void
    {
        License::flushCache();
        Cache::flush();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_true_in_local_environment()
    {
        app()['env'] = 'local';
        config()->set('app.url', 'http://localhost');

        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_for_localhost_domain()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://localhost');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_for_127_dot_0_dot_0_dot_1_domain()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://127.0.0.1:8000');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_for_local_tld()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://myapp.test');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_for_private_ip()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://192.168.1.100');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_false_in_production_without_key()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_returns_false_when_server_rejects_key()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'some-license-key');

        Http::fake([
            'license.test/*' => Http::response(['valid' => false, 'reason' => 'invalid'], 403),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_sends_license_key_and_domain_to_verify_server()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'my-license-key');

        Http::fake([
            'license.test/*' => Http::response(['valid' => true]),
        ]);

        License::flushCache();
        $this->assertTrue(License::isPro());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://license.test/api/license/verify'
                && $request['license_key'] === 'my-license-key'
                && $request['domain'] === 'example.com';
        });
    }

    /** @test */
    public function it_returns_true_when_server_validates_key()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'valid-license-key');

        Http::fake([
            'license.test/*' => Http::response(['valid' => true]),
        ]);

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_uses_cached_validation_when_server_is_unreachable()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'cached-key');

        // First call: server is up → cache the result
        Http::fake([
            'license.test/*' => Http::response(['valid' => true]),
        ]);

        License::flushCache();
        $this->assertTrue(License::isPro());

        // Second call: server is down → should use cache
        License::flushCache();
        Http::fake([
            'license.test/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_false_when_server_is_unreachable_and_no_cache_exists()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'some-key');

        Http::fake([
            'license.test/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_respects_force_community_config()
    {
        app()['env'] = 'local';
        config()->set('raise-import.force_community', true);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_caches_result_and_does_not_revalidate()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertFalse(License::isPro());

        // Changing config after cache should not affect result
        config()->set('raise-import.license_key', 'something-else');
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function flush_cache_clears_result()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertFalse(License::isPro());

        app()['env'] = 'local';
        License::flushCache();
        $this->assertTrue(License::isPro());
    }
}
