<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('dni', 20)->nullable();
                $table->json('detail')->nullable();
                $table->decimal('total', 10, 2);
                $table->foreignId('operator_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('status_id')->constrained('status_orders')->onDelete('cascade');
                $table->string('sync_id', 36)->nullable()->unique();
                $table->boolean('paid')->default(false);
                $table->string('mp_payment_id', 50)->nullable();
                $table->decimal('mp_transaction_amount', 10, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
