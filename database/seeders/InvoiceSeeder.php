<?php

namespace Database\Seeders;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;
use LogicException;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(GenerateInvoiceAction $generateInvoice): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Demo invoices must not be seeded in production.');
        }

        User::query()->each(function (User $landlord) use ($generateInvoice): void {
            Contract::query()->where('status', ContractStatus::Active)
                ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->whereHas('tenant', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->whereDoesntHave('invoices', fn (Builder $query) => $query->where('billing_period', today()->format('Y-m')))
                ->each(function (Contract $contract) use ($landlord, $generateInvoice): void {
                    try {
                        $generateInvoice->handle($landlord, [
                            'contract_id' => $contract->id, 'billing_period' => today()->format('Y-m'),
                            'notes' => 'Demo invoice created by InvoiceSeeder using recorded occupancy history.',
                        ]);
                    } catch (ValidationException $exception) {
                        $this->command?->warn('Skipped contract '.$contract->id.': '.$exception->getMessage());
                    }
                });
        });
    }
}
