<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 20)->nullable();
            $table->text('detail');
            $table->decimal('total', 10, 2);
            $table->foreignId('operator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('status_id')->constrained('status_orders')->onDelete('cascade');
            $table->boolean('paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
