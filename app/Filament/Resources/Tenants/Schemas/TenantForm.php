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
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->minLength(7)
                    ->maxLength(20)
                    ->rule('regex:/^[0-9+().\-\s]+$/'),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->maxDate(today()),
                TextInput::make('identity_number')
                    ->rule('regex:/^[0-9]{9,12}$/')
                    ->maxLength(50)
                    ->unique(
                        table: 'tenants',
                        column: 'identity_number',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('landlord_id', auth()->id()),
                    ),
                DatePicker::make('identity_issued_date')
                    ->maxDate(today()),
                TextInput::make('identity_issued_place')
                    ->maxLength(255),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('emergency_contact_name')
                    ->maxLength(255),
                TextInput::make('emergency_contact_phone')
                    ->tel()
                    ->minLength(7)
                    ->maxLength(20)
                    ->rule('regex:/^[0-9+().\-\s]+$/'),
                Select::make('status')
                    ->options(TenantStatus::class)
                    ->default(TenantStatus::Active->value)
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
