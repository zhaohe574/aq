<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Logger;
use App\Services\ExamService;
use App\Services\CourseService;

// 关闭输出缓冲区，确保立即输出
ob_end_clean();
ob_implicit_flush(true);

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize core services
$logger = new Logger($config['storage']['logs_dir'], $config['storage']['max_log_size']);
$api = new Api($config['api']['base_url'], $config['api']['timeout'], $logger);
$auth = new Auth($config['auth']['authorized_users'], $config['auth']['token_file'], $api, $logger);

// Set error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);
ini_set('zlib.output_compression', false);

set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logger) {
    $type = match($errno) {
        E_WARNING => 'warning',
        E_NOTICE => 'notice',
        default => 'error'
    };
    
    $logger->log("{$errstr} in {$errfile} on line {$errline}", $type);
    return true;
});

// 记录请求开始
$logger->log("新的请求开始处理", 'info');

// Configure output
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // 对Nginx有效

// 确保能够立即发送输出
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

// Handle request
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data) || !isset($data['username']) || !isset($data['password'])) {
    $logger->log("无效的请求数据", 'error');
    echo $logger->formatMessage('error', '错误', '无效的请求数据');
    flush();
    exit;
}

$username = strtoupper($data['username']);
$password = $data['password'];

$logger->log("用户 {$username} 尝试登录", 'info');

// Authenticate user
$token = $auth->authenticate($username, $password);
if (!$token) {
    $logger->log("用户 {$username} 认证失败", 'error');
    echo $logger->formatMessage('error', '错误', '认证失败');
    flush();
    exit;
}

$logger->log("用户 {$username} 登录成功", 'info', ['user' => $username]);

// Set token for API calls
$api->setToken($token);

// Initialize services
$examService = new ExamService($api, $logger);
$courseService = new CourseService($api, $logger);

// Start processing
echo $logger->formatMessage('success', '成功', '登录成功');
flush();
$logger->log("发送成功消息: 登录成功", 'info', ['user' => $username]);
sleep(1); // 给前端时间来处理

echo $logger->formatMessage('info', '系统', '开始执行任务');
flush();
$logger->log("开始执行任务", 'info', ['user' => $username]);
sleep(1);

// Process courses
$logger->log("获取课程信息", 'info', ['user' => $username]);
$courseResult = $courseService->getCourses();
if ($courseResult['total'] > 0) {
    $message = "找到 {$courseResult['total']} 个课程任务";
    echo $logger->formatMessage('success', '课程', $message);
    $logger->log($message, 'info', ['user' => $username]);
    flush();
    sleep(1);
    
    echo $logger->formatMessage('info', '课程', '课程任务程序正在开发中......');
    $logger->log("课程任务程序正在开发中", 'info', ['user' => $username]);
    flush();
    sleep(1);
}

// Process exams
$logger->log("获取考试信息", 'info', ['user' => $username]);
$unfinishedExams = $examService->getExams(false);
$finishedExams = $examService->getExams(true);

$totalExams = count($unfinishedExams) + count($finishedExams);
if ($totalExams > 0) {
    $message = "共 {$totalExams} 个考试任务";
    echo $logger->formatMessage('success', '考试', $message);
    $logger->log($message, 'info', ['user' => $username]);
    flush();
    sleep(1);
    
    $completedCount = 0;
    
    // Process unfinished exams
    $logger->log("处理未完成的考试任务", 'info', ['user' => $username]);
    foreach ($unfinishedExams as $exam) {
        $logger->log("处理考试: " . ($exam['title'] ?? 'Unknown'), 'info', ['user' => $username]);
        if ($examService->processExam($exam)) {
            $completedCount++;
            $logger->log("考试处理成功: " . ($exam['title'] ?? 'Unknown'), 'info', ['user' => $username]);
        } else {
            $logger->log("考试处理失败: " . ($exam['title'] ?? 'Unknown'), 'warning', ['user' => $username]);
        }
    }
    
    // Process finished exams
    $logger->log("处理已完成的考试任务", 'info', ['user' => $username]);
    foreach ($finishedExams as $exam) {
        $logger->log("处理考试: " . ($exam['title'] ?? 'Unknown'), 'info', ['user' => $username]);
        if ($examService->processExam($exam)) {
            $completedCount++;
            $logger->log("考试处理成功: " . ($exam['title'] ?? 'Unknown'), 'info', ['user' => $username]);
        } else {
            $logger->log("考试处理失败: " . ($exam['title'] ?? 'Unknown'), 'warning', ['user' => $username]);
        }
    }
    
    $message = "完成 {$completedCount} 个考试任务";
    echo $logger->formatMessage('success', '考试', $message);
    $logger->log($message, 'info', ['user' => $username]);
    flush();
    sleep(1);
} else {
    echo $logger->formatMessage('info', '考试', '没有可执行的考试任务');
    $logger->log("没有可执行的考试任务", 'info', ['user' => $username]);
    flush();
    sleep(1);
}

echo $logger->formatMessage('info', '系统', '所有任务执行完成');
$logger->log("所有任务执行完成", 'info', ['user' => $username]);
flush(); 