<?php

namespace App\Filament\Resources\RoomReservations\Schemas;

use App\Models\Room;
use App\Models\Tenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoomReservationForm
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
                DatePicker::make('expected_move_in_date')->minDate(today())->required(),
                TextInput::make('hold_amount')->numeric()->minValue(1)->required(),
                DateTimePicker::make('paid_at')->default(now())->required(),
                TextInput::make('payment_method')->maxLength(50)->required(),
                TextInput::make('payment_reference')->maxLength(255),
                DateTimePicker::make('expires_at')->after('paid_at')->minDate(now())->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
