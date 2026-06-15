<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'digital', 'mercadopago']);
            $table->string('mp_payment_id', 100)->nullable();
            $table->string('mp_preference_id', 100)->nullable();
            $table->string('mp_status', 50)->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->dateTime('paid_at');
            $table->boolean('rendered')->default(false);
            $table->decimal('rendered_amount', 10, 2)->nullable();
            $table->dateTime('rendered_at')->nullable();
            $table->unsignedBigInteger('rendered_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('paid_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rendered_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_payments');
    }
};
