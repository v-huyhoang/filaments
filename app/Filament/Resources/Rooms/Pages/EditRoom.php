<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Actions\Rooms\SaveRoomAction;
use App\Filament\Resources\Rooms\RoomResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SaveRoomAction::class)->handle(auth()->user(), $data, $record);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => ['data.'.$field => $errors])->all());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
