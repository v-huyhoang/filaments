<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\ContractStatus;
use App\Models\Room;
use App\Models\Tenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->relationship(
                        name: 'room',
                        titleAttribute: 'room_number',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
                            ->with('property'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Room $record): string => "{$record->property->name} - {$record->room_number}")
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tenant_id')
                    ->relationship(
                        name: 'tenant',
                        titleAttribute: 'full_name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('landlord_id', auth()->id()),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Tenant $record): string => "{$record->full_name} - {$record->phone}")
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->afterOrEqual('start_date'),
                TextInput::make('rent_amount')->numeric()->minValue(0)->required(),
                TextInput::make('deposit_amount')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('payment_due_day')->numeric()->minValue(1)->maxValue(28)->required(),
                Select::make('status')
                    ->options([
                        ContractStatus::Draft->value => 'Draft',
                        ContractStatus::Scheduled->value => 'Scheduled',
                    ])
                    ->default(ContractStatus::Scheduled->value)
                    ->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
