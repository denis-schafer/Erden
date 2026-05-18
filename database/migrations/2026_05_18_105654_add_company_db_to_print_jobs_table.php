<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('mysql_parent')->hasColumn('print_jobs', 'company_db')) {
            Schema::connection('mysql_parent')->table('print_jobs', function (Blueprint $table) {
                $table->string('company_db')->after('id');
                $table->index(['company_db', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_parent')->table('print_jobs', function (Blueprint $table) {
            $table->dropIndex(['company_db', 'status']);
            $table->dropColumn('company_db');
        });
    }
};
