<?php

namespace App\Filament\Resources\Rooms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property.name')
                    ->label(fn (): string => __('tables.property'))
                    ->searchable(),
                TextColumn::make('room_number')
                    ->label(fn (): string => __('tables.room_number'))
                    ->searchable(),
                TextColumn::make('floor')
                    ->label(fn (): string => __('tables.floor'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('area')
                    ->label(fn (): string => __('tables.area'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('base_rent')
                    ->label(fn (): string => __('tables.base_rent'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deposit_amount')
                    ->label(fn (): string => __('tables.deposit_amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_occupants')
                    ->label(fn (): string => __('tables.max_occupants'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(fn (): string => __('tables.status'))
                    ->badge()
                    ->searchable(),
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
                SelectFilter::make('property')
                    ->label(fn (): string => __('tables.property'))
                    ->relationship('property', 'name', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
                    ->searchable()
                    ->preload(),
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
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
