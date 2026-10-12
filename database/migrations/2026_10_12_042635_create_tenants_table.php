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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('identity_number', 50)->nullable();
            $table->date('identity_issued_date')->nullable();
            $table->string('identity_issued_place')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['landlord_id', 'identity_number']);
            $table->index(['landlord_id', 'status']);
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
