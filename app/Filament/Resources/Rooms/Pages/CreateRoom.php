<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Actions\Rooms\SaveRoomAction;
use App\Filament\Resources\Rooms\RoomResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SaveRoomAction::class)->handle(auth()->user(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => ['data.'.$field => $errors])->all());
        }
    }
}
