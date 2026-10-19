<?php

namespace Tests\Feature;

use App\Enums\BillingMethod;
use App\Services\PropertyServicePricing;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PropertyServicePricingTest extends TestCase
{
    #[TestWith(['metered', ['unit_price' => '3500.50'], '3500.50', 0])]
    #[TestWith(['per_person', ['unit_price' => '20000'], '20000', 0])]
    #[TestWith(['fixed', ['fixed_price' => '100000'], 0, '100000'])]
    #[TestWith(['manual', [], 0, 0])]
    #[TestWith(['metered', ['unit_price' => 0], 0, 0])]
    public function test_billing_method_selects_correct_price(string $method, array $prices, string|int $unitPrice, string|int $fixedPrice): void
    {
        $result = app(PropertyServicePricing::class)->validate(BillingMethod::from($method), [
            ...$prices, 'effective_from' => today()->addMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);
        $this->assertSame($unitPrice, $result['unit_price']);
        $this->assertSame($fixedPrice, $result['fixed_price']);
        $this->assertTrue($result['is_enabled']);
    }

    #[TestWith(['metered', []])]
    #[TestWith(['metered', ['unit_price' => -1]])]
    #[TestWith(['metered', ['unit_price' => '1.001']])]
    #[TestWith(['metered', ['unit_price' => '10000000000']])]
    #[TestWith(['metered', ['unit_price' => 1, 'fixed_price' => 1]])]
    #[TestWith(['fixed', ['fixed_price' => -1]])]
    #[TestWith(['fixed', ['fixed_price' => 1, 'unit_price' => 1]])]
    #[TestWith(['per_person', []])]
    #[TestWith(['manual', ['unit_price' => 1]])]
    public function test_invalid_prices_are_rejected(string $method, array $prices): void
    {
        $this->expectException(ValidationException::class);
        app(PropertyServicePricing::class)->validate(BillingMethod::from($method), [...$prices, 'effective_from' => today()->toDateString()]);
    }

    public function test_retroactive_price_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(PropertyServicePricing::class)->validate(BillingMethod::Manual, ['effective_from' => today()->subDay()->toDateString()]);
    }
}
