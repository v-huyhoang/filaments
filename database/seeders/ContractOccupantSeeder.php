<?php

namespace Database\Seeders;

use App\Actions\Contracts\SaveContractOccupantAction;
use App\Models\Contract;
use Illuminate\Database\Seeder;
use LogicException;

class ContractOccupantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Demo occupancy must not be seeded in production.');
        }
        Contract::query()->where('status', 'active')->whereDoesntHave('occupants')->whereDoesntHave('invoices')
            ->with(['tenant', 'room.property.landlord'])->each(function (Contract $contract): void {
                app(SaveContractOccupantAction::class)->handle($contract->room->property->landlord, $contract, [
                    'full_name' => $contract->tenant->full_name, 'move_in_date' => $contract->start_date->toDateString(),
                ]);
            });
    }
}
