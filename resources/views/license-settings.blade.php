<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status Card --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('raise-import::messages.license.status_heading') }}
            </x-slot>

            <div class="flex items-center gap-4">
                {{-- Status badge --}}
                <x-filament::badge
                    :color="$getStatusColor()"
                    class="text-lg px-4 py-2"
                >
                    {{ $licenseInfo['message'] ?? __('raise-import::messages.license.status_unknown') }}
                </x-filament::badge>
            </div>

            @if(!empty($licenseInfo['key']))
                <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium">{{ __('raise-import::messages.license.license_key') }}:</span>
                    <code class="ml-2 px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded">{{ $licenseInfo['key'] }}</code>
                </div>
            @endif
        </x-filament::section>

        {{-- Activation Form --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('raise-import::messages.license.activation_heading') }}
            </x-slot>

            <form wire:submit="activate" class="space-y-4">
                <div>
                    <x-filament::input.wrapper
                        :label="__('raise-import::messages.license.license_key_label')"
                        :hint="__('raise-import::messages.license.license_key_hint')"
                    >
                        <x-filament::input
                            type="text"
                            wire:model="licenseKey"
                            :placeholder="__('raise-import::messages.license.license_key_placeholder')"
                            class="font-mono"
                        />
                    </x-filament::input.wrapper>
                    @error('licenseKey')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3">
                    <x-filament::button
                        type="submit"
                        color="primary"
                        :loading="$isActivating"
                    >
                        {{ __('raise-import::messages.license.activate_button') }}
                    </x-filament::button>

                    @if($licenseInfo['has_key'] ?? false)
                        <x-filament::button
                            color="danger"
                            wire:click="deactivate"
                            :loading="$isActivating"
                        >
                            {{ __('raise-import::messages.license.deactivate_button') }}
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </x-filament::section>

        {{-- Features List --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('raise-import::messages.license.features_heading') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($getFeatureGroups() as $group)
                    <div>
                        <h3 class="text-sm font-medium mb-3 flex items-center gap-2">
                            @if($group['available'])
                                <x-filament::icon
                                    name="heroicon-o-check-circle"
                                    class="w-5 h-5 text-success-500"
                                />
                            @else
                                <x-filament::icon
                                    name="heroicon-o-lock-closed"
                                    class="w-5 h-5 text-gray-400"
                                />
                            @endif
                            {{ $group['label'] }}
                        </h3>
                        <ul class="space-y-1">
                            @foreach($group['features'] as $feature)
                                <li class="flex items-center gap-2 text-sm">
                                    @if($group['available'])
                                        <span class="text-success-500">&#10003;</span>
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ __("raise-import::messages.license.feature_{$feature}") }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">&#10007;</span>
                                        <span class="text-gray-400 line-through">
                                            {{ __("raise-import::messages.license.feature_{$feature}") }}
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
