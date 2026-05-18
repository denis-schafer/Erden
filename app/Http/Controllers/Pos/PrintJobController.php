<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class PrintJobController extends Controller
{
    public function pending(Request $request)
    {
        $jobs = DB::table('print_jobs')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        return response()->json($jobs->map(function ($job) {
            return [
                'id' => $job->id,
                'order_id' => $job->order_id,
                'printer_ip' => $job->printer_ip,
                'printer_port' => $job->printer_port,
                'printer_width' => $job->printer_width,
                'ticket_data' => $job->ticket_data,
            ];
        }));
    }

    public function ack(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:completed,failed',
            'error_message' => 'nullable|string',
        ]);

        $job = DB::table('print_jobs')
            ->where('id', $id)
            ->first();

        if (!$job) {
            return response()->json(['error' => 'Print job not found'], 404);
        }

        DB::table('print_jobs')
            ->where('id', $id)
            ->update([
                'status' => $request->status,
                'error_message' => $request->error_message,
                'processed_at' => now(),
                'attempts' => $job->attempts + 1,
            ]);

        return response()->json(['success' => true]);
    }
}
