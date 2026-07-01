<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hairsalon_products', 'service_id')) {
            Schema::table('hairsalon_products', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('category_id');
                $table->foreign('service_id')->references('id')->on('hairsalon_services')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hairsalon_products', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });
    }
};
