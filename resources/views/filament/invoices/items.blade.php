<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @once
        <style>
            .invoice-items { width: 100%; border-collapse: collapse; font-size: .875rem; }
            .invoice-items th, .invoice-items td { padding: .75rem; border-bottom: 1px solid var(--gray-200); text-align: start; vertical-align: top; overflow-wrap: anywhere; }
            .invoice-items th { background: var(--gray-50); font-weight: 600; }
            .invoice-items .invoice-number { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
            .invoice-items summary { cursor: pointer; margin-top: .5rem; font-weight: 500; }
            .invoice-items dl { margin-top: .5rem; }
            .invoice-items dl > div { display: flex; flex-wrap: wrap; gap: .5rem; margin-block: .25rem; }
            .invoice-items dd { font-variant-numeric: tabular-nums; }
            .invoice-mobile-label { display: none; }
            .dark .invoice-items th { background: var(--gray-900); }
            .dark .invoice-items th, .dark .invoice-items td { border-color: var(--gray-700); }
            @media (max-width: 1023px) {
                .invoice-items thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
                .invoice-items, .invoice-items tbody { display: block; }
                .invoice-items tr { display: block; border: 1px solid var(--gray-200); border-radius: .75rem; margin-bottom: .75rem; }
                .dark .invoice-items tr { border-color: var(--gray-700); }
                .invoice-items td, .invoice-items .invoice-number { display: flex; justify-content: space-between; gap: 1rem; text-align: end; white-space: normal; }
                .invoice-items td:last-child { border-bottom: 0; font-weight: 600; }
                .invoice-mobile-label { display: inline; text-align: start; font-weight: 500; }
            }
        </style>
    @endonce

    @php
        $columns = ['name', 'charge_from', 'charge_to', 'quantity', 'unit', 'unit_price', 'amount'];
        $formatDate = fn ($value) => filled($value) ? \Carbon\CarbonImmutable::parse($value)->format('d/m/Y') : '-';
    @endphp

    <table class="invoice-items">
        <caption style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%);">{{ __('invoices.fields.items') }}</caption>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th scope="col" @class(['invoice-number' => in_array($column, ['quantity', 'unit_price', 'amount'])])>{{ __('invoices.fields.'.$column) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($getState() ?? [] as $item)
                @php
                    $metadata = data_get($item, 'metadata', []) ?? [];
                    $values = [
                        'name' => data_get($item, 'name'),
                        'charge_from' => $formatDate($metadata['charge_from'] ?? null),
                        'charge_to' => $formatDate($metadata['charge_to'] ?? null),
                        'quantity' => data_get($item, 'quantity'),
                        'unit' => data_get($item, 'unit') ?: '-',
                        'unit_price' => \Illuminate\Support\Number::currency((float) data_get($item, 'unit_price'), 'VND', precision: 2),
                        'amount' => \Illuminate\Support\Number::currency((float) data_get($item, 'amount'), 'VND', precision: 2),
                    ];
                    $details = array_intersect_key($metadata, array_flip(['previous_value', 'current_value', 'monthly_rent', 'days', 'person_days', 'days_in_month']));
                @endphp
                <tr>
                    @foreach ($values as $column => $value)
                        <td @class(['invoice-number' => in_array($column, ['quantity', 'unit_price', 'amount'])])>
                            <span class="invoice-mobile-label">{{ __('invoices.fields.'.$column) }}</span>
                            <div>
                                {{ $value }}
                                @if ($column === 'quantity' && data_get($item, 'type') === 'per_person')
                                    <div>{{ __('occupancy.approximate_months') }}</div>
                                @endif
                                @if ($column === 'name' && ($details !== [] || ! empty($metadata['occupants'])))
                                    <details>
                                        <summary>{{ __('invoices.calculation_details') }}</summary>
                                        <dl>
                                            @foreach ($details as $key => $detail)
                                                <div>
                                                    <dt>{{ __((in_array($key, ['previous_value', 'current_value']) ? 'meter_readings.fields.' : ($key === 'monthly_rent' ? 'invoices.fields.' : 'occupancy.')).$key) }}</dt>
                                                    <dd>{{ $key === 'monthly_rent' ? \Illuminate\Support\Number::currency((float) $detail, 'VND', precision: 2) : $detail }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                        @foreach ($metadata['occupants'] ?? [] as $occupant)
                                            <dl>
                                                <div><dt>{{ __('occupancy.full_name') }}</dt><dd>{{ $occupant['full_name'] }}</dd></div>
                                                <div><dt>{{ __('occupancy.charge_from') }}</dt><dd>{{ $formatDate($occupant['from']) }}</dd></div>
                                                <div><dt>{{ __('occupancy.charge_until') }}</dt><dd>{{ $formatDate($occupant['until']) }}</dd></div>
                                                <div><dt>{{ __('occupancy.days') }}</dt><dd>{{ $occupant['days'] }}</dd></div>
                                            </dl>
                                        @endforeach
                                    </details>
                                @endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</x-dynamic-component>
