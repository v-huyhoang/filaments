<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label(fn (): string => __('invoices.fields.number'))->sortable(),
                TextColumn::make('billing_period')->label(fn (): string => __('invoices.fields.billing_period'))->sortable()->searchable(),
                TextColumn::make('snapshot.property_name')->label(fn (): string => __('invoices.fields.property')),
                TextColumn::make('snapshot.room_number')->label(fn (): string => __('invoices.fields.room')),
                TextColumn::make('snapshot.tenant_name')->label(fn (): string => __('invoices.fields.tenant')),
                TextColumn::make('status')->label(fn (): string => __('invoices.fields.status'))->badge(),
                TextColumn::make('due_date')->label(fn (): string => __('invoices.fields.due_date'))->date()->sortable(),
                TextColumn::make('total')->label(fn (): string => __('invoices.fields.total'))->money('VND', decimalPlaces: 2)->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(fn (): string => __('invoices.fields.status'))->options(InvoiceStatus::class),
                Filter::make('billing_period')->schema([
                    TextInput::make('billing_period')->label(fn (): string => __('invoices.fields.billing_period'))->rule('date_format:Y-m'),
                ])->query(fn (Builder $query, array $data): Builder => $query->when($data['billing_period'] ?? null, fn (Builder $query, string $period) => $query->where('billing_period', $period))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }
}
