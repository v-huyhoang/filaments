<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Rooms\RoomResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewRoom extends ViewRecord
{
    protected static string $resource = RoomResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('property.name')->label(fn (): string => __('tables.property')),
            TextEntry::make('room_number')->label(fn (): string => __('tables.room_number')),
            TextEntry::make('status')->label(fn (): string => __('tables.status'))->badge(),
            TextEntry::make('base_rent')->label(fn (): string => __('tables.base_rent'))->money('VND'),
            TextEntry::make('max_occupants')->label(fn (): string => __('tables.max_occupants')),
            TextEntry::make('billing_help')->hiddenLabel()->state(fn (): string => __('resources.room.billing_help')),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
