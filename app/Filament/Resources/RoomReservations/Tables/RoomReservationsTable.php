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
                TextColumn::make('room.property.name')->label(fn (): string => __('tables.property'))->searchable(),
                TextColumn::make('room.room_number')->label(fn (): string => __('tables.room'))->searchable(),
                TextColumn::make('tenant.full_name')->label(fn (): string => __('tables.tenant'))->searchable(),
                TextColumn::make('expected_move_in_date')->label(fn (): string => __('tables.expected_move_in_date'))->date()->sortable(),
                TextColumn::make('hold_amount')->label(fn (): string => __('tables.hold_amount'))->money('VND')->sortable(),
                TextColumn::make('paid_at')->label(fn (): string => __('tables.paid_at'))->dateTime()->sortable(),
                TextColumn::make('expires_at')->label(fn (): string => __('tables.expires_at'))->dateTime()->sortable(),
                TextColumn::make('status')->label(fn (): string => __('tables.status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(fn (): string => __('tables.status'))->options(RoomReservationStatus::class),
            ])
            ->recordActions([
                Action::make('convertToContract')
                    ->label(fn (): string => __('tables.convert_to_contract'))
                    ->schema([
                        DatePicker::make('end_date')->label(fn (): string => __('tables.end_date')),
                        TextInput::make('rent_amount')->label(fn (): string => __('tables.rent_amount'))->numeric()->minValue(0)->required(),
                        TextInput::make('deposit_amount')->label(fn (): string => __('tables.deposit_amount'))->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('payment_due_day')->label(fn (): string => __('tables.payment_due_day'))->numeric()->minValue(1)->maxValue(28)->required(),
                        TextInput::make('notes')->label(fn (): string => __('tables.notes')),
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
