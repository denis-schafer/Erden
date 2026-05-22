<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class WebhookJobController extends Controller
{
    public function pending(Request $request)
    {
        $company = $request->_company;
        $webhookCode = $company['webhook_code'] ?? null;

        if (empty($webhookCode)) {
            return response()->json([]);
        }

        $jobs = DB::connection('mysql_parent')
            ->table('webhooks_jobs')
            ->where('webhook_code', $webhookCode)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        return response()->json($jobs->map(function ($job) {
            return [
                'id' => $job->id,
                'raw_payload' => $job->raw_payload,
                'topic' => $job->topic,
                'webhook_code' => $job->webhook_code,
                'created_at' => $job->created_at,
            ];
        }));
    }

    public function ack(Request $request, $id)
    {
        $company = $request->_company;
        $webhookCode = $company['webhook_code'] ?? null;

        if (empty($webhookCode)) {
            return response()->json(['error' => 'Webhook jobs not available for this company'], 404);
        }

        $job = DB::connection('mysql_parent')
            ->table('webhooks_jobs')
            ->where('webhook_code', $webhookCode)
            ->where('id', $id)
            ->first();

        if (!$job) {
            return response()->json(['error' => 'Webhook job not found'], 404);
        }

        DB::connection('mysql_parent')
            ->table('webhooks_jobs')
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
