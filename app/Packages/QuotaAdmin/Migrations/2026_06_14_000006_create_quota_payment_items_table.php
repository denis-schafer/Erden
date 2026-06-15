<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_payment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quota_payment_id');
            $table->unsignedBigInteger('quota_id');
            $table->decimal('amount', 10, 2);

            $table->foreign('quota_payment_id')->references('id')->on('quota_payments')->onDelete('cascade');
            $table->foreign('quota_id')->references('id')->on('quotas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_payment_items');
    }
};
