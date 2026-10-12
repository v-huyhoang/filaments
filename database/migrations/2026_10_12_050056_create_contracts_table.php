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
        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->constrained()->restrictOnDelete();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('room_reservation_id')->nullable()->unique();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->decimal('rent_amount', 12, 2);
                $table->decimal('deposit_amount', 12, 2)->default(0);
                $table->unsignedTinyInteger('payment_due_day');
                $table->string('status')->default('draft');
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('terminated_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['room_id', 'status']);
                $table->index(['tenant_id', 'status']);
                $table->index(['room_id', 'start_date', 'end_date']);
            });
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasIndex('contracts', 'contracts_room_reservation_id_unique')) {
                $table->unique('room_reservation_id');
            }

            if (! Schema::hasIndex('contracts', 'contracts_room_id_status_index')) {
                $table->index(['room_id', 'status']);
            }

            if (! Schema::hasIndex('contracts', 'contracts_tenant_id_status_index')) {
                $table->index(['tenant_id', 'status']);
            }

            if (! Schema::hasIndex('contracts', 'contracts_room_id_start_date_end_date_index')) {
                $table->index(['room_id', 'start_date', 'end_date']);
            }

            if (! Schema::hasForeignKey('contracts', ['room_id'])) {
                $table->foreign('room_id')->references('id')->on('rooms')->restrictOnDelete();
            }

            if (! Schema::hasForeignKey('contracts', ['tenant_id'])) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
