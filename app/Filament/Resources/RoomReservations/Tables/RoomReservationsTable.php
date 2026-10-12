<?php

namespace App\Filament\Resources\RoomReservations\Tables;

use App\Actions\Reservations\ConvertRoomReservationAction;
use App\Enums\RoomReservationStatus;
use App\Models\RoomReservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.property.name')->label('Property')->searchable(),
                TextColumn::make('room.room_number')->label('Room')->searchable(),
                TextColumn::make('tenant.full_name')->label('Tenant')->searchable(),
                TextColumn::make('expected_move_in_date')->date()->sortable(),
                TextColumn::make('hold_amount')->money('VND')->sortable(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    RoomReservationStatus::Held->value => 'Held',
                    RoomReservationStatus::Cancelled->value => 'Cancelled',
                    RoomReservationStatus::Expired->value => 'Expired',
                    RoomReservationStatus::Converted->value => 'Converted',
                ]),
            ])
            ->recordActions([
                Action::make('convertToContract')
                    ->label('Convert to contract')
                    ->schema([
                        DatePicker::make('end_date'),
                        TextInput::make('rent_amount')->numeric()->minValue(0)->required(),
                        TextInput::make('deposit_amount')->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('payment_due_day')->numeric()->minValue(1)->maxValue(28)->required(),
                        TextInput::make('notes'),
                    ])
                    ->visible(fn (RoomReservation $record): bool => $record->status === RoomReservationStatus::Held && $record->expires_at->isFuture())
                    ->action(function (RoomReservation $record, array $data): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        app(ConvertRoomReservationAction::class)->handle($landlord, $record, $data);
                    }),
            ]);
    }
}
