<?php

namespace App\Core;

class Auth {
    private array $authorizedUsers;
    private string $tokenFile;
    private Api $api;
    private Logger $logger;
    
    public function __construct(array $authorizedUsers, string $tokenFile, Api $api, Logger $logger) {
        $this->authorizedUsers = array_map('strtoupper', $authorizedUsers);
        $this->tokenFile = $tokenFile;
        $this->api = $api;
        $this->logger = $logger;
        
        // Ensure token file directory exists
        $tokenDir = dirname($this->tokenFile);
        if (!file_exists($tokenDir)) {
            mkdir($tokenDir, 0777, true);
        }
    }
    
    public function authenticate(string $username, string $password): ?string {
        $username = strtoupper($username);
        
        // Check if user is authorized
        if (!in_array($username, $this->authorizedUsers)) {
            $this->logger->log("Unauthorized access attempt: {$username}", 'error');
            return null;
        }
        
        // Try to get cached token
        $token = $this->getCachedToken($username, $password);
        if ($token && $this->validateToken($token)) {
            return $token;
        }
        
        // Get new token
        $token = $this->login($username, $password);
        if ($token) {
            $this->cacheToken($username, $password, $token);
        }
        
        return $token;
    }
    
    private function getCachedToken(string $username, string $password): ?string {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        
        $tokens = json_decode(file_get_contents($this->tokenFile), true) ?? [];
        
        if (isset($tokens[$username]) &&
            is_array($tokens[$username]) &&
            isset($tokens[$username]['token']) &&
            isset($tokens[$username]['password']) &&
            $tokens[$username]['password'] === $password) {
            return $tokens[$username]['token'];
        }
        
        return null;
    }
    
    private function validateToken(string $token): bool {
        $this->api->setToken($token);
        $response = $this->api->get('system/user/info');
        
        return isset($response['userCode']);
    }
    
    private function login(string $username, string $password): ?string {
        $response = $this->api->post('auth/login', [
            'username' => $username,
            'password' => $password
        ]);
        
        if (isset($response['token'])) {
            return $response['token'];
        }
        
        $this->logger->log("Login failed for user: {$username}", 'error');
        return null;
    }
    
    private function cacheToken(string $username, string $password, string $token): void {
        $tokens = file_exists($this->tokenFile) 
            ? (json_decode(file_get_contents($this->tokenFile), true) ?? [])
            : [];
        
        $tokens[$username] = [
            'token' => $token,
            'password' => $password,
            'timestamp' => time()
        ];
        
        file_put_contents($this->tokenFile, json_encode($tokens));
    }
} 