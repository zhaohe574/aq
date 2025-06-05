<?php

return [
    'app' => [
        'name' => 'Auto Exam System',
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
        'log_level' => 'debug',
    ],
    
    'api' => [
        'base_url' => 'http://api.hebeiluhang.com:7000/api',
        'timeout' => 30,
    ],
    
    'auth' => [
        'authorized_users' => [],
        'token_file' => __DIR__ . '/../storage/cache/tokens.json',
    ],
    
    'storage' => [
        'logs_dir' => __DIR__ . '/../storage/logs',
        'cache_dir' => __DIR__ . '/../storage/cache',
        'max_log_size' => 10 * 1024 * 1024, // 10MB
    ],
]; 