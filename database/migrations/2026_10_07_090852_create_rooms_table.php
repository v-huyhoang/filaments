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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('room_number');
            $table->unsignedInteger('floor')->default(1);
            $table->decimal('area', 8, 2)->nullable();
            $table->decimal('base_rent', 12, 2)->default(0);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->unsignedInteger('max_occupants')->default(1);
            $table->string('status')->default('available');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'room_number']);
            $table->index(['property_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
