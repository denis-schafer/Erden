<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_rate_id')->nullable()->constrained('daily_charge_rates')->nullOnDelete();
            $table->string('person_name');
            $table->string('person_dni', 50)->nullable();
            $table->decimal('amount', 10, 2);
            $table->integer('quantity')->nullable()->default(1);
            $table->enum('payment_method', ['cash', 'digital'])->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('charged_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_charges');
    }
};
