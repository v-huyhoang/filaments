<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\BillingMethod;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(fn (): string => __('services.fields.name'))->searchable()->sortable(),
            TextColumn::make('code')->label(fn (): string => __('services.fields.code'))->searchable(),
            TextColumn::make('billing_method')->label(fn (): string => __('services.fields.billing_method'))->badge(),
            TextColumn::make('unit')->label(fn (): string => __('services.fields.unit')),
            IconColumn::make('is_active')->label(fn (): string => __('services.fields.is_active'))->boolean(),
        ])->filters([
            SelectFilter::make('billing_method')->label(fn (): string => __('services.fields.billing_method'))->options(BillingMethod::class),
            TernaryFilter::make('is_active')->label(fn (): string => __('tables.status'))
                ->placeholder(fn (): string => __('tables.all'))
                ->trueLabel(fn (): string => __('tables.active'))
                ->falseLabel(fn (): string => __('tables.inactive')),
        ])->recordActions([EditAction::make()]);
    }
}
