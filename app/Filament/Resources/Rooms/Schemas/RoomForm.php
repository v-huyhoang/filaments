<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
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
                    ->label(fn (): string => __('forms.property'))
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
                    ->label(fn (): string => __('forms.room_number'))
                    ->unique(
                        table: 'rooms',
                        column: 'room_number',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('property_id', $get('property_id')),
                    )
                    ->required(),
                TextInput::make('floor')
                    ->label(fn (): string => __('forms.floor'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                TextInput::make('area')
                    ->label(fn (): string => __('forms.area'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('base_rent')
                    ->label(fn (): string => __('forms.base_rent'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0.0),
                TextInput::make('deposit_amount')
                    ->label(fn (): string => __('forms.deposit_amount'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0.0),
                TextInput::make('max_occupants')
                    ->label(fn (): string => __('forms.max_occupants'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                Select::make('status')
                    ->label(fn (): string => __('forms.status'))
                    ->options(fn (?Room $record): array => $record?->contracts()->where('status', ContractStatus::Active)->exists()
                        ? [RoomStatus::OCCUPIED->value => RoomStatus::OCCUPIED->getLabel()]
                        : collect(RoomStatus::cases())->reject(fn (RoomStatus $status): bool => $status === RoomStatus::OCCUPIED)->mapWithKeys(fn (RoomStatus $status): array => [$status->value => $status->getLabel()])->all())
                    ->helperText(fn (): string => __('workflow.room_status_managed'))
                    ->default('available')
                    ->required(),
                Textarea::make('description')
                    ->label(fn (): string => __('forms.description'))
                    ->columnSpanFull(),
            ]);
    }
}
