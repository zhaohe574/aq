<?php

namespace App\Core;

class Api {
    private string $baseUrl;
    private int $timeout;
    private ?string $token;
    private Logger $logger;
    
    public function __construct(string $baseUrl, int $timeout, Logger $logger) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->token = null;
        $this->logger = $logger;
    }
    
    public function setToken(?string $token): void {
        $this->token = $token;
    }
    
    public function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        $headers = ["Content-type: application/json\r\n"];
        if ($this->token) {
            $headers[] = "Authorization: {$this->token}\r\n";
        }
        
        $options = [
            'http' => [
                'header' => implode('', $headers),
                'method' => strtoupper($method),
                'content' => $data ? json_encode($data) : null,
                'ignore_errors' => true,
                'timeout' => $this->timeout,
            ],
        ];
        
        $context = stream_context_create($options);
        
        try {
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                $this->logger->log("API Request failed: " . ($error['message'] ?? 'Unknown error'), 'error');
                return ['code' => 500, 'msg' => 'Request failed'];
            }
            
            $decoded = json_decode($response, true);
            
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->log("JSON decode failed: " . json_last_error_msg(), 'error');
                return ['code' => 500, 'msg' => 'Invalid JSON response'];
            }
            
            return $decoded;
        } catch (\Exception $e) {
            $this->logger->log("API Exception: " . $e->getMessage(), 'error');
            return ['code' => 500, 'msg' => $e->getMessage()];
        }
    }
    
    public function get(string $endpoint): array {
        return $this->request($endpoint, 'GET');
    }
    
    public function post(string $endpoint, array $data): array {
        return $this->request($endpoint, 'POST', $data);
    }
} 