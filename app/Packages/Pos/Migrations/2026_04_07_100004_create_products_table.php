<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('short_description', 200)->nullable();
                $table->text('long_description')->nullable();
                $table->decimal('amount', 10, 2);
                $table->foreignId('category_id')->constrained()->onDelete('cascade');
                $table->string('sync_id', 36)->nullable()->unique();
                $table->boolean('enable')->default(true);
                $table->integer('order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'enable')) {
                    $table->boolean('enable')->default(true);
                }
                if (!Schema::hasColumn('products', 'order')) {
                    $table->integer('order')->default(0);
                }
                if (!Schema::hasColumn('products', 'sync_id')) {
                    $table->string('sync_id', 36)->nullable()->unique();
                }
                if (!Schema::hasColumn('products', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
