<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('short_description', 200)->nullable();
            $table->string('long_description', 500)->nullable();
            $table->decimal('amount', 10, 2);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->boolean('enable')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
