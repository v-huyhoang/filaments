<?php

namespace App\Filament\Resources\Services\Pages;

use App\Actions\Services\UpdateServiceAction;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $landlord = auth()->user();
        abort_unless($landlord instanceof User && $record instanceof Service, 403);

        return app(UpdateServiceAction::class)->handle($landlord, $record, $data);
    }
}
