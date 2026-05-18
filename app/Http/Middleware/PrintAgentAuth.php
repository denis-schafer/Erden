<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $request->merge(['_company' => $company]);

        return $next($request);
    }
}
