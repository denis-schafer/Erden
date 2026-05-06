<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('route', 100);
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_special')->default(false);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->integer('order')->default(0);
                $table->string('package')->nullable();
                $table->timestamps();
            });
        } else {
            // Add missing columns if table exists
            Schema::table('modules', function (Blueprint $table) {
                if (!Schema::hasColumn('modules', 'description')) {
                    $table->text('description')->nullable()->after('icon');
                }
                if (!Schema::hasColumn('modules', 'package')) {
                    $table->string('package')->nullable()->after('order');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
