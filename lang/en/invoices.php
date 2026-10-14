<?php

return [
    'label' => 'Invoice',
    'plural_label' => 'Invoices',
    'rent' => 'Rent',
    'occupant_help' => 'Actual number of occupants, required for per-person fees. Must not exceed room capacity.',
    'generation_help' => 'Full monthly rent; service prices effective at month end. Manual services are not billed automatically. Issued amounts cannot be edited.',
    'fields' => [
        'number' => 'Invoice number', 'contract' => 'Contract', 'billing_period' => 'Billing month',
        'occupant_count' => 'Actual occupants', 'property' => 'Property', 'room' => 'Room', 'tenant' => 'Tenant',
        'status' => 'Status', 'issued_at' => 'Issued at', 'due_date' => 'Due date', 'subtotal' => 'Subtotal',
        'total' => 'Total', 'notes' => 'Notes', 'items' => 'Invoice items', 'name' => 'Item',
        'quantity' => 'Quantity', 'unit' => 'Unit', 'unit_price' => 'Unit price', 'amount' => 'Amount',
    ],
    'statuses' => ['draft' => 'Draft', 'issued' => 'Issued', 'partial' => 'Partially paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'],
    'actions' => ['cancel' => 'Cancel invoice'],
    'errors' => [
        'contract_unavailable' => 'Select a contract belonging to your properties and tenant.',
        'contract_not_billable' => 'Select a contract with actual tenancy overlapping this month. Checked-out contracts can bill earlier tenancy months.',
        'duplicate' => 'An invoice already exists for this month. To replace a cancelled invoice, use Create replacement invoice from its detail page.',
        'missing_reading' => 'A meter reading is required for :service in this billing month.',
        'occupant_count_required' => 'Enter the actual number of occupants for per-person fees.',
        'occupant_count_exceeds_capacity' => 'The number of occupants exceeds room capacity.',
        'immutable' => 'Invoice snapshots cannot be edited or deleted. Cancel the invoice instead.',
        'amount_out_of_range' => 'The calculated invoice amount is outside the supported range.',
        'invalid_status_transition' => 'This invoice status transition is not supported.',
    ],
];
