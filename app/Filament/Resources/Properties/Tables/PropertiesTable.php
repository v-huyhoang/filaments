<?php

namespace App\Filament\Resources\Properties\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(fn (): string => __('tables.property_name'))
                    ->searchable(),
                TextColumn::make('address')
                    ->label(fn (): string => __('tables.address'))
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label(fn (): string => __('tables.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('tables.active') : __('tables.inactive')),
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
                TernaryFilter::make('is_active')
                    ->label(fn (): string => __('tables.status'))
                    ->placeholder(fn (): string => __('tables.all'))
                    ->trueLabel(fn (): string => __('tables.active'))
                    ->falseLabel(fn (): string => __('tables.inactive')),
                TrashedFilter::make()->label(fn (): string => __('tables.trashed')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
