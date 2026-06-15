<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('quota_plan_id');
            $table->enum('type', ['regular', 'pool_fee']);
            $table->integer('installment_number');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['cash', 'digital', 'mercadopago'])->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('mp_payment_id', 100)->nullable();
            $table->string('mp_preference_id', 100)->nullable();
            $table->boolean('rendered')->default(false);
            $table->dateTime('rendered_at')->nullable();
            $table->unsignedBigInteger('rendered_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('quota_plan_id')->references('id')->on('quota_plans')->onDelete('cascade');
            $table->foreign('paid_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rendered_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotas');
    }
};
