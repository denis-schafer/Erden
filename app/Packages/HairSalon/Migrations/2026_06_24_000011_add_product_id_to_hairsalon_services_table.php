<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hairsalon_services', 'product_id')) {
            Schema::table('hairsalon_services', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->after('category_id');
                $table->foreign('product_id')->references('id')->on('hairsalon_products')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hairsalon_services', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
