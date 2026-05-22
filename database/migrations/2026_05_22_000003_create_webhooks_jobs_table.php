<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_parent')->create('webhooks_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_code', 50)->nullable();
            $table->string('company_db', 100)->nullable();
            $table->longText('raw_payload');
            $table->string('topic', 50)->nullable();
            $table->string('payment_id', 50)->nullable();
            $table->enum('status', ['pending', 'processed', 'forwarded'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('forwarded_at')->nullable();
            $table->index(['webhook_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_parent')->dropIfExists('webhooks_jobs');
    }
};
