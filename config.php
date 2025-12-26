<?php
/**
 * 系统配置文件
 * 
 * 所有系统配置项集中管理
 * 支持环境变量覆盖（可选）
 */

return [
    // API配置
    'api_base_url' => getenv('API_BASE_URL') ?: 'http://api.hebeiluhang.com:7000/api',
    
    // 文件配置
    'token_file' => 'tokens.json',
    'log_dir' => 'logs',
    'err_log_dir' => 'errs',
    
    // 授权用户列表
    'authorized_users' => [
        'JS05533', 'JS02319', 'JS03912', 'JS01521', 'JS00003', 
        'JS05764', 'JS01806', 'JS00949', 'JS02466'
    ],
    
    // API请求配置
    'api_timeout' => 120,        // API请求超时时间（秒），默认120秒
    'api_max_retries' => 3,      // 最大重试次数，默认3次
    'api_retry_delay' => 2,      // 重试延迟基数（秒），每次重试延迟 = 基数 * 尝试次数
    
    // 分页配置
    'page_size' => 10,          // 分页大小，默认10条
    
    // 考试配置
    'exam_time_limit' => 1800,   // 考试时间限制（秒），默认30分钟
    'exam_answer_delay' => 30,   // 答题间隔时间（秒），用于计算剩余时间
    
    // 加密配置
    'encryption_key' => 'AutoLearnSystem2024SecretKey!@#$%^&*',  // Token加密密钥（硬编码）
    'token_expire_hours' => 24,  // Token过期时间（小时）
    
    // 日志配置
    'log_level' => 'info',      // 日志级别：debug/info/warning/error
    'log_buffer_size' => 100,   // 日志缓冲区大小
    'max_backup_files' => 10,   // 最大备份文件数
    
    // 性能配置
    'use_curl' => true,         // 是否使用cURL（默认启用）
];

