<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Filament\Resources\RoomReservations\RoomReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoomReservations extends ListRecords
{
    protected static string $resource = RoomReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
