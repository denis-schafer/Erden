<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_partner_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('quota_plan_id');
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('pool_fee_amount', 10, 2)->nullable();
            $table->integer('pool_fee_count')->nullable();
            $table->boolean('is_exempt')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('quota_plan_id')->references('id')->on('quota_plans')->onDelete('cascade');
            $table->unique(['partner_id', 'quota_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_partner_config');
    }
};
