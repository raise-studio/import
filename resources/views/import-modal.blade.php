<div class="space-y-4">
    <div class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('raise-import::messages.upload.dropzone') }}
    </div>

    <div class="text-xs text-gray-400 dark:text-gray-500">
        {{ __('raise-import::messages.upload.supported') }}
    </div>

    @if(count($getRecord()?->fields ?? $this->getCachedForms()['form']?->getModel()?->getFillable() ?? []) > 0)
        <div class="mt-2">
            <a href="{{ route('raise-import.template', ['modelClass' => str_replace('\\', '_', $this->modelClass ?? '')]) }}"
               class="text-primary-600 hover:text-primary-500 text-sm underline">
                {{ __('raise-import::messages.upload.download_template') }}
            </a>
        </div>
    @endif
</div>
