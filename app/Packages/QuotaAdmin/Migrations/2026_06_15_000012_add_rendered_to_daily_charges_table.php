<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_charges', function (Blueprint $table) {
            $table->boolean('rendered')->default(false);
            $table->decimal('rendered_amount', 10, 2)->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->foreignId('rendered_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_charges', function (Blueprint $table) {
            $table->dropForeign(['rendered_by']);
            $table->dropColumn(['rendered', 'rendered_amount', 'rendered_at', 'rendered_by']);
        });
    }
};
