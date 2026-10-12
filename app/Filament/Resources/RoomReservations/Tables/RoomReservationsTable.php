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
                TextColumn::make('room.property.name')->label('Bất động sản')->searchable(),
                TextColumn::make('room.room_number')->label('Phòng')->searchable(),
                TextColumn::make('tenant.full_name')->label('Người thuê')->searchable(),
                TextColumn::make('expected_move_in_date')->label('Ngày dự kiến nhận phòng')->date()->sortable(),
                TextColumn::make('hold_amount')->label('Tiền giữ chỗ')->money('VND')->sortable(),
                TextColumn::make('paid_at')->label('Thời điểm thanh toán')->dateTime()->sortable(),
                TextColumn::make('expires_at')->label('Thời hạn giữ chỗ')->dateTime()->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options([
                    RoomReservationStatus::Held->value => 'Đang giữ chỗ',
                    RoomReservationStatus::Cancelled->value => 'Đã hủy',
                    RoomReservationStatus::Expired->value => 'Đã hết hạn',
                    RoomReservationStatus::Converted->value => 'Đã chuyển thành hợp đồng',
                ]),
            ])
            ->recordActions([
                Action::make('convertToContract')
                    ->label('Chuyển thành hợp đồng')
                    ->schema([
                        DatePicker::make('end_date')->label('Ngày kết thúc'),
                        TextInput::make('rent_amount')->label('Giá thuê')->numeric()->minValue(0)->required(),
                        TextInput::make('deposit_amount')->label('Tiền cọc')->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('payment_due_day')->label('Ngày đến hạn thanh toán')->numeric()->minValue(1)->maxValue(28)->required(),
                        TextInput::make('notes')->label('Ghi chú'),
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
