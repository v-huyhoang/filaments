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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('replaces_invoice_id')->nullable()->unique()->constrained('invoices')->restrictOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->unsignedTinyInteger('active_billing_key')->nullable()->virtualAs("case when status = 'cancelled' then null else 1 end");
            $table->unique(['contract_id', 'billing_period', 'revision'], 'invoices_period_revision_unique');
            $table->unique(['contract_id', 'billing_period', 'active_billing_key'], 'invoices_active_period_unique');
            $table->dropUnique(['contract_id', 'billing_period']);
        });

        Schema::create('meter_reading_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_reading_id')->constrained()->restrictOnDelete();
            $table->json('before');
            $table->json('after');
            $table->text('reason');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('service_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->boolean('is_active');
            $table->unique(['service_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Billing revisions and audit history must be preserved. Use a forward migration.');
    }
};
