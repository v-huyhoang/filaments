<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Actions\Contracts\CreateContractAction;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $landlord = auth()->user();

        abort_unless($landlord instanceof User, 403);

        try {
            return app(CreateContractAction::class)->handle($landlord, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => ['data.'.$field => $errors])->all());
        }
    }
}
