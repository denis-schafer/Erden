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
                $table->string('sync_id', 36)->nullable()->unique();
                $table->boolean('default')->default(false);
                $table->integer('order')->default(0);
                $table->boolean('enable')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'default')) {
                    $table->boolean('default')->default(false);
                }
                if (!Schema::hasColumn('categories', 'order')) {
                    $table->integer('order')->default(0);
                }
                if (!Schema::hasColumn('categories', 'enable')) {
                    $table->boolean('enable')->default(true);
                }
                if (!Schema::hasColumn('categories', 'sync_id')) {
                    $table->string('sync_id', 36)->nullable()->unique();
                }
                if (!Schema::hasColumn('categories', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
