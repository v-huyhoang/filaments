<?php

return [
    'label' => 'Meter reading',
    'plural_label' => 'Meter readings',
    'previous_help' => 'Required for the first reading. Later readings use the last recorded value. Enter periods in chronological order. Service eligibility is checked at month end.',
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
        'initial_value_required' => 'Enter the initial previous value for the first reading.',
        'previous_mismatch' => 'Previous value must match the last recorded current value.',
        'decreasing_value' => 'Current value must be greater than or equal to previous value.',
        'immutable' => 'Recorded readings cannot be edited or deleted. Corrections require a separate adjustment workflow.',
    ],
];
