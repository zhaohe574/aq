<?php
/**
 * 日志内容获取代理脚本
 * 用于解决直接访问日志文件的403权限问题
 */

// 设置响应头
header('Content-Type: text/plain; charset=utf-8');

// 获取请求的文件路径
$file = isset($_GET['file']) ? $_GET['file'] : '';

// 安全检查：确保文件路径合法且在logs或errs目录下
if (empty($file) || !preg_match('/^(logs|errs)\/(log_|error_)\d{4}-\d{2}-\d{2}\.txt$/', $file)) {
    header('HTTP/1.1 400 Bad Request');
    echo "错误：无效的文件路径";
    exit;
}

// 构建完整的文件路径
$filePath = __DIR__ . '/' . $file;

// 检查文件是否存在
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo "错误：文件不存在";
    exit;
}

// 检查文件是否可读
if (!is_readable($filePath)) {
    header('HTTP/1.1 403 Forbidden');
    echo "错误：无法读取文件，权限不足";
    exit;
}

// 读取并输出文件内容
echo file_get_contents($filePath);