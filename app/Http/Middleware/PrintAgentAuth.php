<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrintAgentAuth
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Print-Agent-Key');

        if (!$key) {
            return response()->json(['error' => 'Unauthorized - missing API key'], 401);
        }

        $company = DB::connection('mysql_parent')
            ->table('companies')
            ->where('print_agent_key', $key)
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Unauthorized - invalid API key'], 401);
        }

        $this->ensurePrintJobsTable();

        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $request->merge(['_company' => (array) $company]);

        return $next($request);
    }

    private function ensurePrintJobsTable(): void
    {
        if (!Schema::connection('mysql_parent')->hasTable('print_jobs')) {
            Schema::connection('mysql_parent')->create('print_jobs', function ($table) {
                $table->id();
                $table->string('company_db');
                $table->unsignedBigInteger('order_id');
                $table->string('printer_ip', 45);
                $table->string('printer_port', 20)->default('9100');
                $table->string('printer_width', 10)->default('80mm');
                $table->longText('ticket_data');
                $table->string('status')->default('pending');
                $table->text('error_message')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->index(['company_db', 'status']);
            });
        } elseif (!Schema::connection('mysql_parent')->hasColumn('print_jobs', 'company_db')) {
            Schema::connection('mysql_parent')->table('print_jobs', function ($table) {
                $table->string('company_db')->after('id');
                $table->index(['company_db', 'status']);
            });
        }
    }
}
