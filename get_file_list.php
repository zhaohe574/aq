<?php
/**
 * 获取日志文件列表
 * 此脚本用于获取logs和errs目录下的真实文件列表
 * 作者: AI助手
 * 日期: 2025-09-26
 */

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 获取请求参数
$type = isset($_GET['type']) ? $_GET['type'] : 'log';

// 确定目录路径
$dir = $type === 'log' ? 'logs/' : 'errs/';
$prefix = $type === 'log' ? 'log_' : 'error_';

// 验证目录是否存在
if (!is_dir($dir)) {
    echo json_encode(['success' => false, 'message' => '目录不存在', 'files' => []]);
    exit;
}

// 获取目录中的文件列表
$files = [];
$dh = opendir($dir);
if ($dh) {
    while (($file = readdir($dh)) !== false) {
        // 只获取.txt文件，且文件名以正确的前缀开头
        if (is_file($dir . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'txt' && strpos($file, $prefix) === 0) {
            $files[] = $dir . $file;
        }
    }
    closedir($dh);
    
    // 按文件名排序（最新的在前）
    rsort($files);
}

// 返回JSON响应
echo json_encode(['success' => true, 'files' => $files]);
?>