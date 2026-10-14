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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('meter_reading_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 50);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->json('metadata');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
