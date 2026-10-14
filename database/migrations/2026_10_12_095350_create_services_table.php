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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('billing_method', 20);
            $table->string('unit', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['landlord_id', 'code']);
            $table->index(['landlord_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
