<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractOccupant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OccupancyBilling
{
    /** @return array{from: string, until: string, days: int} */
    public function interval(Contract $contract, ContractOccupant $occupant, CarbonImmutable $period): array
    {
        $from = $period->startOfMonth()->max($contract->start_date)->max($occupant->move_in_date);
        $until = $period->startOfMonth()->addMonth();
        if ($contract->end_date !== null) {
            $until = $until->min($contract->end_date->copy()->addDay());
        }
        if ($contract->terminated_at !== null) {
            $until = $until->min($contract->terminated_at->copy()->startOfDay());
        }
        if ($occupant->move_out_date !== null) {
            $until = $until->min($occupant->move_out_date);
        }

        return ['from' => $from->toDateString(), 'until' => $until->toDateString(), 'days' => max(0, (int) $from->diffInDays($until, false))];
    }

    /** @param Collection<int, ContractOccupant> $occupants
     * @return array{amount: string, quantity: string, metadata: array<string, mixed>}
     */
    public function calculate(Contract $contract, Collection $occupants, CarbonImmutable $period, string $unitPrice): array
    {
        if ($occupants->isEmpty()) {
            throw ValidationException::withMessages(['billing_period' => __('occupancy.missing_occupants')]);
        }
        $details = $occupants->map(fn (ContractOccupant $occupant): array => [
            'occupant_id' => $occupant->id, 'full_name' => $occupant->full_name, ...$this->interval($contract, $occupant, $period),
        ])->filter(fn (array $detail): bool => $detail['days'] > 0)->values()->all();
        $days = array_sum(array_column($details, 'days'));
        $amount = BigDecimal::of($unitPrice)->multipliedBy($days)->dividedBy($period->daysInMonth, 0, RoundingMode::HalfUp)->toScale(2);

        return [
            'amount' => (string) $amount,
            'quantity' => (string) BigDecimal::of($days)->dividedBy($period->daysInMonth, 3, RoundingMode::HalfUp),
            'metadata' => ['billing_period' => $period->format('Y-m'), 'person_days' => $days, 'days_in_month' => $period->daysInMonth, 'date_rule' => 'move_in_inclusive_move_out_exclusive', 'rounding' => 'half_up_whole_vnd_after_sum', 'occupants' => $details],
        ];
    }
}
