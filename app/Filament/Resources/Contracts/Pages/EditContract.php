<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Actions\Contracts\UpdateDraftContractAction;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function authorizeAccess(): void
    {
        Gate::authorize('editDraft', $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateDraftContractAction::class)->handle(auth()->user(), $record, $data);
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
