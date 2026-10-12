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
                TextColumn::make('room.property.name')->label(fn (): string => __('tables.property'))->searchable(),
                TextColumn::make('room.room_number')->label(fn (): string => __('tables.room'))->searchable(),
                TextColumn::make('tenant.full_name')->label(fn (): string => __('tables.tenant'))->searchable(),
                TextColumn::make('start_date')->label(fn (): string => __('tables.start_date'))->date()->sortable(),
                TextColumn::make('end_date')->label(fn (): string => __('tables.end_date'))->date()->placeholder(fn (): string => __('tables.unspecified'))->sortable(),
                TextColumn::make('rent_amount')->label(fn (): string => __('tables.rent_amount'))->money('VND')->sortable(),
                TextColumn::make('status')->label(fn (): string => __('tables.status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(fn (): string => __('tables.status'))->options(ContractStatus::class),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label(fn (): string => __('tables.activate'))
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Scheduled)
                    ->action(function (Contract $record): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        app(ActivateContractAction::class)->handle($landlord, $record);
                    }),
                Action::make('terminate')
                    ->label(fn (): string => __('tables.terminate'))
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
