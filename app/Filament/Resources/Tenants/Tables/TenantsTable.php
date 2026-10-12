<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Enums\TenantStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(fn (): string => __('tables.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(fn (): string => __('tables.phone'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(fn (): string => __('tables.email'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(fn (): string => __('tables.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(fn (): string => __('tables.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(fn (): string => __('tables.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(fn (): string => __('tables.status'))
                    ->options(TenantStatus::class),
                TrashedFilter::make()->label(fn (): string => __('tables.trashed')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
