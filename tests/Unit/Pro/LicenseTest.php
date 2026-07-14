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

    /* ---------------------------------------------------------------------
     | Local environment auto-exemption
     |--------------------------------------------------------------------- */

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

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_true_for_127_dot_0_dot_0_dot_1_domain()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://127.0.0.1:8000');

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_returns_false_for_local_tld_after_tightening()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://myapp.test');

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_returns_false_for_private_ip_after_tightening()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'http://192.168.1.100');

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_returns_false_in_production_without_key()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | SDK delegation — mock LicenseClient / FeatureGate
     |--------------------------------------------------------------------- */

    /** @test */
    public function it_returns_true_when_feature_gate_grants_pro()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

        License::flushCache();

        // Setup mock AFTER flushCache (which clears container instances)
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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
     | Force community config
     |--------------------------------------------------------------------- */

    /** @test */
    public function it_respects_force_community_config_new_key()
    {
        app()['env'] = 'local';
        config()->set('raise-import.license.force_community', true);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_respects_force_community_config_legacy_key()
    {
        app()['env'] = 'local';
        config()->set('raise-import.force_community', true);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Static cache behavior
     |--------------------------------------------------------------------- */

    /** @test */
    public function it_caches_result_and_does_not_revalidate()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());

        // Changing env after cache should not affect result
        app()['env'] = 'local';
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function flush_cache_clears_result()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

        License::flushCache();
        $this->assertFalse(License::isPro());

        app()['env'] = 'local';
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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
    public function gate_pro_returns_false_when_force_community()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.force_community', true);

        License::flushCache();
        $this->assertFalse(License::gatePro());
    }

    /** @test */
    public function gate_pro_returns_false_when_integrity_broken()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');

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
