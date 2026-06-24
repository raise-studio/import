<div x-data="{ importing: false, completed: false }" class="space-y-4">
    <template x-if="!importing && !completed">
        <div class="text-center p-8">
            <div class="text-gray-500 dark:text-gray-400 mb-2">
                {{ __('raise-import::messages.wizard.start_import') }}
            </div>
        </div>
    </template>

    <template x-if="importing && !completed">
        <div class="text-center p-8">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
            <div class="text-gray-600 dark:text-gray-400 font-medium">
                {{ __('raise-import::messages.wizard.importing') }}
            </div>
        </div>
    </template>

    <template x-if="completed">
        <div class="space-y-4" x-data="{
            imported: 0,
            skipped: 0,
            failed: 0,
            init() {
                // These will be set by Livewire after import completes
                this.imported = $wire.importedCount ?? 0;
                this.skipped = $wire.skippedCount ?? 0;
                this.failed = $wire.failedCount ?? 0;
            }
        }">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-success-50 dark:bg-success-900/20 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400" x-text="imported">0</div>
                    <div class="text-sm text-success-600 dark:text-success-400">
                        {{ __('raise-import::messages.results.imported', ['count' => '']) }}
                    </div>
                </div>
                <div class="bg-warning-50 dark:bg-warning-900/20 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-warning-600 dark:text-warning-400" x-text="skipped">0</div>
                    <div class="text-sm text-warning-600 dark:text-warning-400">
                        {{ __('raise-import::messages.results.skipped', ['count' => '']) }}
                    </div>
                </div>
                <div class="bg-danger-50 dark:bg-danger-900/20 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-danger-600 dark:text-danger-400" x-text="failed">0</div>
                    <div class="text-sm text-danger-600 dark:text-danger-400">
                        {{ __('raise-import::messages.results.failed', ['count' => '']) }}
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
