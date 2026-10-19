<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contract_occupants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('full_name');
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'move_in_date', 'move_out_date']);
        });
        Schema::create('contract_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->boolean('use_default')->default(false);
            $table->string('billing_method', 20)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('fixed_price', 12, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['contract_id', 'service_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_services');
        Schema::dropIfExists('contract_occupants');
    }
};
