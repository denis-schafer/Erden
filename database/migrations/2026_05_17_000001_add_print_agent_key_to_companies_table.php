<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_parent')->table('companies', function (Blueprint $table) {
            $table->string('print_agent_key', 64)->nullable()->unique()->after('db');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_parent')->table('companies', function (Blueprint $table) {
            $table->dropColumn('print_agent_key');
        });
    }
};
