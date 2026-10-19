<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\ContractOccupant;
use App\Models\User;
use App\Services\OccupancyBilling;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveContractOccupantAction
{
    /** @param array{full_name: string, move_in_date: string, move_out_date?: string|null} $attributes */
    public function handle(User $landlord, Contract $contract, array $attributes, ?ContractOccupant $occupant = null): ContractOccupant
    {
        Gate::forUser($landlord)->authorize('update', $contract);
        $validated = Validator::make($attributes, [
            'full_name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'move_in_date' => ['required', 'date_format:Y-m-d'],
            'move_out_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:move_in_date'],
        ])->validate();
        $validated['full_name'] = trim($validated['full_name']);
        $validated['move_out_date'] = filled($validated['move_out_date'] ?? null) ? $validated['move_out_date'] : null;

        return DB::transaction(function () use ($landlord, $contract, $occupant, $validated): ContractOccupant {
            $contract = Contract::query()->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
                ->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($contract->status === ContractStatus::Cancelled) {
                throw ValidationException::withMessages(['move_in_date' => __('occupancy.contract_closed')]);
            }
            $room = $contract->room()->lockForUpdate()->firstOrFail();
            $existing = $contract->occupants()->lockForUpdate()->get();
            $original = $occupant === null ? null : $existing->firstWhere('id', $occupant->id);
            if ($occupant !== null && $original === null) {
                throw ValidationException::withMessages(['full_name' => __('occupancy.unavailable')]);
            }
            $candidate = new ContractOccupant($validated);
            $lastDay = $contract->end_date?->copy()->addDay();
            if ($contract->terminated_at !== null) {
                $lastDay = $lastDay === null ? $contract->terminated_at->copy()->startOfDay() : $lastDay->min($contract->terminated_at->copy()->startOfDay());
            }
            if ($candidate->move_in_date->lessThan($contract->start_date) || ($lastDay !== null &&
                ($candidate->move_in_date->greaterThan($lastDay) || ($candidate->move_out_date !== null && $candidate->move_out_date->greaterThan($lastDay))))) {
                throw ValidationException::withMessages(['move_in_date' => __('occupancy.outside_contract')]);
            }

            $billing = app(OccupancyBilling::class);
            $invoices = $contract->invoices()->where('status', '!=', InvoiceStatus::Cancelled)->lockForUpdate()->get();
            foreach ($invoices as $invoice) {
                $servicePeriod = array_key_exists('service_billing_period', $invoice->snapshot)
                    ? $invoice->snapshot['service_billing_period'] : $invoice->billing_period;
                if ($servicePeriod === null) {
                    continue;
                }
                $period = CarbonImmutable::createFromFormat('!Y-m', $servicePeriod);
                $after = $billing->interval($contract, $candidate, $period);
                $before = $original === null ? null : $billing->interval($contract, $original, $period);
                if (($after['days'] > 0 || ($before['days'] ?? 0) > 0) &&
                    ($after !== $before || $original?->full_name !== $candidate->full_name)) {
                    throw ValidationException::withMessages(['move_in_date' => __('occupancy.invoice_locked')]);
                }
            }

            $events = [];
            foreach ($existing->reject(fn (ContractOccupant $record): bool => $record->id === $original?->id)->push($candidate) as $record) {
                $start = $record->move_in_date->toDateString();
                $end = $record->move_out_date?->toDateString();
                if ($end === $start) {
                    continue;
                }
                $events[$start] = ($events[$start] ?? 0) + 1;
                if ($end !== null) {
                    $events[$end] = ($events[$end] ?? 0) - 1;
                }
            }
            ksort($events);
            $count = 0;
            foreach ($events as $change) {
                $count += $change;
                if ($count > $room->max_occupants) {
                    throw ValidationException::withMessages(['move_in_date' => __('occupancy.capacity_exceeded')]);
                }
            }

            if ($original !== null) {
                $original->update($validated);

                return $original;
            }

            return $contract->occupants()->create($validated);
        }, attempts: 3);
    }
}
