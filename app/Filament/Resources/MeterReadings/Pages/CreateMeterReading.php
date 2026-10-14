<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Filament\Resources\MeterReadings\MeterReadingResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateMeterReading extends CreateRecord
{
    protected static string $resource = MeterReadingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $landlord = auth()->user();
        abort_unless($landlord instanceof User, 403);

        try {
            return app(RecordMeterReadingAction::class)->handle($landlord, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => ['data.'.$field => $errors])->all());
        }
    }
}
