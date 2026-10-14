<?php

namespace App\Filament\Resources\Services\Pages;

use App\Actions\Services\CreateServiceAction;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $landlord = auth()->user();
        abort_unless($landlord instanceof User, 403);

        return app(CreateServiceAction::class)->handle($landlord, $data);
    }
}
