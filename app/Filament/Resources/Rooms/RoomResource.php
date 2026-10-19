<?php

namespace App\Filament\Resources\Rooms;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Pages\ViewRoom;
use App\Filament\Resources\Rooms\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Rooms\RelationManagers\MeterReadingsRelationManager;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Resources\Rooms\Tables\RoomsTable;
use App\Models\Room;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public static function getModelLabel(): string
    {
        return __('resources.room.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.room.plural_label');
    }

    protected static ?string $recordTitleAttribute = 'room_number';

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'meter-readings' => MeterReadingsRelationManager::class,
            'invoices' => InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'view' => ViewRoom::route('/{record}'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
            ->withCount([
                'meterReadings as previous_month_readings_count' => fn (Builder $query) => $query
                    ->where('billing_period', today()->startOfMonth()->subMonth()->format('Y-m'))
                    ->whereHas('service', fn (Builder $query) => $query->where('landlord_id', auth()->id())),
                'invoices as unpaid_invoices_count' => fn (Builder $query) => $query
                    ->where('invoices.landlord_id', auth()->id())
                    ->where('invoices.total', '>', 0)
                    ->whereIn('invoices.status', [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue]),
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', auth()->id()));
    }
}
