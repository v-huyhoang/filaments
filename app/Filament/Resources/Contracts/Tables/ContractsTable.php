<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Actions\Contracts\ActivateContractAction;
use App\Actions\Contracts\TerminateContractAction;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.property.name')->label('Bất động sản')->searchable(),
                TextColumn::make('room.room_number')->label('Phòng')->searchable(),
                TextColumn::make('tenant.full_name')->label('Người thuê')->searchable(),
                TextColumn::make('start_date')->label('Ngày bắt đầu')->date()->sortable(),
                TextColumn::make('end_date')->label('Ngày kết thúc')->date()->placeholder('Không xác định')->sortable(),
                TextColumn::make('rent_amount')->label('Giá thuê')->money('VND')->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options([
                    ContractStatus::Draft->value => 'Bản nháp',
                    ContractStatus::Scheduled->value => 'Đã lên lịch',
                    ContractStatus::Active->value => 'Đang hiệu lực',
                    ContractStatus::Expired->value => 'Đã hết hạn',
                    ContractStatus::Terminated->value => 'Đã chấm dứt',
                    ContractStatus::Cancelled->value => 'Đã hủy',
                ]),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Kích hoạt')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Scheduled)
                    ->action(function (Contract $record): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        app(ActivateContractAction::class)->handle($landlord, $record);
                    }),
                Action::make('terminate')
                    ->label('Chấm dứt')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Active)
                    ->action(function (Contract $record): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        app(TerminateContractAction::class)->handle($landlord, $record);
                    }),
            ]);
    }
}
