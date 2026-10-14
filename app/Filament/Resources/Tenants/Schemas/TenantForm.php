<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Enums\TenantStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label(fn (): string => __('forms.full_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(fn (): string => __('forms.phone'))
                    ->tel()
                    ->required()
                    ->minLength(7)
                    ->maxLength(20)
                    ->rule('regex:/^[0-9+().\-\s]+$/'),
                TextInput::make('email')
                    ->label(fn (): string => __('forms.email'))
                    ->email()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->label(fn (): string => __('forms.date_of_birth'))
                    ->maxDate(today()),
                TextInput::make('identity_number')
                    ->label(fn (): string => __('forms.identity_number'))
                    ->rule('regex:/^[0-9]{9,12}$/')
                    ->maxLength(50)
                    ->unique(
                        table: 'tenants',
                        column: 'identity_number',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('landlord_id', auth()->id()),
                    ),
                DatePicker::make('identity_issued_date')
                    ->label(fn (): string => __('forms.identity_issued_date'))
                    ->maxDate(today()),
                TextInput::make('identity_issued_place')
                    ->label(fn (): string => __('forms.identity_issued_place'))
                    ->maxLength(255),
                Textarea::make('address')
                    ->label(fn (): string => __('forms.address'))
                    ->columnSpanFull(),
                TextInput::make('emergency_contact_name')
                    ->label(fn (): string => __('forms.emergency_contact_name'))
                    ->maxLength(255),
                TextInput::make('emergency_contact_phone')
                    ->label(fn (): string => __('forms.emergency_contact_phone'))
                    ->tel()
                    ->minLength(7)
                    ->maxLength(20)
                    ->rule('regex:/^[0-9+().\-\s]+$/'),
                Select::make('status')
                    ->label(fn (): string => __('forms.status'))
                    ->options(TenantStatus::class)
                    ->default(TenantStatus::Active->value)
                    ->required(),
                Textarea::make('notes')
                    ->label(fn (): string => __('forms.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
