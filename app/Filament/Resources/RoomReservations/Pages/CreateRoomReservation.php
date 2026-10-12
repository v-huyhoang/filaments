<?php

namespace App\Filament\Resources\RoomReservations\Pages;

use App\Actions\Reservations\CreateRoomReservationAction;
use App\Filament\Resources\RoomReservations\RoomReservationResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRoomReservation extends CreateRecord
{
    protected static string $resource = RoomReservationResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $landlord = auth()->user();

        abort_unless($landlord instanceof User, 403);

        return app(CreateRoomReservationAction::class)->handle($landlord, $data);
    }
}
