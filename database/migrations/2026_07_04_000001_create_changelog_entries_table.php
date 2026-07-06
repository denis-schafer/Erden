<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('changelog_entries')) {
            Schema::create('changelog_entries', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50);
                $table->string('title');
                $table->text('content');
                $table->boolean('is_published')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
