<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Models\Contract;
use App\Models\MeterReading;
use App\Models\PropertyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    /**
     * @param  Collection<int, PropertyService>  $configurations
     * @param  Collection<int, MeterReading>  $readings
     * @return array{items: list<array<string, mixed>>, subtotal: string, total: string}
     */
    public function calculate(Contract $contract, Collection $configurations, Collection $readings, ?int $occupantCount): array
    {
        $items = [$this->item('rent', __('invoices.rent'), '1', 'month', $contract->rent_amount, ['contract_id' => $contract->id])];
        $readings = $readings->keyBy('service_id');

        foreach ($configurations as $configuration) {
            $service = $configuration->service;
            $method = $service->billing_method;

            if ($method === BillingMethod::Manual) {
                continue;
            }

            $reading = $readings->get($service->id);

            if ($method === BillingMethod::Metered && $reading === null) {
                throw ValidationException::withMessages(['billing_period' => __('invoices.errors.missing_reading', ['service' => $service->name])]);
            }

            if ($method === BillingMethod::PerPerson && $occupantCount === null) {
                throw ValidationException::withMessages(['occupant_count' => __('invoices.errors.occupant_count_required')]);
            }

            $quantity = match ($method) {
                BillingMethod::Metered => $reading->usage,
                BillingMethod::PerPerson => (string) $occupantCount,
                default => '1',
            };
            $items[] = $this->item($method->value, $service->name, $quantity, $service->unit,
                $method === BillingMethod::Fixed ? $configuration->fixed_price : $configuration->unit_price,
                [
                    'service_id' => $service->id,
                    'service_code' => $service->code,
                    'property_service_id' => $configuration->id,
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
