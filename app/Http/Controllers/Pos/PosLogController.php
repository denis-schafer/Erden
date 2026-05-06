<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PosLogService;
use Illuminate\Support\Facades\Storage;

class PosLogController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Log module loaded via Vue']);
    }
    
    public function getLogs(Request $request)
    {
        $module = $request->input('module', 'productos');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $action = $request->input('action');
        
        // If module is empty (Todos), read all available modules and merge
        if (empty($module)) {
            $logs = PosLogService::readAllLogs($startDate, $endDate, $action);
        } else {
            $logs = PosLogService::readLogs($module, $startDate, $endDate, $action);
        }
        
        $availableModules = PosLogService::getAvailableModules();
        
        // Get actions for selected module (or empty if Todos)
        if (!empty($module)) {
            $actions = $this->getModuleActions($module);
        } else {
            $actions = [];
        }
        
        return response()->json([
            'logs' => $logs,
            'available_modules' => $availableModules,
            'actions' => $actions,
        ]);
    }
    
    private function getModuleActions(string $module): array
    {
        try {
            $databaseName = PosLogService::getDatabaseName();
            $logDir = storage_path('logs/' . $databaseName);
            $logFile = $logDir . '/' . $module . '-log.txt';
            
            if (!file_exists($logFile)) {
                return [];
            }
            
            $content = file_get_contents($logFile);
            $lines = explode("\n", trim($content));
            $actions = [];
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                if (preg_match('/^\[.*?\]\s+(\w+):/', $line, $matches)) {
                    if (!in_array($matches[1], $actions)) {
                        $actions[] = $matches[1];
                    }
                }
            }
            
            return $actions;
            
        } catch (\Exception $e) {
            return [];
        }
    }
}
