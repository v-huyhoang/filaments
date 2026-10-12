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
        if (! Schema::hasTable('room_reservations')) {
            Schema::create('room_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->constrained()->restrictOnDelete();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->date('expected_move_in_date');
                $table->decimal('hold_amount', 12, 2);
                $table->timestamp('paid_at');
                $table->string('payment_method', 50);
                $table->string('payment_reference')->nullable();
                $table->timestamp('expires_at');
                $table->string('status')->default('held');
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->decimal('refunded_amount', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['room_id', 'status', 'expires_at']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasForeignKey('contracts', ['room_reservation_id'])) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreign('room_reservation_id')
                    ->references('id')
                    ->on('room_reservations')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasForeignKey('contracts', ['room_reservation_id'])) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['room_reservation_id']);
            });
        }

        Schema::dropIfExists('room_reservations');
    }
};
