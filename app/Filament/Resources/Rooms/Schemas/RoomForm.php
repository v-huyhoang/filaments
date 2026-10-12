<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\RoomStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_id')
                    ->label('Bất động sản')
                    ->relationship(
                        name: 'property',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('landlord_id', auth()->id()),
                    )
                    ->rule(
                        Rule::exists('properties', 'id')
                            ->where('landlord_id', auth()->id())
                            ->whereNull('deleted_at'),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('room_number')
                    ->label('Số phòng')
                    ->unique(
                        table: 'rooms',
                        column: 'room_number',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('property_id', $get('property_id')),
                    )
                    ->required(),
                TextInput::make('floor')
                    ->label('Tầng')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                TextInput::make('area')
                    ->label('Diện tích (m²)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('base_rent')
                    ->label('Giá thuê cơ bản')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0.0),
                TextInput::make('deposit_amount')
                    ->label('Tiền cọc')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0.0),
                TextInput::make('max_occupants')
                    ->label('Số người ở tối đa')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options(RoomStatus::class)
                    ->default('available')
                    ->required(),
                Textarea::make('description')
                    ->label('Mô tả')
                    ->columnSpanFull(),
            ]);
    }
}
