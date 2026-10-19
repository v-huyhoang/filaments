<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Actions\Contracts\ActivateContractAction;
use App\Actions\Contracts\TerminateContractAction;
use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Contract;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

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
                Action::make('manageOccupancy')->label(fn (): string => __('occupancy.manage'))
                    ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record])),
                EditAction::make()->visible(fn (Contract $record): bool => $record->status === ContractStatus::Draft),
                Action::make('invoice')->label(fn (): string => __('workflow.bill_contract'))
                    ->visible(fn (Contract $record): bool => in_array($record->status, [ContractStatus::Active, ContractStatus::Terminated, ContractStatus::Expired], true))
                    ->url(fn (Contract $record): string => InvoiceResource::getUrl('create', ['contract' => $record->id])),
                Action::make('activate')
                    ->label(fn (): string => __('tables.activate'))
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Scheduled)
                    ->action(function (Contract $record, Action $action): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        try {
                            app(ActivateContractAction::class)->handle($landlord, $record);
                        } catch (ValidationException $exception) {
                            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();
                            $action->failure();
                        }
                    }),
                Action::make('terminate')
                    ->label(fn (): string => __('tables.terminate'))
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => __('workflow.end_contract_help'))
                    ->schema([
                        DatePicker::make('terminated_at')->label(fn (): string => __('forms.end_date'))->default(today()->toDateString())->maxDate(today())->required(),
                    ])
                    ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Active)
                    ->action(function (Contract $record, array $data, Action $action): void {
                        $landlord = auth()->user();

                        abort_unless($landlord instanceof User, 403);

                        try {
                            app(TerminateContractAction::class)->handle($landlord, $record, $data['terminated_at']);
                        } catch (ValidationException $exception) {
                            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();
                            $action->failure();
                        }
                    }),
            ]);
    }
}
