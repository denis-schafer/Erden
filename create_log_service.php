<?php

$content = '<?php

namespace App\Services;

class PosLogService
{
    public static function writeLog(string $module, string $action, string $details, ?int $userId = null): void
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path("logs/" . $databaseName);
            
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . "/" . $module . "-log.txt";
            $timestamp = date("Y-m-d H:i:s");
            $userId = $userId ?? (auth()->id() ?? "N/A");
            $logEntry = "[" . $timestamp . "] " . $action . ": " . $details . " | user_id: " . $userId . "\n";
            
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
        } catch (\Exception $e) {
            \Log::error("Error writing POS log: " . $e->getMessage());
        }
    }
    
    public static function readLogs(string $module, ?string $startDate = null, ?string $endDate = null, ?string $action = null): array
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path("logs/" . $databaseName);
            $logFile = $logDir . "/" . $module . "-log.txt";
            
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
                
                if ($startDate && $parsed["timestamp"] < $startDate . " 00:00:00") continue;
                if ($endDate && $parsed["timestamp"] > $endDate . " 23:59:59") continue;
                if ($action && $parsed["action"] !== $action) continue;
                
                $logs[] = $parsed;
            }
            
            usort($logs, function ($a, $b) {
                return strtotime($b["timestamp"]) - strtotime($a["timestamp"]);
            });
            
            return $logs;
            
        } catch (\Exception $e) {
            \Log::error("Error reading POS log: " . $e->getMessage());
            return [];
        }
    }
    
    private static function parseLogLine(string $line): ?array
    {
        $pattern = "/^\\[([\\d\\-: ]+)\\] +(\\w+): +(.*?) +\\| +user_id: +(\\S+)$/";
        
        if (preg_match($pattern, trim($line), $matches)) {
            return [
                "timestamp" => $matches[1],
                "action" => $matches[2],
                "details" => $matches[3],
                "user_id" => $matches[4],
            ];
        }
        
        if (preg_match("/^\\[([\\d\\-: ]+)\\] +(.*)$/", trim($line), $matches)) {
            return [
                "timestamp" => $matches[1],
                "action" => "unknown",
                "details" => $matches[2],
                "user_id" => "N/A",
            ];
        }
        
        return null;
    }
    
    public static function getDatabaseName(): string
    {
        $databaseName = config("database.connections.dynamic.database", "unknown");
        
        if (str_ends_with($databaseName, ".db")) {
            $databaseName = basename($databaseName, ".db");
        }
        
        return $databaseName;
    }
    
    public static function getAvailableModules(): array
    {
        try {
            $databaseName = self::getDatabaseName();
            $logDir = storage_path("logs/" . $databaseName);
            
            if (!is_dir($logDir)) {
                return [];
            }
            
            $files = scandir($logDir);
            $modules = [];
            
            foreach ($files as $file) {
                if (preg_match("/([a-z_]+)-log\\.txt$/", $file, $matches)) {
                    $modules[] = $matches[1];
                }
            }
            
            return array_unique($modules);
            
        } catch (\Exception $e) {
            return [];
        }
    }
}
';

file_put_contents('app/Services/PosLogService.php', $content);
echo "File created successfully\n";
