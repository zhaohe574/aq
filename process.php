<?php
/**
 * 自动学习系统后端处理文件
 * 
 * 处理前端请求，执行课程学习和考试任务
 */

// 设置错误处理和输出配置
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);
ob_implicit_flush(true);

// 设置响应头，确保流式输出
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

/**
 * 系统核心类
 */
class AutoLearnSystem {
    // 配置项
    private $config = [
        'api_base_url' => 'http://api.hebeiluhang.com:7000/api',
        'token_file' => 'tokens.json',
        'log_dir' => 'logs',
        'authorized_users' => []
    ];
    
    // 用户信息
    private $username = null;
    private $password = null;
    private $token = null;
    
    // 统计信息
    private $startTime;
    private $completedCourses = 0;
    private $completedExams = 0;
    
    /**
     * 构造函数，初始化系统
     */
    public function __construct() {
        // 注册错误处理函数
        set_error_handler([$this, 'errorHandler']);
        
        // 记录开始时间
        $this->startTime = microtime(true);
        
        // 创建日志目录
        $this->ensureLogDirectoryExists();
    }
    
    /**
     * 自定义错误处理函数
     */
    public function errorHandler($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_WARNING => 'warning',
            E_NOTICE => 'notice',
            E_USER_ERROR => 'error',
            E_USER_WARNING => 'warning',
            E_USER_NOTICE => 'notice',
            E_STRICT => 'strict',
            E_RECOVERABLE_ERROR => 'error',
            E_DEPRECATED => 'deprecated',
            E_USER_DEPRECATED => 'deprecated'
        ];
        
        $errorType = $errorTypes[$errno] ?? 'error';
        
        $this->formatMessage('PHP ' . $errorType, $errstr . ' in ' . $errfile . ' on line ' . $errline, $errorType);
        
