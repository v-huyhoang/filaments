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
                TextColumn::make('room.property.name')->label('Property')->searchable(),
                TextColumn::make('room.room_number')->label('Room')->searchable(),
                TextColumn::make('tenant.full_name')->label('Tenant')->searchable(),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->placeholder('Open-ended')->sortable(),
                TextColumn::make('rent_amount')->money('VND')->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ContractStatus::Draft->value => 'Draft',
                    ContractStatus::Scheduled->value => 'Scheduled',
                    ContractStatus::Active->value => 'Active',
                    ContractStatus::Expired->value => 'Expired',
                    ContractStatus::Terminated->value => 'Terminated',
                    ContractStatus::Cancelled->value => 'Cancelled',
                ]),
            ])
            ->recordActions([
                Action::make('activate')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Scheduled)
                    ->action(function (Contract $record): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        app(ActivateContractAction::class)->handle($landlord, $record);
                    }),
                Action::make('terminate')
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
