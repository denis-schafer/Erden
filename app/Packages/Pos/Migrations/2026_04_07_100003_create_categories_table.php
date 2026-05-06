<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->boolean('default')->default(false);
                $table->boolean('enable')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'default')) {
                    $table->boolean('default')->default(false);
                }
                if (!Schema::hasColumn('categories', 'enable')) {
                    $table->boolean('enable')->default(true);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
