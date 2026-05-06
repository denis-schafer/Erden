<?php

namespace App\Services;

class PosLogService
{
    public static function writeLog(string $module, string $action, string $details, ?int $userId = null): void
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path('logs/' . $databaseName);
            
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/' . $module . '-log.txt';
            $timestamp = date('Y-m-d H:i:s');
            
            // Get user ID: first check parameter, then session, then auth
            if ($userId === null) {
                // Try to get from session first (most reliable in web context)
                $sessionUser = session('user');
                if ($sessionUser && isset($sessionUser['id'])) {
                    $userId = $sessionUser['id'];
                } elseif (auth()->check()) {
                    $userId = auth()->id();
                } elseif (request() && request()->user()) {
                    $userId = request()->user()->id;
                } else {
                    $userId = 'N/A';
                }
            }
            
            // Get username for better logging
            $username = 'N/A';
            if ($userId !== 'N/A') {
                // Try session first
                $sessionUser = session('user');
                if ($sessionUser && isset($sessionUser['username'])) {
                    $username = $sessionUser['username'];
                } else {
                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        $username = $user->username;
                    }
                }
            }
            
            $logEntry = '[' . $timestamp . '] ' . $action . ': ' . $details . ' | user_id: ' . $userId . ' | username: ' . $username . "\n";
            
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
        } catch (\Exception $e) {
            \Log::error('Error writing POS log: ' . $e->getMessage());
        }
    }
    
    public static function readLogs(string $module, ?string $startDate = null, ?string $endDate = null, ?string $action = null): array
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path('logs/' . $databaseName);
            $logFile = $logDir . '/' . $module . '-log.txt';
            
            if (!file_exists($logFile)) {
                return [];
            }
            
            $content = file_get_contents($logFile);
            $lines = explode("\n", trim($content));
            $logs = [];
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                $parsed = self::parseLogLine($line);
                if (!$parsed) continue;
                
                if ($startDate && $parsed['timestamp'] < $startDate . ' 00:00:00') continue;
                if ($endDate && $parsed['timestamp'] > $endDate . ' 23:59:59') continue;
                if ($action && $parsed['action'] !== $action) continue;
                
                $logs[] = $parsed;
            }
            
            usort($logs, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            return $logs;
            
        } catch (\Exception $e) {
            \Log::error('Error reading POS log: ' . $e->getMessage());
            return [];
        }
    }
    
    private static function parseLogLine(string $line): ?array
    {
        // New format: [timestamp] action: details | user_id: X | username: Y
        $pattern = '/^\[([\d\-: ]+)\]\s+(\w+):\s+(.*?)\s+\|\s+user_id:\s+(\S+)\s+\|\s+username:\s+(\S+)$/';        
        if (preg_match($pattern, trim($line), $matches)) {
            return [
                'timestamp' => $matches[1],
                'action' => $matches[2],
                'details' => $matches[3],
                'user_id' => $matches[4],
                'username' => $matches[5] ?? 'N/A',
                'module' => 'unknown',
            ];
        }
        
        // Old format: [timestamp] action: details | user_id: X
        $pattern = '/^\[([\d\-: ]+)\]\s+(\w+):\s+(.*?)\s+\|\s+user_id:\s+(\S+)$/';        
        if (preg_match($pattern, trim($line), $matches)) {
            return [
                'timestamp' => $matches[1],
                'action' => $matches[2],
                'details' => $matches[3],
                'user_id' => $matches[4],
                'username' => 'N/A',
                'module' => 'unknown',
            ];
        }
        
        if (preg_match('/^\[([\d\-: ]+)\]\s+(.*)$/', trim($line), $matches)) {
            return [
                'timestamp' => $matches[1],
                'action' => 'unknown',
                'details' => $matches[2],
                'user_id' => 'N/A',
                'username' => 'N/A',
                'module' => 'unknown',
            ];
        }
        
        return null;
    }
        
        // Old format: [timestamp] action: details | user_id: X
        $pattern = '/^\[([\d\-: ]+)\]\s+(\w+):\s+(.*?)\s+\|\s+user_id:\s+(\S+)$/';
        
        if (preg_match($pattern, trim($line), $matches)) {
            return [
                'timestamp' => $matches[1],
                'action' => $matches[2],
                'details' => $matches[3],
                'user_id' => $matches[4],
                'username' => 'N/A',
            ];
        }
        
        if (preg_match('/^\[([\d\-: ]+)\]\s+(.*)$/', trim($line), $matches)) {
            return [
                'timestamp' => $matches[1],
                'action' => 'unknown',
                'details' => $matches[2],
                'user_id' => 'N/A',
                'username' => 'N/A',
            ];
        }
        
        return null;
    }
    
    public static function getDatabaseName(): string
    {
        $databaseName = config('database.connections.dynamic.database') 
            ?? config('database.connections.mysql.database') 
            ?? 'unknown';
        
        if (str_ends_with($databaseName, '.db')) {
            $databaseName = basename($databaseName, '.db');
        }
        
        return $databaseName;
    }
    
    public static function readAllLogs(?string $startDate = null, ?string $endDate = null, ?string $action = null): array
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path('logs/' . $databaseName);
            
            if (!is_dir($logDir)) {
                return [];
            }
            
            $allLogs = [];
            $files = scandir($logDir);
            
            foreach ($files as $file) {
                if (preg_match('/([a-z_]+)-log\.txt$/', $file, $matches)) {
                    $module = $matches[1];
                    $moduleLogs = self::readLogs($module, $startDate, $endDate, $action);
                    // Add module name to each log entry
                    foreach ($moduleLogs as &$log) {
                        $log['module'] = $module;
                    }
                    $allLogs = array_merge($allLogs, $moduleLogs);
                }
            }
            
            // Sort by timestamp descending
            usort($allLogs, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            return $allLogs;
            
        } catch (\Exception $e) {
            \Log::error('Error reading all POS logs: ' . $e->getMessage());
            return [];
        }
    }
    
    public static function getAvailableModules(): array
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path('logs/' . $databaseName);
            
            if (!is_dir($logDir)) {
                return [];
            }
            
            $files = scandir($logDir);
            $modules = [];
            
            foreach ($files as $file) {
                if (preg_match('/([a-z_]+)-log\.txt$/', $file, $matches)) {
                    $modules[] = $matches[1];
                }
            }
            
            return array_unique($modules);
            
        } catch (\Exception $e) {
            return [];
        }
    }
}

