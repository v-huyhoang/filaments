<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Actions\Properties\CreatePropertyAction;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    private CreatePropertyAction $createPropertyAction;

    public function boot(CreatePropertyAction $createPropertyAction): void
    {
        $this->createPropertyAction = $createPropertyAction;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return $this->createPropertyAction->handle(
            $this->landlord(),
            $data,
        );
    }

    private function landlord(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }
}
