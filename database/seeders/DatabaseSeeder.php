<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call(UserSeeder::class);
        $this->call(PropertySeeder::class);
        $this->call(TenantSeeder::class);
        $this->call(RoomSeeder::class);
        $this->call(ContractSeeder::class);
        $this->call(RoomReservationSeeder::class);
        $this->call(ServiceSeeder::class);
        $this->call(PropertyServiceSeeder::class);
        $this->call(MeterReadingSeeder::class);
    }
}
