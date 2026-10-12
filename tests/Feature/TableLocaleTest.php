<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\RoomReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\TenantStatus;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\Tables\PropertiesTable;
use App\Filament\Resources\RoomReservations\Pages\ListRoomReservations;
use App\Filament\Resources\RoomReservations\Tables\RoomReservationsTable;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Tables\RoomsTable;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Tenants\Tables\TenantsTable;
use Filament\Tables\Table;
use Tests\TestCase;

class TableLocaleTest extends TestCase
{
    public function test_table_columns_and_filters_follow_current_locale(): void
    {
        $tables = [
            [PropertiesTable::configure(Table::make(app(ListProperties::class))), 'name', 'Property name', 'Tên bất động sản', 'is_active'],
            [RoomsTable::configure(Table::make(app(ListRooms::class))), 'room_number', 'Room number', 'Số phòng', 'property'],
            [TenantsTable::configure(Table::make(app(ListTenants::class))), 'full_name', 'Full name', 'Họ và tên', 'status'],
            [ContractsTable::configure(Table::make(app(ListContracts::class))), 'start_date', 'Start date', 'Ngày bắt đầu', 'status'],
            [RoomReservationsTable::configure(Table::make(app(ListRoomReservations::class))), 'hold_amount', 'Holding payment', 'Tiền giữ chỗ', 'status'],
        ];

        foreach (['en', 'vi'] as $locale) {
            app()->setLocale($locale);

            foreach ($tables as [$table, $column, $englishLabel, $vietnameseLabel, $filter]) {
                $this->assertSame($locale === 'en' ? $englishLabel : $vietnameseLabel, $table->getColumn($column)->getLabel());
                $this->assertSame(
                    $filter === 'property' ? ($locale === 'en' ? 'Property' : 'Bất động sản') : ($locale === 'en' ? 'Status' : 'Trạng thái'),
                    $table->getFilter($filter)->getLabel(),
                );
            }
        }
    }

    public function test_filter_options_and_status_badges_follow_current_locale(): void
    {
        $contracts = ContractsTable::configure(Table::make(app(ListContracts::class)));
        $reservations = RoomReservationsTable::configure(Table::make(app(ListRoomReservations::class)));
        $tenants = TenantsTable::configure(Table::make(app(ListTenants::class)));
        $properties = PropertiesTable::configure(Table::make(app(ListProperties::class)));

        foreach (['en', 'vi'] as $locale) {
            app()->setLocale($locale);

            $this->assertSame($locale === 'en' ? 'Draft' : 'Bản nháp', $contracts->getFilter('status')->getOptions()['draft']);
            $this->assertSame($locale === 'en' ? 'Held' : 'Đang giữ chỗ', $reservations->getFilter('status')->getOptions()['held']);
            $this->assertSame($locale === 'en' ? 'Blacklisted' : 'Danh sách hạn chế', $tenants->getFilter('status')->getOptions()['blacklisted']);
            $this->assertSame($locale === 'en' ? 'All' : 'Tất cả', $properties->getFilter('is_active')->getPlaceholder());
            $this->assertSame($locale === 'en' ? 'Active' : 'Đang hoạt động', $properties->getFilter('is_active')->getTrueLabel());
            $this->assertSame($locale === 'en' ? 'Inactive' : 'Ngừng hoạt động', $properties->getFilter('is_active')->getFalseLabel());
            $this->assertSame($locale === 'en' ? 'Unspecified' : 'Không xác định', $contracts->getColumn('end_date')->getPlaceholder());
            $this->assertSame($locale === 'en' ? 'Deleted records' : 'Đã xóa', $properties->getFilter('trashed')->getLabel());
            $this->assertSame($locale === 'en' ? 'Occupied' : 'Đang có người thuê', RoomStatus::OCCUPIED->getLabel());

            foreach ([ContractStatus::class, RoomReservationStatus::class, TenantStatus::class, RoomStatus::class] as $enum) {
                foreach ($enum::cases() as $status) {
                    $this->assertNotEmpty($status->getLabel());
                    $this->assertStringNotContainsString('statuses.', $status->getLabel());
                }
            }
        }

    }
}
