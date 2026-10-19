<?php

return [
    'property' => ['label' => 'property', 'plural_label' => 'properties'],
    'room' => [
        'label' => 'room', 'plural_label' => 'rooms',
        'meter_readings' => 'Utilities', 'record_reading' => 'Record utilities', 'create_invoice' => 'Create invoice',
        'previous_readings' => 'Readings for :period', 'unpaid_invoices' => 'Unpaid invoices',
        'back_to_room' => 'Back to room invoices',
        'readings_help' => 'Record readings for this room by usage month. Defaults to the previous month; only metered services are listed. Per-person and fixed fees do not need readings.',
        'invoices_help' => 'Invoices from all contracts for this room. Create an invoice to preview and confirm issue; open its details to inspect charges, due date and status.',
        'billing_help' => 'Manage utilities and invoices using the tabs below. Collect current-month rent plus previous-month services; usage month differs from collection month.',
    ],
    'tenant' => ['label' => 'tenant', 'plural_label' => 'tenants'],
    'contract' => ['label' => 'contract', 'plural_label' => 'contracts'],
    'room_reservation' => ['label' => 'room reservation', 'plural_label' => 'room reservations'],
];
