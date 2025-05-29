<?php

namespace App\Core;

class Logger {
    private string $logDir;
    private int $maxLogSize;
    private string $currentDate;
    
    public function __construct(string $logDir, int $maxLogSize) {
        $this->logDir = $logDir;
        $this->maxLogSize = $maxLogSize;
        $this->currentDate = date('Y-m-d');
        
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }
    
    public function log(string $message, string $type = 'info', array $context = []): void {
        $microtime = microtime(true);
        $milliseconds = sprintf("%03d", ($microtime - floor($microtime)) * 1000);
        $time = date('H:i:s', $microtime) . '.' . $milliseconds;
        
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
        $user = $context['user'] ?? 'Unknown User';
        
        $logEntry = sprintf(
            "[%s %s] [%s] [User:%s] [IP:%s] %s",
            $this->currentDate,
            $time,
            strtoupper($type),
            $user,
            $ip,
            $message
        );
        
        $this->writeLog($logEntry, $type);
    }
    
    private function writeLog(string $logEntry, string $type): void {
        $logFile = sprintf("%s/log_%s.txt", $this->logDir, $this->currentDate);
        
        // Write to main log file
        file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
        
        // Write to error log if necessary
        if (in_array($type, ['error', 'warning'])) {
            $errorLogFile = sprintf("%s/error_%s.txt", $this->logDir, $this->currentDate);
            file_put_contents($errorLogFile, $logEntry . PHP_EOL, FILE_APPEND);
        }
        
        // Rotate log if necessary
        $this->rotateLogIfNeeded($logFile);
    }
    
    private function rotateLogIfNeeded(string $logFile): void {
        if (file_exists($logFile) && filesize($logFile) > $this->maxLogSize) {
            $backupFile = sprintf(
                "%s/log_%s_%s.bak",
                $this->logDir,
                $this->currentDate,
                date('His')
            );
            rename($logFile, $backupFile);
        }
    }
    
    public function formatMessage(string $type, string $title, string $content): string {
        $message = [
            'type' => $type,
            'title' => $title,
            'content' => $content
        ];
        
        return json_encode($message, JSON_UNESCAPED_UNICODE) . "\n";
    }
} 