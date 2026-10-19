<?php

namespace Database\Seeders;

use App\Actions\Contracts\ConfigureContractServiceAction;
use App\Models\Contract;
use Illuminate\Database\Seeder;
use LogicException;

class ContractServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Demo contract services must not be seeded in production.');
        }
        Contract::query()->where('status', 'active')->with(['room.property.propertyServices', 'room.property.landlord'])
            ->each(function (Contract $contract): void {
                $effective = today()->addMonthNoOverflow()->startOfMonth();
                foreach ($contract->room->property->propertyServices()->whereHas('service', fn ($query) => $query->where('is_active', true))->get()->unique('service_id') as $configuration) {
                    if (! $contract->contractServices()->where('service_id', $configuration->service_id)->exists()) {
                        app(ConfigureContractServiceAction::class)->handle($contract->room->property->landlord, $contract, [
                            'service_id' => $configuration->service_id, 'use_default' => true, 'effective_from' => $effective->toDateString(),
                        ]);
                    }
                }
            });
    }
}
