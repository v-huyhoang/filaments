<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Models\Contract;
use App\Models\MeterReading;
use App\Models\PropertyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    /**
     * @param  Collection<int, PropertyService>  $configurations
     * @param  Collection<int, MeterReading>  $readings
     * @return array{items: list<array<string, mixed>>, subtotal: string, total: string}
     */
    public function calculate(Contract $contract, Collection $configurations, Collection $readings, CarbonImmutable $period): array
    {
        $items = [];
        if (($contract->end_date === null || $contract->end_date->greaterThanOrEqualTo($period)) &&
            ($contract->terminated_at === null || $contract->terminated_at->copy()->startOfDay()->greaterThan($period))) {
            $rentFrom = $period->max($contract->start_date);
            $rentDays = (int) $rentFrom->diffInDays($period->addMonth(), false);
            $rent = $this->item('rent', __('invoices.rent'), '1', 'month', $contract->rent_amount, [
                'contract_id' => $contract->id, 'billing_period' => $period->format('Y-m'),
                'monthly_rent' => $contract->rent_amount,
                'from' => $rentFrom->toDateString(), 'until' => $period->endOfMonth()->toDateString(),
                'charge_from' => $rentFrom->toDateString(), 'charge_to' => $period->endOfMonth()->toDateString(),
                'days' => $rentDays, 'days_in_month' => $period->daysInMonth,
            ]);
            $rent['amount'] = (string) BigDecimal::of($contract->rent_amount)->multipliedBy($rentDays)
                ->dividedBy($period->daysInMonth, 2, RoundingMode::HalfUp);
            if ($rentDays !== $period->daysInMonth) {
                $rent['unit_price'] = $rent['amount'];
                $rent['unit'] = __('invoices.rental_period');
            }
            $items[] = $rent;
        }
        $servicePeriod = $period->subMonth();
        $serviceFrom = $servicePeriod->max($contract->start_date);
        $serviceTo = $servicePeriod->endOfMonth();
        if ($contract->end_date !== null) {
            $serviceTo = $serviceTo->min($contract->end_date);
        }
        if ($contract->terminated_at !== null) {
            $serviceTo = $serviceTo->min($contract->terminated_at->copy()->startOfDay()->subDay());
        }
        $serviceDates = ['charge_from' => $serviceFrom->toDateString(), 'charge_to' => $serviceTo->toDateString()];
        $readings = $readings->keyBy('service_id');

        foreach ($configurations as $configuration) {
            $service = $configuration->service;
            $method = $service->billing_method;

            if ($method === BillingMethod::Manual) {
                continue;
            }

            $reading = $readings->get($service->id);

            if ($method === BillingMethod::Metered && $reading === null) {
                throw ValidationException::withMessages(['billing_period' => __('invoices.errors.missing_reading', ['service' => $service->name, 'period' => $servicePeriod->format('Y-m')])]);
            }

            if ($method === BillingMethod::PerPerson) {
                $proration = app(OccupancyBilling::class)->calculate($contract, $contract->occupants, $servicePeriod, $configuration->unit_price);
                $amount = BigDecimal::of($proration['amount']);
                $this->ensureMoneyFits($amount);
                $items[] = [
                    'type' => $method->value, 'name' => $service->name,
                    'quantity' => $proration['quantity'], 'unit' => __('occupancy.person_month'),
                    'unit_price' => $configuration->unit_price, 'amount' => $proration['amount'],
                    'metadata' => [...$proration['metadata'], ...$serviceDates, 'service_id' => $service->id, 'service_code' => $service->code,
                        'property_service_id' => $configuration->id, 'contract_service_id' => $configuration->getAttribute('contract_service_id'), 'contract_effective_from' => $configuration->getAttribute('contract_effective_from'),
                        'effective_from' => $configuration->effective_from->toDateString(), 'billing_method' => $method->value],
                    'meter_reading_id' => null,
                ];

                continue;
            }

            $quantity = match ($method) {
                BillingMethod::Metered => $reading->usage,
                default => '1',
            };
            $items[] = $this->item($method->value, $service->name, $quantity, $service->unit,
                $method === BillingMethod::Fixed ? $configuration->fixed_price : $configuration->unit_price,
                [
                    'billing_period' => $servicePeriod->format('Y-m'),
                    ...$serviceDates,
                    'service_id' => $service->id,
                    'service_code' => $service->code,
                    'property_service_id' => $configuration->id,
                    'contract_service_id' => $configuration->getAttribute('contract_service_id'),
                    'contract_effective_from' => $configuration->getAttribute('contract_effective_from'),
                    'effective_from' => $configuration->effective_from->toDateString(),
                    'billing_method' => $method->value,
                    ...($method === BillingMethod::Metered ? ['previous_value' => $reading->previous_value, 'current_value' => $reading->current_value] : []),
                ], $method === BillingMethod::Metered ? $reading->id : null);
        }

        $total = BigDecimal::of('0.00');
        foreach ($items as $item) {
            $total = $total->plus($item['amount']);
        }
        $this->ensureMoneyFits($total);

        return ['items' => $items, 'subtotal' => (string) $total, 'total' => (string) $total];
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function item(string $type, string $name, string $quantity, string $unit, string $unitPrice, array $metadata, ?int $readingId = null): array
    {
        $amount = BigDecimal::of($quantity)->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);
        $this->ensureMoneyFits($amount);

        return ['type' => $type, 'name' => $name, 'quantity' => $quantity, 'unit' => $unit, 'unit_price' => $unitPrice, 'amount' => (string) $amount, 'metadata' => $metadata, 'meter_reading_id' => $readingId];
    }

    private function ensureMoneyFits(BigDecimal $amount): void
    {
        if ($amount->isLessThan(0) || $amount->isGreaterThan('999999999999.99')) {
            throw ValidationException::withMessages(['total' => __('invoices.errors.amount_out_of_range')]);
        }
    }
}
