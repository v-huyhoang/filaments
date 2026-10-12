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
                    ->label('Bất động sản')
                    ->searchable(),
                TextColumn::make('room_number')
                    ->label('Số phòng')
                    ->searchable(),
                TextColumn::make('floor')
                    ->label('Tầng')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('area')
                    ->label('Diện tích (m²)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('base_rent')
                    ->label('Giá thuê cơ bản')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deposit_amount')
                    ->label('Tiền cọc')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_occupants')
                    ->label('Số người ở tối đa')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Cập nhật lúc')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('property')
                    ->label('Bất động sản')
                    ->relationship('property', 'name', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
                    ->searchable()
                    ->preload(),
                TrashedFilter::make()->label('Đã xóa'),
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
