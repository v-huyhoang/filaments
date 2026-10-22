<x-filament-panels::page>
    <style>
        .property-readings { display: grid; gap: 1.25rem; }
        .property-readings-help { color: var(--gray-600); font-size: .875rem; }
        .property-readings-scroll { overflow-x: auto; }
        .property-readings-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .property-readings-table th, .property-readings-table td { padding: 1rem; border-bottom: 1px solid var(--gray-200); text-align: start; vertical-align: top; }
        .property-readings-table thead th { background: var(--gray-50); }
        .property-reading-meter { display: grid; gap: .75rem; min-width: 17rem; }
        .property-reading-values { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .property-reading-values label { display: grid; gap: .375rem; }
        .property-reading-usage { font-variant-numeric: tabular-nums; }
        .property-reading-service { display: none; }
        .property-readings-errors { color: var(--danger-600); }
        .dark .property-readings-help { color: var(--gray-400); }
        .dark .property-readings-table th, .dark .property-readings-table td { border-color: var(--gray-700); }
        .dark .property-readings-table thead th { background: var(--gray-900); }
        .dark .property-readings-errors { color: var(--danger-400); }
        @media (max-width: 767px) {
            .property-readings-table, .property-readings-table tbody, .property-readings-table tr, .property-readings-table td { display: block; }
            .property-readings-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
            .property-readings-table tbody tr { border: 1px solid var(--gray-200); border-radius: .75rem; margin-bottom: 1rem; }
            .dark .property-readings-table tbody tr { border-color: var(--gray-700); }
            .property-readings-table tbody th { display: block; }
            .property-readings-table td:last-child { border-bottom: 0; }
            .property-reading-service { display: block; font-weight: 600; margin-bottom: .75rem; }
            .property-reading-meter { min-width: 0; }
        }
    </style>

    <div class="property-readings">
        <x-filament::section>
            <form wire:submit="loadReadings" class="property-readings">
                {{ $this->form }}
                <p class="property-readings-help">{{ __('meter_readings.bulk.help') }}</p>
                <div>
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="loadReadings,save">
                        {{ __('meter_readings.bulk.load') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if ($errors->any())
            <div role="alert" class="property-readings-errors">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($loadedPropertyId !== null && count($services) > 0)
            <form wire:submit="save" class="property-readings">
                <p class="property-readings-help">{{ __('meter_readings.bulk.opening_help') }}</p>
                <x-filament::section>
                    <div class="property-readings-scroll">
                        <table class="property-readings-table">
                            <caption>{{ __('meter_readings.bulk.title') }} — {{ $loadedPeriod }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('meter_readings.fields.room') }}</th>
                                    @foreach ($services as $service)
                                        <th scope="col">{{ $service }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rooms as $roomId => $room)
                                    <tr wire:key="room-{{ $loadedPropertyId }}-{{ $loadedPeriod }}-{{ $roomId }}">
                                        <th scope="row">{{ $room['room_number'] }}</th>
                                        @foreach ($services as $serviceId => $service)
                                            <td>
                                                <span class="property-reading-service">{{ $service }}</span>
                                                @if (isset($room['meters'][$serviceId]))
                                                    @php($meter = $room['meters'][$serviceId])
                                                    @if ($meter['recorded'])
                                                        <div class="property-reading-meter">
                                                            <x-filament::badge color="success">{{ __('meter_readings.bulk.recorded') }}</x-filament::badge>
                                                            <div class="property-reading-values">
                                                                <span>{{ __('meter_readings.fields.previous_value') }}: {{ $meter['previous_value'] }}</span>
                                                                <span>{{ __('meter_readings.fields.current_value') }}: {{ $meter['current_value'] }}</span>
                                                            </div>
                                                            <span class="property-reading-usage">{{ __('meter_readings.fields.usage') }}: {{ $meter['usage'] }} {{ $meter['unit'] }}</span>
                                                        </div>
                                                    @else
                                                        <div class="property-reading-meter" x-data="{
                                                            previous: $wire.entangle('readings.{{ $meter['key'] }}.previous_value'),
                                                            current: $wire.entangle('readings.{{ $meter['key'] }}.current_value'),
                                                            get usage() {
                                                                if (this.previous === null || this.previous === '' || this.current === null || this.current === '') return '—';
                                                                const value = Number(this.current) - Number(this.previous);
                                                                return Number.isFinite(value) ? value.toFixed(3) : '—';
                                                            }
                                                        }">
                                                            <x-filament::badge color="gray">{{ __('meter_readings.bulk.pending') }}</x-filament::badge>
                                                            <div class="property-reading-values">
                                                                @foreach (['previous_value' => 'previous', 'current_value' => 'current'] as $field => $model)
                                                                    @php($errorKey = 'readings.'.$meter['key'].'.'.$field)
                                                                    <label for="reading-{{ $meter['key'] }}-{{ $field }}">
                                                                        <span>{{ __('meter_readings.fields.'.$field) }}</span>
                                                                        <x-filament::input.wrapper :valid="! $errors->has($errorKey)">
                                                                            <x-filament::input
                                                                                type="number" step="0.001" min="0" max="999999999.999"
                                                                                id="reading-{{ $meter['key'] }}-{{ $field }}"
                                                                                x-model="{{ $model }}"
                                                                                :readonly="$field === 'previous_value' && $meter['previous_value'] !== null"
                                                                                :aria-label="$room['room_number'].' / '.$service.' / '.__('meter_readings.fields.'.$field)"
                                                                                :aria-invalid="$errors->has($errorKey) ? 'true' : 'false'"
                                                                                :aria-describedby="$errors->has($errorKey) ? 'error-'.$meter['key'].'-'.$field : null"
                                                                            />
                                                                        </x-filament::input.wrapper>
                                                                        @error($errorKey)
                                                                            <span class="property-readings-errors" id="error-{{ $meter['key'] }}-{{ $field }}">{{ $message }}</span>
                                                                        @enderror
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                            <span class="property-reading-usage">{{ __('meter_readings.fields.usage') }}: <output x-text="usage">—</output> {{ $meter['unit'] }}</span>
                                                            @foreach (['billing_period', 'room_id', 'service_id'] as $field)
                                                                @error('readings.'.$meter['key'].'.'.$field)
                                                                    <span class="property-readings-errors">{{ $message }}</span>
                                                                @enderror
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="property-readings-help">{{ __('meter_readings.bulk.not_metered') }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
                @if (count($readings) > 0)
                    <div>
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save,loadReadings">
                            {{ __('meter_readings.bulk.save') }}
                        </x-filament::button>
                    </div>
                @endif
            </form>
        @else
            <x-filament::section>
                <p class="property-readings-help">{{ __($loadedPropertyId === null ? 'meter_readings.bulk.choose' : 'meter_readings.bulk.empty') }}</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
