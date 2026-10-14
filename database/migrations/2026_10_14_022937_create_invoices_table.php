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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('billing_period', 7);
            $table->timestamp('issued_at');
            $table->date('due_date');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('status', 20);
            $table->json('snapshot');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'billing_period']);
            $table->index(['landlord_id', 'status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
