<?php

namespace RaiseStudio\Import\Console\Commands;

use Illuminate\Console\Command;

class IntegrityRehashCommand extends Command
{
    protected $signature = 'raise-import:integrity:rehash';

    protected $description = 'Generate SHA-256 integrity hashes for the Pro gatekeeper files';

    /**
     * Gatekeeper files verified by the runtime integrity self-check.
     * Paths are relative to the package root.
     */
    private const FILES = [
        'src/License.php',
        'src/Pro/Actions/ProImportAction.php',
        'src/RaiseImportServiceProvider.php',
    ];

    public function handle(): int
    {
        $base = realpath(__DIR__ . '/../../..');

        if ($base === false) {
            $this->error('Unable to resolve package root.');
            return self::FAILURE;
        }

        $hashes = [];
        foreach (self::FILES as $relative) {
            $absolute = $base . DIRECTORY_SEPARATOR . $relative;
            if (!is_file($absolute)) {
                $this->warn("Skipped (not found): {$relative}");
                continue;
            }
            $hashes[$relative] = hash_file('sha256', $absolute);
        }

        $version = $this->packageVersion();

        $this->info("Package version: {$version}");
        $this->newLine();
        $this->info('Paste the following into config/raise-import.php:');
        $this->newLine();
        $this->line("'integrity_version' => '{$version}',");
        $this->line("'integrity_hashes' => [");
        foreach ($hashes as $relative => $hash) {
            $this->line("    '{$relative}' => '{$hash}',");
        }
        $this->line('],');

        return self::SUCCESS;
    }

    private function packageVersion(): string
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
}
