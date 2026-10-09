<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\RoomStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_id')
                    ->relationship('property', 'name')
                    ->required(),
                TextInput::make('room_number')
                    ->required(),
                TextInput::make('floor')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('area')
                    ->numeric(),
                TextInput::make('base_rent')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('deposit_amount')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('max_occupants')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('status')
                    ->options(RoomStatus::class)
                    ->default('available')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
