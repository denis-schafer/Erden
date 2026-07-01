<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hairsalon_cash_movements', 'detail')) {
            Schema::table('hairsalon_cash_movements', function (Blueprint $table) {
                $table->json('detail')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hairsalon_cash_movements', function (Blueprint $table) {
            $table->dropColumn('detail');
        });
    }
};
