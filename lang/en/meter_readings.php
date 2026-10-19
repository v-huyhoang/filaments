<?php

return [
    'label' => 'Meter reading',
    'plural_label' => 'Meter readings',
    'previous_help' => 'Required for the first reading or when returning from per-person/fixed billing. Enter a fresh opening value so earlier non-metered months are not charged again. Continuous metered months use the last recorded value.',
    'immutable_help' => 'Correct the latest reading with a reason and audit history. Cancel referencing invoices first, then issue replacements. Earlier readings stay locked.',
    'fields' => [
        'property' => 'Property', 'room' => 'Room', 'service' => 'Service', 'billing_period' => 'Billing month',
        'previous_value' => 'Previous value', 'current_value' => 'Current value', 'usage' => 'Usage',
        'recorded_at' => 'Recorded at', 'recorded_by' => 'Recorded by', 'notes' => 'Notes',
    ],
    'errors' => [
        'room_unavailable' => 'Select a room belonging to your properties.',
        'service_unavailable' => 'Select an active metered service enabled for this property in the billing month.',
        'period_order' => 'A reading already exists for this or a later month. Enter readings in chronological order.',
        'initial_value_required' => 'Enter the opening value for the first reading or a new metered billing period.',
        'previous_mismatch' => 'Previous value must match the last recorded current value.',
        'decreasing_value' => 'Current value must be greater than or equal to previous value.',
        'immutable' => 'Recorded readings cannot be edited or deleted. Corrections require a separate adjustment workflow.',
    ],
];
