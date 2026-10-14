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
        Schema::create('property_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('fixed_price', 12, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['property_id', 'service_id', 'effective_from'], 'property_service_version_unique');
            $table->index(['property_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_services');
    }
};
