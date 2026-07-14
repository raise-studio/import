<?php

namespace RaiseStudio\Import\Tests\Unit\Pro;

use Illuminate\Support\Facades\Cache;
use RaiseStudio\Import\License;
use RaiseStudio\Import\Tests\TestCase;
use RaiseStudio\License\FeatureGate;
use RaiseStudio\License\LicenseClient;

class LicenseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        License::flushCache();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        License::flushCache();
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Bind a real request with the given URL so that License resolves the
     * ACTUAL accessed host from it (mirrors production behavior).
     */
    private function setHost(string $url): void
    {
        $request = \Illuminate\Http\Request::create($url);
        $this->app->instance('request', $request);
    }

    /* ---------------------------------------------------------------------
     | Local loopback auto-exemption — decided by the REAL accessed domain
     |--------------------------------------------------------------------- */

    /** @test */
    public function it_returns_true_when_accessed_via_localhost()
    {
        $this->setHost('http://localhost');

        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_when_accessed_via_127()
    {
        $this->setHost('http://127.0.0.1:8000');

        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_when_accessed_via_ipv6_loopback()
    {
        $this->setHost('http://[::1]:8000');

        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_false_when_accessed_via_local_tld()
    {
        // .test / .local style dev domains are NOT exempt — they need a license
        $this->setHost('http://myapp.test');

        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_returns_false_when_accessed_via_private_ip()
    {
        $this->setHost('http://192.168.1.100');

        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_returns_false_when_accessed_via_real_domain_without_key()
    {
        $this->setHost('https://example.com');

        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Config values must NEVER decide (or bypass) authorization
     |--------------------------------------------------------------------- */

    /** @test */
    public function app_env_local_does_not_grant_pro_on_real_domain()
    {
        // Even with APP_ENV=local, a real domain must NOT be exempt.
        app()['env'] = 'local';
        config()->set('app.url', 'https://example.com');
        $this->setHost('https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function app_url_config_localhost_does_not_grant_pro_on_real_domain()
    {
        // Spoofing APP_URL=localhost while accessed via a real domain is ignored.
        config()->set('app.url', 'http://localhost');
        $this->setHost('https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function removed_force_community_config_no_longer_affects_result()
    {
        // The force_community config was removed; it must not grant/deny Pro.
        config()->set('raise-import.license.force_community', true);
        config()->set('raise-import.force_community', true);
        $this->setHost('https://example.com');

        License::flushCache();
        // Without a bound license the result is false regardless of the config.
        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | SDK delegation — mock LicenseClient / FeatureGate
     |--------------------------------------------------------------------- */

    /** @test */
    public function it_returns_true_when_feature_gate_grants_pro()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(true);
        $this->app->instance(FeatureGate::class, $gate);

        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_false_when_feature_gate_denies_pro()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(false);
        $this->app->instance(FeatureGate::class, $gate);

        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_sends_correct_params_to_client_when_checking_pro()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $client = $this->getMockBuilder(LicenseClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoredLicenseKey', 'getPayload', 'isPro'])
            ->getMock();
        $client->method('getStoredLicenseKey')->willReturn(null);
        $client->method('getPayload')->willReturn(null);
        $client->method('isPro')->willReturn(false);
        $this->app->instance(LicenseClient::class, $client);

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(false);
        $this->app->instance(FeatureGate::class, $gate);

        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Static cache behavior
     |--------------------------------------------------------------------- */

    /** @test */
    public function it_caches_result_and_does_not_revalidate()
    {
        $this->setHost('https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());

        // Changing the accessed host after caching should not affect result
        $this->setHost('http://localhost');
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function flush_cache_clears_result()
    {
        $this->setHost('https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());

        $this->setHost('http://localhost');
        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Integrity self-check
     |--------------------------------------------------------------------- */

    private function currentHashes(): array
    {
        $base = realpath(__DIR__ . '/../../..');
        $files = [
            'src/License.php',
            'src/Pro/Actions/ProImportAction.php',
            'src/RaiseImportServiceProvider.php',
        ];
        $hashes = [];
        foreach ($files as $rel) {
            $hashes[$rel] = hash_file('sha256', $base . '/' . $rel);
        }

        return $hashes;
    }

    private function currentVersion(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)
            && \Composer\InstalledVersions::isInstalled('raise-studio/raise-import')) {
            return (string) \Composer\InstalledVersions::getPrettyVersion('raise-studio/raise-import');
        }
        $composer = realpath(__DIR__ . '/../../..') . '/composer.json';
        if (is_file($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            if (!empty($data['version'])) {
                return (string) $data['version'];
            }
        }

        return 'unknown';
    }

    /** @test */
    public function integrity_passes_with_correct_hashes()
    {
        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_version', $this->currentVersion());
        config()->set('raise-import.license.integrity_hashes', $this->currentHashes());

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_fails_with_wrong_hash()
    {
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'deadbeef';

        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_version', $this->currentVersion());
        config()->set('raise-import.license.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertFalse(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_skips_when_disabled()
    {
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'deadbeef';

        config()->set('raise-import.license.integrity_disabled', true);
        config()->set('raise-import.license.integrity_version', $this->currentVersion());
        config()->set('raise-import.license.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_skips_when_no_hashes_configured()
    {
        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_hashes', []);

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_skips_on_version_mismatch()
    {
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'deadbeef';

        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_version', '9.9.9-mismatch');
        config()->set('raise-import.license.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_fails_when_gatekeeper_file_is_missing()
    {
        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_version', $this->currentVersion());
        config()->set('raise-import.license.integrity_hashes', [
            'src/License.php' => 'ok',
            'src/MissingFile.php' => 'ok',
        ]);

        License::flushCache();
        $this->assertFalse(License::isIntegrityValid());
    }

    /** @test */
    public function is_pro_degrades_to_community_when_integrity_fails()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        // Mock LicenseClient to return a valid payload (simulating valid license)
        // The real FeatureGate will check integrity and return false when broken
        $client = $this->getMockBuilder(LicenseClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayload'])
            ->getMock();
        $client->method('getPayload')->willReturn((object) [
            'features' => ['advanced_mapping', 'queue', 'import_log', 'merge_split', 'pipeline'],
        ]);
        $this->app->instance(LicenseClient::class, $client);

        // Use real FeatureGate (re-register so it picks up the mocked client)
        $gate = new FeatureGate($client);
        $gate->setFreeFeatures(config('raise-import.license.free_features', []));
        $gate->setAllProFeatures(config('raise-import.license.all_pro_features', []));
        $this->app->instance(FeatureGate::class, $gate);

        // Set broken integrity hashes
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'tampered';
        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_version', $this->currentVersion());
        config()->set('raise-import.license.integrity_hashes', $hashes);

        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function integrity_does_not_affect_is_pro_when_hashes_empty()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(true);
        $this->app->instance(FeatureGate::class, $gate);

        config()->set('raise-import.license.integrity_hashes', []);

        $this->assertTrue(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Distributed gate (gatePro) — independent re-validation
     |--------------------------------------------------------------------- */

    /** @test */
    public function gate_pro_returns_true_when_feature_gate_grants()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(true);
        $this->app->instance(FeatureGate::class, $gate);

        $this->assertTrue(License::gatePro());
    }

    /** @test */
    public function gate_pro_returns_false_when_integrity_broken()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(true);
        $this->app->instance(FeatureGate::class, $gate);

        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'tampered';
        config()->set('raise-import.license.integrity_disabled', false);
        config()->set('raise-import.license.integrity_version', $this->currentVersion());
        config()->set('raise-import.license.integrity_hashes', $hashes);

        $this->assertFalse(License::gatePro());
    }

    /** @test */
    public function gate_pro_revalidates_independently_of_ispro_cache()
    {
        $this->setHost('https://example.com');

        // isPro() caches false
        License::flushCache();
        $this->assertFalse(License::isPro());

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(true);
        $this->app->instance(FeatureGate::class, $gate);

        // isPro() still cached false
        $this->assertFalse(License::isPro());
        // gatePro() does fresh check → true
        $this->assertTrue(License::gatePro());
    }

    /** @test */
    public function gate_pro_returns_false_when_feature_gate_denies()
    {
        $this->setHost('https://example.com');

        License::flushCache();

        $gate = $this->getMockBuilder(FeatureGate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canUse'])
            ->getMock();
        $gate->method('canUse')->with('*')->willReturn(false);
        $this->app->instance(FeatureGate::class, $gate);

        $this->assertFalse(License::gatePro());
    }
}
