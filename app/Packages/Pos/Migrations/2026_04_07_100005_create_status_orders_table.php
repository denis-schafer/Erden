<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('status_orders')) {
            Schema::create('status_orders', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('sync_id', 36)->nullable()->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('status_orders');
    }
};
