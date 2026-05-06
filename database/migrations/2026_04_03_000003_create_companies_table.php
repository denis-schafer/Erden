<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_parent')->create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('db');
            $table->string('name');
            $table->unsignedBigInteger('status_id');
            $table->foreign('status_id')->references('id')->on('statuses');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_parent')->dropIfExists('companies');
    }
};