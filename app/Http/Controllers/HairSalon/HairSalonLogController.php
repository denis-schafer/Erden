<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HairSalonLogController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Log module loaded via Vue']);
    }

    public function getLogs(Request $request)
    {
        $module = $request->input('module', 'hairsalon');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $action = $request->input('action');

        $databaseName = config('database.connections.mysql.database');
        $logDir = storage_path('logs/' . $databaseName);
        $logFile = $logDir . '/hairsalon-log.txt';

        if (!file_exists($logFile)) {
            return response()->json([
                'logs' => [],
                'available_modules' => ['hairsalon'],
                'actions' => [],
            ]);
        }

        $content = file_get_contents($logFile);
        $lines = explode("\n", trim($content));
        $logs = [];
        $actions = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            if (preg_match('/^\[(.*?)\]\s+(\w+):\s+(.+)/', $line, $matches)) {
                $timestamp = $matches[1];
                $logAction = $matches[2];
                $message = $matches[3];

                if (!in_array($logAction, $actions)) {
                    $actions[] = $logAction;
                }

                if ($action && $logAction !== $action) continue;
                if ($startDate && $timestamp < $startDate) continue;
                if ($endDate && $timestamp > $endDate . ' 23:59:59') continue;

                $logs[] = [
                    'timestamp' => $timestamp,
                    'action' => $logAction,
                    'message' => $message,
                ];
            }
        }

        return response()->json([
            'logs' => array_reverse($logs),
            'available_modules' => ['hairsalon'],
            'actions' => $actions,
        ]);
    }
}