        // 不执行PHP内部错误处理程序
        return true;
    }
    
    /**
     * 运行主程序
     */
    public function run() {
        try {
            // 解析请求数据
            $this->parseRequest();
            
            // 开始日志记录
            $this->startLogSession();
            
            // 验证用户
            $this->validateUser();
            
            // 登录并获取token
            $this->login();
            
            // 执行课程任务
            $this->processCourses();
            
            // 执行考试任务
            $this->processExams();
            
            // 结束任务，输出统计信息
            $this->finishTasks();
        } catch (Exception $e) {
            $this->formatMessage('系统错误', $e->getMessage(), 'error');
        }
    }
    
    /**
     * 解析请求数据
     */
    private function parseRequest() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 检查数据是否有效
        if (!is_array($data) || !isset($data['username']) || !isset($data['password'])) {
            throw new Exception('无效的请求数据');
        }
        
        $this->username = strtoupper($data['username']);
        $this->password = $data['password'];
        
        // 检查用户名和密码是否为空
        if (empty($this->username) || empty($this->password)) {
            throw new Exception('用户名或密码不能为空');
        }
    }
    
    /**
     * 验证用户是否授权
     */
    private function validateUser() {
        if (!in_array($this->username, $this->config['authorized_users'])) {
            throw new Exception('未授权的用户');
        }
    }
    
    /**
     * 登录并获取token
     */
    private function login() {
        $isLogin = false;
        
        // 加载用户本地记录的token
        $tokens = $this->loadTokens();
        
        // 检查是否有保存的token
        if (isset($tokens[$this->username]) && is_array($tokens[$this->username])) {
            // 检查密码是否匹配
            if (isset($tokens[$this->username]['password']) && $tokens[$this->username]['password'] === $this->password) {
                $this->token = $tokens[$this->username]['token'] ?? null;
                
                // 验证token是否有效
                if ($this->token) {
                    $url = $this->config['api_base_url'] . '/system/user/info';
                    $responseData = $this->sendRequest($url, 'GET');
                    
                    if (isset($responseData['userCode']) && $responseData['userCode'] === $this->username) {
                        $isLogin = true;
                    } else {
                        // token无效，重新登录获取新的token
                        $this->token = null;
                        $isLogin = false;
                        $this->formatMessage('Token失效', '正在重新登录获取最新Token', 'error');
                    }
                }
    } else {
                // 密码不匹配，删除旧token
                unset($tokens[$this->username]);
                $this->saveTokens($tokens);
            }
        }
        
        // 如果未登录，则进行登录
        if (!$isLogin) {
            $url = $this->config['api_base_url'] . '/auth/login';
            $responseData = $this->sendRequest($url, 'POST', ['username' => $this->username, 'password' => $this->password], false);
            
            if (isset($responseData['code']) && $responseData['code'] === 500) {
                throw new Exception($responseData['msg'] ?? '登录失败');
            }
            
            if (!isset($responseData['token'])) {
                throw new Exception('登录失败：未获取到token');
            }
            
            $this->token = $responseData['token'];
            
            // 保存token到本地
            $tokens[$this->username] = [
                'token' => $this->token,
                'password' => $this->password,
                'timestamp' => time()
            ];
            $this->saveTokens($tokens);
        }
        
        // 立即发送登录成功消息
        $this->formatMessage('成功', '登录成功', 'success');
    }
    
    /**
     * 处理课程任务
     */
    private function processCourses() {
        $this->formatMessage('系统', '开始执行课程任务', 'info');
        
        try {
            $url = $this->config['api_base_url'] . '/app/trainCoursePlan/queryAppTCPList';
            $data = [
                'isFinished' => '0',
                'appPageNum' => 1,
                'appPageSize' => 10,
                'total' => 0
            ];
            
            $response = $this->sendRequest($url, 'POST', $data);
            
            if (isset($response['code']) && $response['code'] === 500) {
                throw new Exception('获取课程信息失败：' . ($response['msg'] ?? '未知错误'));
            }
            
            if (!isset($response['total']) || intval($response['total']) === 0) {
                $this->formatMessage('课程', '没有可执行的课程任务', 'error');
                return;
            }
            
            $this->formatMessage('课程', '找到 ' . $response['total'] . ' 个课程任务', 'success');
            
            if ($response['total'] > 0 && isset($response['rows']) && is_array($response['rows'])) {
                foreach ($response['rows'] as $course) {
                    if (!isset($course['courseName']) || !isset($course['courseId'])) {
                        $this->formatMessage('课程', '课程数据不完整，跳过此课程', 'error');
                        continue;
                    }
                    
                    if ($course['courseTrainTypeCode'] != '1') {
                        $this->formatMessage('课程', '非视频类课程正在开发中...', 'error');
                        continue;
                    }
                    
                    $courseName = $course['courseName'];
                    $this->formatMessage('课程', '开始执行课程任务：'. $courseName, 'info');
                    
                    // 执行课程任务
                    $result = $this->completeCourse($course);
                    
                    if ($result) {
                        $this->completedCourses++;
                        $this->formatMessage('课程', '执行课程任务：'. $courseName .' 成功，累计完成 ' . $this->completedCourses . ' 个课程任务', 'success'); 
                    }
                }
            }
        } catch (Exception $e) {
            $this->formatMessage('错误', '处理课程信息时发生异常：' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * 完成单个课程
     */
    private function completeCourse($course) {
        $url = $this->config['api_base_url'] . '/app/trainCoursePlan/updateTCP';
        $data = $course;
        $data['isFinished'] = '1';
        $data['haveVideoTime'] = $course['videoTime'];
        $data['status'] = '1';
        $data['pauseVideoTime'] = $course['videoTime'];
        $data['params'] = count($course['params']) == 0 ? (object)[] : $course['params'];
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        if (isset($response['code']) && $response['code'] === 500) {
            $this->formatMessage($course['courseName'], '课程学习失败: ' . ($response['msg'] ?? '未知错误'), 'error');
            return false;
        }
        
        $this->formatMessage($course['courseName'], '课程任务学习完成', 'success');
        return true;
    }
    
    /**
     * 处理考试任务
     */
    private function processExams() {
        $this->formatMessage('系统', '开始执行考试任务', 'info');
        
        try {
            // 获取未完成的考试
            $unfinishedExams = $this->getExams('0');
            
            // 获取已完成的考试
            $finishedExams = $this->getExams('1');
            
            // 确保total是整数
            $totalUnfinished = isset($unfinishedExams['total']) ? intval($unfinishedExams['total']) : 0;
            $totalFinished = isset($finishedExams['total']) ? intval($finishedExams['total']) : 0;
            $totalExams = $totalUnfinished + $totalFinished;
            
            if ($totalExams === 0) {
                $this->formatMessage('考试', '没有可执行的考试任务', 'error');
                return;
            }
            
            $this->formatMessage('考试', '共 '. $totalExams . ' 个考试任务', 'success');
            
            // 处理未完成的考试
            if ($totalUnfinished > 0 && isset($unfinishedExams['rows']) && is_array($unfinishedExams['rows'])) {
                $this->processExamList($unfinishedExams['rows']);
            }
            
            // 处理已完成的考试
            if ($totalFinished > 0 && isset($finishedExams['rows']) && is_array($finishedExams['rows'])) {
                $this->processExamList($finishedExams['rows']);
            }
        } catch (Exception $e) {
            $this->formatMessage('错误', '处理考试信息时发生异常：' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * 获取考试列表
     */
    private function getExams($isFinished) {
        $url = $this->config['api_base_url'] . '/app/trainExamPlan/queryExamPlans';
        $data = [
            'isFinished' => $isFinished,
            'isMy' => '1',
            'appPageNum' => 1,
            'appPageSize' => 10,
            'total' => 0
        ];
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        if (isset($response['code']) && $response['code'] === 500) {
            throw new Exception('获取考试信息失败：' . ($response['msg'] ?? '未知错误'));
        }
        
        return $response;
    }
    
    /**
     * 处理考试列表
     */
    private function processExamList($exams) {
        foreach ($exams as $exam) {
            if (!isset($exam['exaName']) || !isset($exam['exaPlanId'])) {
                $this->formatMessage('考试', '考试数据不完整，跳过此考试', 'error');
                continue;
            }
            
            $examName = $exam['exaName'];
            $this->formatMessage('考试', '开始执行考试任务：'. $examName, 'info');
            
            // 执行考试任务
            $result = $this->completeExam(
                $exam['isFinished'] ?? 0,
                $exam['isPass'] ?? 0,
                $exam['exaTypeCode'] ?? '',
                $exam['exaCount'] ?? 0,
                $exam['exaPlanId'],
                $examName
            );
            
            if ($result) {
                $this->completedExams++;
                $this->formatMessage('考试', '执行考试任务：'. $examName .' 成功，累计完成 ' . $this->completedExams . ' 个考试任务', 'success');
            }
        }
    }
    
    /**
     * 完成单个考试
     */
    private function completeExam($isFinished, $isPass, $exaTypeCode, $exaCount, $exaPlanId, $exaName) {
        $url = $this->config['api_base_url'] . '/app/trainExamPlan/startExamPlan';
        $data = [
            'reExaTimeSec' => '1800',
            'isFinished' => $isFinished,
            'isPass' => $isPass,
            'exaTypeCode' => $exaTypeCode,
            'exaCount' => $exaCount,
            'exaPlanId' => $exaPlanId
        ];
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        if (isset($response['code']) && $response['code'] === 500) {
            $this->formatMessage($exaName, '获取考题失败: ' . ($response['msg'] ?? '未知错误'), 'error');
            return false;
        }
        
        // 检查userSubjects是否存在
        if (!isset($response['userSubjects']) || !is_array($response['userSubjects'])) {
            $this->formatMessage($exaName, '获取考题失败: 未找到考题数据', 'error');
            return false;
        }
        
        $this->formatMessage($exaName, '开始答题', 'info');
        
        // 回答每道题目
        foreach ($response['userSubjects'] as $key => $value) {
            $this->answerQuestion($key, $value, $exaName);
        }
        
        $this->formatMessage($exaName, '答题完成', 'success');
        
        // 提交考试
        return $this->submitExam($exaPlanId, $exaName);
    }
    
    /**
     * 回答单个题目
     */
    private function answerQuestion($index, $question, $exaName) {
        $url = $this->config['api_base_url'] . '/app/trainExamPlan/startAnswer';
        $data = [
            "reExaTimeSec" => 1800 - ($index+1)*30,
            "exaPlanId" => $question['exaPlanId'],
            "isTrue" => "1",
            "subId" => $question['subId'],
            "subChoose" => $question['subTrueAnswer'],
            "sortNum" => $question['sortNum']
        ];
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        if ($response === 2) {
            $this->formatMessage($exaName.'第'.($index+1).'题', '提交答案成功', 'success');
        } else {
            $this->formatMessage($exaName.'第'.($index+1).'题', '提交答案失败', 'error');
        }
    }
    
    /**
     * 提交考试
     */
    private function submitExam($exaPlanId, $exaName) {
        $url = $this->config['api_base_url'] . '/app/trainExamPlan/startFinalAnswer';
        $data = [
            'exaPlanId' => $exaPlanId 
        ];
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        // 检查是否有score字段
        if (!isset($response['score'])) {
            $this->formatMessage($exaName, '考试提交失败: 未获取到分数', 'error');
            return false;
        }
        
        // 将string转化为数字
        $score = intval($response['score']);
        if ($score >= 90) {
            $this->formatMessage($exaName, '考试通过，得分'.$score, 'success');
            return true;
        } else {
            $this->formatMessage($exaName, '考试未通过，得分'.$score, 'error');
            return false;
        }
    }
    
    /**
     * 发送API请求
     */
    private function sendRequest($url, $method, $data = null, $useToken = true) {
    $headers = [
        "Content-type: application/json\r\n"
    ];
    
        if ($useToken && $this->token) {
            $headers[] = "Authorization: {$this->token}\r\n";
    }
    
    $options = [
        'http' => [
            'header'  => implode('', $headers),
            'method'  => strtoupper($method),
            'content' => $data ? json_encode($data) : null,
                'ignore_errors' => true,
                'timeout' => 30,
        ],
    ];
    
    $context = stream_context_create($options);
    
    try {
        $response = file_get_contents($url, false, $context);
        
        // 检查是否获取到响应
        if ($response === false) {
            $error = error_get_last();
            return [
                'code' => 500,
                'msg' => "请求失败: " . ($error ? $error['message'] : '未知错误')
            ];
        }
        
        // 尝试解析JSON
        $decoded = json_decode($response, true);
        
        // 检查JSON解析是否成功
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'code' => 500,
                'msg' => "JSON解析失败: " . json_last_error_msg()
            ];
        }
        
            return $decoded;
    } catch (Exception $e) {
        return [
            'code' => 500,
            'msg' => "请求异常: {$e->getMessage()}"
        ];
    }
}

    /**
     * 格式化并输出消息
     */
    private function formatMessage($title, $content, $type = "info") {
        // 确保输入参数是有效的字符串
        $type = is_string($type) ? $type : "info";
        $title = is_string($title) ? $title : (string)$title;
        $content = is_string($content) ? $content : (string)$content;
        
        // 创建消息数组
        $messageArray = [
            'type' => $type,
            'title' => $title,
            'content' => $content
        ];
        
        // 尝试JSON编码，并处理可能的错误
        $msg = json_encode($messageArray, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        
        // 检查JSON编码是否成功
        if ($msg === false) {
            // JSON编码失败，创建一个错误消息
            $errorMsg = json_encode([
                'type' => 'error',
                'title' => 'JSON编码错误',
                'content' => '无法编码消息: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
            
            $this->logMessage($errorMsg, 'error');
            echo $errorMsg . "\n";
        } else {
            // JSON编码成功，记录并输出消息
            $this->logMessage($msg, $type);
            echo $msg . "\n";
        }
        
        // 刷新输出缓冲区
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * 记录日志
     */
    private function logMessage($message, $logType = 'info') {
    // 获取当前时间，精确到毫秒
    $microtime = microtime(true);
    $milliseconds = sprintf("%03d", ($microtime - floor($microtime)) * 1000);
    $date = date('Y-m-d');
    $time = date('H:i:s', $microtime) . '.' . $milliseconds;
    
    // 获取客户端IP地址
    $ip = $_SERVER['REMOTE_ADDR'] ?? '未知IP';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // 获取当前用户名
        $user = $this->username ?? '未登录用户';
    
    // 解析消息内容
    $decoded = json_decode($message, true);
    $action = isset($decoded['title']) ? $decoded['title'] : '未知操作';
    $content = isset($decoded['content']) ? $decoded['content'] : $message;
    
    // 格式化日志内容
    $logEntry = "[{$date} {$time}] [{$logType}] [用户:{$user}] [IP:{$ip}] [操作:{$action}] {$content}";
    
    // 写入日志文件
        $logFile = "{$this->config['log_dir']}/log_{$date}.txt";
    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
    
    // 错误日志额外记录到错误日志文件
    if ($logType == 'error' || $logType == 'warning') {
            $errorLogFile = "{$this->config['log_dir']}/error_{$date}.txt";
        file_put_contents($errorLogFile, $logEntry . PHP_EOL, FILE_APPEND);
    }
    
    // 日志文件大小控制（超过10MB时进行轮转）
    if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
            $backupFile = "{$this->config['log_dir']}/log_{$date}_" . date('His') . ".bak";
        rename($logFile, $backupFile);
    }
}

    /**
     * 确保日志目录存在
     */
    private function ensureLogDirectoryExists() {
        if (!file_exists($this->config['log_dir'])) {
            mkdir($this->config['log_dir'], 0777, true);
        }
    }
    
    /**
     * 加载保存的token
     */
    private function loadTokens() {
        $tokenFile = $this->config['token_file'];
        return file_exists($tokenFile) ? json_decode(file_get_contents($tokenFile), true) : [];
    }
    
    /**
     * 保存token到文件
     */
    private function saveTokens($tokens) {
        file_put_contents($this->config['token_file'], json_encode($tokens));
    }
    
    /**
     * 开始日志会话
     */
    private function startLogSession() {
// 添加开始分割线
        $this->logMessage(json_encode([
    'type' => 'info',
    'title' => '分割线',
    'content' => str_repeat('=', 50)
]), 'info');
        
        $this->logMessage(json_encode([
    'type' => 'info',
    'title' => '开始',
    'content' => '开始执行任务 - ' . date('Y-m-d H:i:s')
]), 'info');
    }
    
    /**
     * 完成所有任务，输出统计信息
     */
    private function finishTasks() {
        // 计算执行时间
        $endTime = microtime(true);
        $executionTime = round($endTime - $this->startTime, 2);
        
        // 添加结束分割线和统计信息
        $this->formatMessage('任务结束', "执行时长：{$executionTime}秒，完成课程：{$this->completedCourses}个，完成考试：{$this->completedExams}个", 'info');
        
        $this->logMessage(json_encode([
    'type' => 'info',
    'title' => '分割线',
    'content' => str_repeat('=', 50)
]), 'info');
    }
}

// 创建并运行系统
$system = new AutoLearnSystem();
$system->run();
?>
