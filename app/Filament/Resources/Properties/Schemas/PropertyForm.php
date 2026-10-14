<?php

namespace App\Filament\Resources\Properties\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(fn (): string => __('forms.property_name'))
                    ->required(),
                TextInput::make('address')
                    ->label(fn (): string => __('forms.address'))
                    ->required(),
                Textarea::make('description')
                    ->label(fn (): string => __('forms.description'))
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(fn (): string => __('forms.active'))
                    ->inline(false)
                    ->default(true),
            ]);
    }
}
