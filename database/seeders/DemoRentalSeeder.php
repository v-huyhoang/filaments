<?php

namespace Database\Seeders;

use App\Actions\Contracts\ActivateContractAction;
use App\Actions\Contracts\CreateContractAction;
use App\Actions\Contracts\SaveContractOccupantAction;
use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Enums\BillingMethod;
use App\Enums\ContractStatus;
use App\Enums\RoomStatus;
use App\Enums\TenantStatus;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DemoRentalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo rental data may only be seeded in local or testing environments.');
        }

        DB::transaction(function (): void {
            $landlord = User::query()->firstOrCreate(['email' => 'demo-rental@example.test'], [
                'name' => 'Chủ trọ Demo', 'email_verified_at' => now(), 'password' => Hash::make('password'),
            ]);
            $landlord = User::query()->whereKey($landlord->id)->lockForUpdate()->firstOrFail();
            if ($landlord->properties()->withTrashed()->exists()) {
                return;
            }

            $start = CarbonImmutable::today()->startOfMonth()->subMonths(2);
            $property = $landlord->properties()->create([
                'name' => 'Nhà trọ Demo - Thực hành', 'address' => '123 Đường Demo, TP. Hồ Chí Minh', 'is_active' => true,
                'description' => 'D-101: xem hóa đơn và thử lập bản thay thế. D-102: tự lập hóa đơn tháng này. D-103: hợp đồng nháp. D-104: chờ nhận phòng. D-105: phòng trống.',
            ]);
            $services = $this->services($landlord, $property, $start);
            $activeContracts = [];
            foreach (['D-101', 'D-102', 'D-103', 'D-104', 'D-105'] as $index => $number) {
                $room = $property->rooms()->create([
                    'room_number' => $number, 'floor' => 1, 'area' => 25, 'base_rent' => 3_000_000 + $index * 200_000,
                    'deposit_amount' => 3_000_000, 'max_occupants' => 3, 'status' => RoomStatus::AVAILABLE,
                ]);
                if ($number === 'D-105') {
                    continue;
                }
                $active = in_array($number, ['D-101', 'D-102'], true);
                $contract = $this->contract($landlord, $room, $index, $active ? $start : CarbonImmutable::today(), $number === 'D-103');
                if ($active) {
                    $contract = app(ActivateContractAction::class)->handle($landlord, $contract);
                    app(SaveContractOccupantAction::class)->handle($landlord, $contract, [
                        'full_name' => $contract->tenant->full_name, 'move_in_date' => $start->toDateString(),
                    ]);
                    if ($number === 'D-101') {
                        app(SaveContractOccupantAction::class)->handle($landlord, $contract, [
                            'full_name' => 'Trần Thị Bình - Người ở cùng', 'move_in_date' => $start->toDateString(),
                        ]);
                    }
                    $activeContracts[] = $contract;
                    $this->readings($landlord, $room, $services, $start, $index === 0 ? '80' : '40', $index === 0 ? '6' : '3');
                }
            }

            $firstContract = $activeContracts[0];
            $cancelled = app(GenerateInvoiceAction::class)->handle($landlord, [
                'contract_id' => $firstContract->id, 'billing_period' => $start->format('Y-m'),
                'notes' => 'DEMO: Hóa đơn đã hủy để thực hành lập bản thay thế có lý do.',
            ]);
            app(CancelInvoiceAction::class)->handle($landlord, $cancelled);
            foreach ($activeContracts as $contract) {
                app(GenerateInvoiceAction::class)->handle($landlord, [
                    'contract_id' => $contract->id, 'billing_period' => $start->addMonth()->format('Y-m'),
                    'notes' => 'DEMO: Tiền phòng tháng thu và điện nước, dịch vụ tháng trước.',
                ]);
            }
            app(GenerateInvoiceAction::class)->handle($landlord, [
                'contract_id' => $firstContract->id, 'billing_period' => $start->addMonths(2)->format('Y-m'),
                'notes' => 'DEMO: Hóa đơn mẫu đầy đủ tiền phòng, điện, nước, Internet và phí rác theo người.',
            ]);
        }, attempts: 3);
    }

    /** @return array<string, Service> */
    private function services(User $landlord, Property $property, CarbonImmutable $start): array
    {
        $services = [];
        foreach ([
            ['electricity', 'Điện', BillingMethod::Metered, 'kWh', 3500],
            ['water', 'Nước', BillingMethod::Metered, 'm3', 15000],
            ['internet', 'Internet', BillingMethod::Fixed, 'tháng', 100000],
            ['trash', 'Phí rác', BillingMethod::PerPerson, 'người/tháng', 30000],
        ] as [$code, $name, $method, $unit, $price]) {
            $service = $landlord->services()->firstOrCreate(['code' => 'demo_'.$code], [
                'name' => $name, 'billing_method' => $method, 'unit' => $unit, 'is_active' => true,
            ]);
            $property->propertyServices()->create([
                'service_id' => $service->id, 'effective_from' => $start->toDateString(), 'is_enabled' => true,
                'unit_price' => $method === BillingMethod::Fixed ? 0 : $price,
                'fixed_price' => $method === BillingMethod::Fixed ? $price : 0,
            ]);
            $services[$code] = $service;
        }

        return $services;
    }

    private function contract(User $landlord, Room $room, int $index, CarbonImmutable $start, bool $draft): Contract
    {
        $tenant = $landlord->tenants()->create([
            'full_name' => ['Nguyễn Văn An', 'Lê Thị Mai', 'Phạm Minh Tuấn', 'Đỗ Ngọc Lan'][$index],
            'phone' => '090000010'.($index + 1), 'identity_number' => '09900000000'.($index + 1),
            'status' => TenantStatus::Active,
        ]);

        return app(CreateContractAction::class)->handle($landlord, [
            'room_id' => $room->id, 'tenant_id' => $tenant->id,
            'start_date' => $start->toDateString(), 'end_date' => $start->addYear()->subDay()->toDateString(),
            'rent_amount' => $room->base_rent, 'deposit_amount' => $room->deposit_amount, 'payment_due_day' => 5,
            'status' => $draft ? ContractStatus::Draft->value : ContractStatus::Scheduled->value,
            'notes' => 'DEMO: '.$room->room_number.' - '.($draft ? 'Sửa thông tin, lên lịch và nhận phòng.' : 'Thực hành quy trình thuê và lập hóa đơn.'),
        ]);
    }

    /** @param array<string, Service> $services */
    private function readings(User $landlord, Room $room, array $services, CarbonImmutable $start, string $electricityUsage, string $waterUsage): void
    {
        foreach (['electricity' => ['1000', $electricityUsage], 'water' => ['50', $waterUsage]] as $code => [$opening, $usage]) {
            $previous = BigDecimal::of($opening);
            foreach ([$start, $start->addMonth()] as $month) {
                $current = $previous->plus($usage);
                app(RecordMeterReadingAction::class)->handle($landlord, [
                    'room_id' => $room->id, 'service_id' => $services[$code]->id, 'billing_period' => $month->format('Y-m'),
                    'previous_value' => (string) $previous, 'current_value' => (string) $current, 'notes' => 'DEMO: Chỉ số điện nước phục vụ hóa đơn mẫu.',
                ]);
                $previous = $current;
            }
        }
    }
}
