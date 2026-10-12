<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Actions\Tenants\UpdateTenantAction;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $landlord = auth()->user();

        abort_unless($landlord instanceof User && $record instanceof Tenant, 403);

        return app(UpdateTenantAction::class)->handle($landlord, $record, $data);
    }
}
