<?php

return [
    'label' => 'Meter reading',
    'plural_label' => 'Meter readings',
    'previous_help' => 'Required for the first reading or when returning from per-person/fixed billing. Enter a fresh opening value so earlier non-metered months are not charged again. Continuous metered months use the last recorded value.',
    'immutable_help' => 'Correct the latest reading with a reason and audit history. Cancel referencing invoices first, then issue replacements. Earlier readings stay locked.',
    'bulk' => [
        'title' => 'Record readings by property',
        'single' => 'Record one room',
        'history' => 'Reading history',
        'load' => 'Load rooms',
        'save' => 'Save entered readings',
        'help' => 'Choose a property and usage month. Only metered services need readings; leave the closing value blank to record later. If any entry fails, nothing in this batch is saved.',
        'opening_help' => 'Opening values come from previous readings. Enter a fresh opening value where blank.',
        'choose' => 'Choose a property and month, then load rooms to begin.',
        'empty' => 'This property has no rooms or metered services for the selected month.',
        'not_metered' => 'Not metered',
        'recorded' => 'Recorded',
        'pending' => 'Not recorded',
        'saved' => 'Saved :count readings.',
        'enter_reading' => 'Enter at least one closing value to save.',
        'reload' => 'The property or month changed. Reload rooms before saving.',
    ],
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
