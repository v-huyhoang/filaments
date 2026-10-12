<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Actions\Tenants\CreateTenantAction;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $landlord = auth()->user();

        abort_unless($landlord instanceof User, 403);

        return app(CreateTenantAction::class)->handle($landlord, $data);
    }
}
