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
        config()->set('raise-import.license_secret', 'test-shared-secret');
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
    public function it_returns_false_for_local_tld_after_tightening()
    {
        // .test TLD is no longer exempt — only loopback hosts are.
        app()['env'] = 'production';
        config()->set('app.url', 'http://myapp.test');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_returns_false_for_private_ip_after_tightening()
    {
        // Private RFC1918 ranges are no longer exempt — only loopback hosts are.
        app()['env'] = 'production';
        config()->set('app.url', 'http://192.168.1.100');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertFalse(License::isPro());
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
    public function it_sends_license_key_site_url_and_product_to_verify_server()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'my-license-key');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertTrue(License::isPro());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://license.test/api/license/verify'
                && $request['license_key'] === 'my-license-key'
                && $request['site_url'] === 'https://example.com'
                && $request['product'] === 'raise-import';
        });
    }

    /** @test */
    public function it_returns_true_when_server_validates_key()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'valid-license-key');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
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
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
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

    private function signedResponse(array $data, string $secret = 'test-shared-secret'): array
    {
        $canonical = sprintf(
            '%s|%s|%s|%s',
            var_export($data['valid'] ?? false, true),
            $data['domain'] ?? '',
            $data['expires_at'] ?? '',
            $data['edition'] ?? ''
        );
        $data['signature'] = hash_hmac('sha256', $canonical, $secret);

        return $data;
    }

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
    public function it_rejects_response_without_signature()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'k');

        Http::fake([
            'license.test/*' => Http::response([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ]),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_rejects_response_with_wrong_signature()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'k');

        $signed = $this->signedResponse([
            'valid' => true,
            'domain' => 'example.com',
            'expires_at' => time() + 86400,
            'edition' => 'pro',
        ]);
        $signed['signature'] = 'forged-signature';

        Http::fake([
            'license.test/*' => Http::response($signed),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_rejects_when_domain_does_not_match()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'k');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'other-site.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_rejects_when_edition_is_not_pro()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'k');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'community',
            ])),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_accepts_wildcard_subdomain_binding()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://app.example.com');
        config()->set('raise-import.license_key', 'k');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => '*.example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertTrue(License::isPro());
    }

    /** @test */
    public function it_rejects_bare_domain_for_wildcard_binding()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'k');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => '*.example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /** @test */
    public function it_rejects_when_no_shared_secret_is_configured()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'k');
        config()->set('raise-import.license_secret', '');

        // Even a perfectly signed response cannot be trusted without the secret
        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Integrity self-check
     |--------------------------------------------------------------------- */

    /** @test */
    public function integrity_passes_with_correct_hashes()
    {
        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_version', $this->currentVersion());
        config()->set('raise-import.integrity_hashes', $this->currentHashes());

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_fails_with_wrong_hash()
    {
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'deadbeef';

        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_version', $this->currentVersion());
        config()->set('raise-import.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertFalse(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_skips_when_disabled()
    {
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'deadbeef'; // would fail if checked

        config()->set('raise-import.integrity_disabled', true);
        config()->set('raise-import.integrity_version', $this->currentVersion());
        config()->set('raise-import.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_skips_when_no_hashes_configured()
    {
        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_hashes', []);

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_skips_on_version_mismatch()
    {
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'deadbeef'; // would fail if checked

        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_version', '9.9.9-mismatch');
        config()->set('raise-import.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertTrue(License::isIntegrityValid());
    }

    /** @test */
    public function integrity_fails_when_gatekeeper_file_is_missing()
    {
        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_version', $this->currentVersion());
        config()->set('raise-import.integrity_hashes', [
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
        config()->set('raise-import.license_key', 'valid-license-key');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        // Server says valid, but a gatekeeper file hash is wrong → refuse Pro.
        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'tampered';
        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_version', $this->currentVersion());
        config()->set('raise-import.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertFalse(License::isPro());
    }

    /* ---------------------------------------------------------------------
     | Distributed gate (gatePro) — independent re-validation
     |--------------------------------------------------------------------- */

    /** @test */
    public function gate_pro_returns_true_with_valid_key_and_integrity()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'valid-key');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertTrue(License::gatePro());
    }

    /** @test */
    public function gate_pro_returns_false_when_force_community()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'valid-key');
        config()->set('raise-import.force_community', true);

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        License::flushCache();
        $this->assertFalse(License::gatePro());
    }

    /** @test */
    public function gate_pro_returns_false_when_integrity_broken()
    {
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', 'valid-key');

        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        $hashes = $this->currentHashes();
        $hashes['src/License.php'] = 'tampered';
        config()->set('raise-import.integrity_disabled', false);
        config()->set('raise-import.integrity_version', $this->currentVersion());
        config()->set('raise-import.integrity_hashes', $hashes);

        License::flushCache();
        $this->assertFalse(License::gatePro());
    }

    /** @test */
    public function gate_pro_revalidates_independently_of_ispro_cache()
    {
        // A patched isPro() (simulated by a cached false result) is not
        // enough to unlock features — gatePro() re-validates on its own.
        app()['env'] = 'production';
        config()->set('app.url', 'https://example.com');
        config()->set('raise-import.license_key', null);

        License::flushCache();
        $this->assertFalse(License::isPro()); // caches false

        // Now supply a valid key + server, WITHOUT flushing isPro()'s cache.
        config()->set('raise-import.license_key', 'valid-key');
        Http::fake([
            'license.test/*' => Http::response($this->signedResponse([
                'valid' => true,
                'domain' => 'example.com',
                'expires_at' => time() + 86400,
                'edition' => 'pro',
            ])),
        ]);

        $this->assertFalse(License::isPro()); // still cached false
        $this->assertTrue(License::gatePro()); // independent fresh check → true
    }
}
