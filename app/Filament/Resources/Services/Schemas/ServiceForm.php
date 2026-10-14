<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\BillingMethod;
use App\Models\Service;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(fn (): string => __('services.fields.name'))->required()->maxLength(255),
            TextInput::make('code')->label(fn (): string => __('services.fields.code'))
                ->required()->maxLength(50)->rule('regex:/^[a-z][a-z0-9_]*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('landlord_id', auth()->id())),
            Select::make('billing_method')->label(fn (): string => __('services.fields.billing_method'))
                ->options(BillingMethod::class)->enum(BillingMethod::class)->required()
                ->disabled(fn (?Service $record): bool => $record?->propertyServices()->exists() ?? false)->dehydrated(),
            TextInput::make('unit')->label(fn (): string => __('services.fields.unit'))->required()->maxLength(50)
                ->disabled(fn (?Service $record): bool => $record?->propertyServices()->exists() ?? false)->dehydrated(),
            Toggle::make('is_active')->label(fn (): string => __('services.fields.is_active'))->default(true)->required()
                ->helperText(fn (): string => __('workflow.service_effective_help')),
        ]);
    }
}
