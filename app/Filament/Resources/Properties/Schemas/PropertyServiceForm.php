<?php

namespace App\Filament\Resources\Properties\Schemas;

use App\Enums\BillingMethod;
use App\Models\Service;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class PropertyServiceForm
{
    /** @return array<Hidden|Select|TextInput|Toggle|DatePicker> */
    public static function components(?Service $service = null): array
    {
        $usesUnitPrice = fn (Get $get): bool => in_array($get('billing_method'), [BillingMethod::Metered->value, BillingMethod::PerPerson->value], true);
        $usesFixedPrice = fn (Get $get): bool => $get('billing_method') === BillingMethod::Fixed->value;

        return [
            ...($service === null ? [
                Select::make('service_id')->label(fn (): string => __('services.label'))
                    ->options(fn (): array => Service::query()->where('landlord_id', auth()->id())->where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable()->required()->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $service = Service::query()->where('landlord_id', auth()->id())->where('is_active', true)->find($state);
                        $set('billing_method', $service?->billing_method->value);
                        $set('unit_price', null);
                        $set('fixed_price', null);
                    }),
            ] : []),
            Hidden::make('billing_method')->default($service?->billing_method->value)->dehydrated(false),
            TextInput::make('unit_price')->label(fn (): string => __('services.fields.unit_price'))
                ->numeric()->minValue(0)->maxValue(9999999999.99)->rule('decimal:0,2')
                ->visible($usesUnitPrice)->required($usesUnitPrice),
            TextInput::make('fixed_price')->label(fn (): string => __('services.fields.fixed_price'))
                ->numeric()->minValue(0)->maxValue(9999999999.99)->rule('decimal:0,2')
                ->visible($usesFixedPrice)->required($usesFixedPrice),
            Toggle::make('is_enabled')->label(fn (): string => __('services.fields.is_enabled'))->default(true)->required(),
            DatePicker::make('effective_from')->label(fn (): string => __('services.fields.effective_from'))
                ->default(today())->minDate(today())->required()
                ->helperText(fn (): string => __('services.price_history_help')),
        ];
    }
}
