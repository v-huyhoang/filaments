<?php

namespace App\Filament\Resources\RoomReservations;

use App\Filament\Resources\RoomReservations\Pages\CreateRoomReservation;
use App\Filament\Resources\RoomReservations\Pages\ListRoomReservations;
use App\Filament\Resources\RoomReservations\Schemas\RoomReservationForm;
use App\Filament\Resources\RoomReservations\Tables\RoomReservationsTable;
use App\Models\RoomReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomReservationResource extends Resource
{
    protected static ?string $model = RoomReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    public static function getModelLabel(): string
    {
        return __('resources.room_reservation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.room_reservation.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return RoomReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomReservations::route('/'),
            'create' => CreateRoomReservation::route('/create'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['room.property', 'tenant'])
            ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', auth()->id()));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', auth()->id()));
    }
}
