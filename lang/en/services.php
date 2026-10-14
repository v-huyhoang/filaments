<?php

return [
    'label' => 'service',
    'plural_label' => 'services',
    'property_services' => 'Property services',
    'configuration' => 'service configuration',
    'price_history_help' => 'Price changes create a new version with a later effective date; previous versions remain unchanged.',
    'fields' => [
        'name' => 'Service name',
        'code' => 'Service code',
        'billing_method' => 'Billing method',
        'unit' => 'Unit',
        'is_active' => 'Active',
        'unit_price' => 'Unit price',
        'fixed_price' => 'Fixed price',
        'is_enabled' => 'Enabled',
        'effective_from' => 'Effective from',
    ],
    'billing_methods' => [
        'metered' => 'Metered',
        'fixed' => 'Fixed',
        'per_person' => 'Per person',
        'manual' => 'Manual',
    ],
    'actions' => [
        'configure' => 'Configure service',
        'revise' => 'Change price / availability',
    ],
    'errors' => [
        'definition_in_use' => 'A configured service cannot change billing method or unit, to preserve historical pricing.',
        'service_unavailable' => 'The selected service is unavailable.',
        'already_configured' => 'This service is already configured for the property; create a new price version instead.',
        'stale_version' => 'A newer configuration exists. Reload before updating.',
        'later_effective_date' => 'The effective date must be later than the latest version.',
    ],
];
