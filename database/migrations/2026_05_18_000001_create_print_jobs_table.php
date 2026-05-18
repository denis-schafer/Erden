<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_parent')->create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('company_db');
            $table->unsignedBigInteger('order_id');
            $table->string('printer_ip', 45);
            $table->unsignedInteger('printer_port')->default(9100);
            $table->unsignedTinyInteger('printer_width')->default(80);
            $table->json('ticket_data');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['company_db', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_parent')->dropIfExists('print_jobs');
    }
};
