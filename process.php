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

// 避免 PHP 会话锁定导致的阻塞
session_write_close();

// 设置响应头，确保流式输出
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');
// 设置最大执行时间，避免超时
set_time_limit(0);

/**
 * 自定义异常类
 */
class AutoLearnException extends Exception {
    const LOGIN_FAILED = 'LOGIN_FAILED';
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    const TOKEN_INVALID = 'TOKEN_INVALID';
    const INVALID_INPUT = 'INVALID_INPUT';
    const UNAUTHORIZED = 'UNAUTHORIZED';
    const API_ERROR = 'API_ERROR';
    const SYSTEM_ERROR = 'SYSTEM_ERROR';
    
    private $errorCode;
    
    public function __construct($message, $code = 0, $errorCode = 'SYSTEM_ERROR', Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }
    
    public function getErrorCode() {
        return $this->errorCode;
    }
}

/**
 * 系统核心类
 */
class AutoLearnSystem {
    // 配置项
    private $config = [];
    
    // 日志缓冲区
    private $logBuffer = [];
    
    // 用户信息
    private $username = null;
    private $password = null;
    private $token = null;
    private $userName = null;
    
    // 统计信息
    private $startTime;
    private $completedCourses = 0;
    private $completedExams = 0;

    // 励志语录列表
    private $motivationalQuotes = [
        "今天的努力，是明天的基石。坚持不懈，你会看到不一样的风景。",
        "学习是一场修行，不在乎起点，重要的是坚持的路上，你会遇见更好的自己。",
        "成功不是偶然，而是日复一日的坚持与积累。每一步都算数。",
        "知识改变命运，学习成就未来。今天多学一点，明天就多一份力量。",
        "人生没有白走的路，每一步都是成长。保持学习的心态，你将无所不能。",
        "再小的进步，只要坚持，也会累积成巨大的成功。",
        "学习不是为了应付考试，而是为了遇见更广阔的世界和更好的自己。",
        "没有人能随随便便成功，你的每一次努力都在塑造未来的你。",
        "不要等待灵感，努力本身就是最好的灵感。",
        "学习是一辈子的事情，今天你投入的每一分钟，都是给未来的自己铺路。"
    ];
    
    /**
     * 构造函数，初始化系统
     */
    public function __construct() {
        // 加载配置
        $this->loadConfig();
        
        // 注册错误处理函数
        set_error_handler([$this, 'errorHandler']);
        
        // 记录开始时间
        $this->startTime = microtime(true);
        
        // 创建日志目录
        $this->ensureLogDirectoryExists();
    }
    
    /**
     * 加载配置文件
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            // 如果配置文件不存在，使用默认配置
            $this->config = [
                'api_base_url' => 'http://api.hebeiluhang.com:7000/api',
                'token_file' => 'tokens.json',
                'log_dir' => 'logs',
                'err_log_dir' => 'errs',
                'authorized_users' => ['JS05533','JS02319','JS03912','JS01521','JS00003','JS05764','JS01806','JS00949','JS02466'],
                'api_timeout' => 120,
                'api_max_retries' => 3,
                'api_retry_delay' => 2,
                'page_size' => 10,
                'exam_time_limit' => 1800,
                'exam_answer_delay' => 30,
                'encryption_key' => 'AutoLearnSystem2024SecretKey!@#$%^&*',
                'token_expire_hours' => 24,
                'log_level' => 'info',
                'log_buffer_size' => 100,
                'max_backup_files' => 10,
                'use_curl' => true,
            ];
        }
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
        } catch (AutoLearnException $e) {
            $this->formatMessage('系统错误', $e->getMessage() . ' (错误代码: ' . $e->getErrorCode() . ')', 'error');
        } catch (Exception $e) {
            $this->formatMessage('系统错误', $e->getMessage(), 'error');
        }
    }
    
    /**
     * 验证和过滤输入
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @return array 验证后的用户名和密码
     * @throws AutoLearnException 验证失败时抛出异常
     */
    private function validateInput($username, $password) {
        // 用户名验证：只允许大写字母和数字，长度3-20字符
        $username = strtoupper(trim($username));
        if (!preg_match('/^[A-Z0-9]{3,20}$/', $username)) {
            throw new AutoLearnException('用户名格式不正确：只允许大写字母和数字，长度3-20个字符', 0, AutoLearnException::INVALID_INPUT);
        }
        
        // 密码验证：长度6-50字符
        $password = trim($password);
        if (strlen($password) < 6 || strlen($password) > 50) {
            throw new AutoLearnException('密码长度必须在6-50个字符之间', 0, AutoLearnException::INVALID_INPUT);
        }
        
        // 过滤特殊字符（防止注入攻击）
        $password = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }
    
    /**
     * 解析请求数据
     */
    private function parseRequest() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 检查数据是否有效，支持loginName和username（向后兼容）
        $usernameField = isset($data['loginName']) ? $data['loginName'] : (isset($data['username']) ? $data['username'] : null);
        if (!is_array($data) || !$usernameField || !isset($data['password'])) {
            throw new AutoLearnException('无效的请求数据', 0, AutoLearnException::INVALID_INPUT);
        }
        
        // 验证和过滤输入
        $validated = $this->validateInput($usernameField, $data['password']);
        $this->username = $validated['username'];
        $this->password = $validated['password'];
        
        // 检查用户名和密码是否为空
        if (empty($this->username) || empty($this->password)) {
            throw new AutoLearnException('用户名或密码不能为空', 0, AutoLearnException::INVALID_INPUT);
        }
    }
    
    /**
     * 验证用户是否授权
     */
    private function validateUser() {
        if (!in_array($this->username, $this->config['authorized_users'])) {
            // 记录未授权用户日志
            $this->logMessage(json_encode([
                'type' => 'warning',
                'title' => '未授权访问',
                'content' => "用户 {$this->username} 尝试访问系统但未授权"
            ]), 'warning');
            
            // 随机选择一条励志语录
            $randomQuote = $this->motivationalQuotes[array_rand($this->motivationalQuotes)];
            
            // 输出消息但不记录日志
            $this->formatMessage('欢迎', '感谢您使用智慧学习助手', 'info', false);
            $this->formatMessage('今日格言', $randomQuote, 'success', false);
            $this->formatMessage('温馨提示', '坚持学习，持续进步。我们将一直陪伴您的学习之旅！', 'info', false);

            $this->logMessage(json_encode([
                'type' => 'info',
                'title' => '分割线',
                'content' => str_repeat('=', 120)
            ]), 'info');
            
            // 直接退出，不显示任务结束信息
            exit;
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
                // 检查Token是否过期
                if ($this->isTokenExpired($tokens[$this->username])) {
                    $this->formatMessage('Token过期', 'Token已过期，正在重新登录获取最新Token', 'warning');
                    unset($tokens[$this->username]);
                    $this->saveTokens($tokens);
                } else {
                    $this->token = $tokens[$this->username]['token'] ?? null;
                    
                    // 验证token是否有效
                    if ($this->token) {
                        $url = $this->config['api_base_url'] . '/system/user/info';
                        $responseData = $this->sendRequest($url, 'GET');
                        
                        if (isset($responseData['userCode']) && $responseData['userCode'] === $this->username) {
                            $isLogin = true;
                            // 获取用户姓名
                            if (isset($responseData['userName'])) {
                                $this->userName = $responseData['userName'];
                            }
                        } else {
                            // token无效，重新登录获取新的token
                            $this->token = null;
                            $isLogin = false;
                            $this->formatMessage('Token失效', '正在重新登录获取最新Token', 'error');
                        }
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
            $responseData = $this->sendRequest($url, 'POST', ['loginName' => $this->username, 'password' => $this->password], false);
            
            if (isset($responseData['code']) && $responseData['code'] === 500) {
                throw new AutoLearnException($responseData['msg'] ?? '登录失败', 0, AutoLearnException::LOGIN_FAILED);
            }
            
            if (!isset($responseData['token'])) {
                throw new AutoLearnException('登录失败：未获取到token', 0, AutoLearnException::LOGIN_FAILED);
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
        
        // 获取用户信息
        $infoUrl = $this->config['api_base_url'] . '/system/user/info';
        $userInfo = $this->sendRequest($infoUrl, 'GET');
        if (isset($userInfo['userName'])) {
            $this->userName = $userInfo['userName'];
        }
        
        // 立即发送登录成功消息
        $loginMsg = isset($this->userName) ? "登录成功，欢迎 {$this->userName}" : '登录成功';
        $this->formatMessage('成功', $loginMsg, 'success');
    }
    
    /**
     * 处理课程任务
     */
    private function processCourses() {
        $this->formatMessage('系统', '开始执行课程任务', 'info');
        
        try {
            $url = $this->config['api_base_url'] . '/app/trainCoursePlan/queryAppTCPList';
            $pageSize = $this->config['page_size'] ?? 100; // 每页大小，默认100
            $pageNum = 1;
            $totalCourses = 0;
            $allCourses = [];
            
            // 循环获取所有分页数据
            do {
                $data = [
                    'isFinished' => '0,1',
                    'appPageNum' => $pageNum,
                    'appPageSize' => $pageSize,
                    'total' => 0
                ];
                
                $response = $this->sendRequest($url, 'POST', $data);
                
                if (isset($response['code']) && $response['code'] === 500) {
                    throw new Exception('获取课程信息失败：' . ($response['msg'] ?? '未知错误'));
                }
                
                // 第一次请求时显示总数
                if ($pageNum === 1) {
                    $totalCourses = isset($response['total']) ? intval($response['total']) : 0;
                    if ($totalCourses === 0) {
                        $this->formatMessage('课程', '课程任务已全部完成', 'success');
                        return;
                    }
                    $this->formatMessage('课程', '找到 ' . $totalCourses . ' 个课程任务，开始处理...', 'success');
                }
                
                // 收集当前页的课程
                if (isset($response['rows']) && is_array($response['rows'])) {
                    $allCourses = array_merge($allCourses, $response['rows']);
                }
                
                $pageNum++;
                
                // 如果当前页返回的数据少于pageSize，说明已经是最后一页
            } while (isset($response['rows']) && count($response['rows']) >= $pageSize);
            
            // 处理所有收集到的课程
            $totalCourseCount = count($allCourses);
            $currentCourseIndex = 0;
            
            foreach ($allCourses as $course) {
                if (!isset($course['courseName']) || !isset($course['courseId'])) {
                    $this->formatMessage('课程', '课程数据不完整，跳过此课程', 'error');
                    continue;
                }
                
                $currentCourseIndex++;
                $courseName = $course['courseName'];
                $result = false;
                
                // 计算进度（课程部分占50%）
                $courseProgress = ($currentCourseIndex / max(1, $totalCourseCount)) * 50;
                
                // 使用 else if 确保只执行一次
                if ($course['courseTrainTypeCode'] == '0') {
                    $this->formatMessage('课程', '开始执行文件课程任务：'. $courseName, 'info', true, $courseProgress);
                    $result = $this->completeCourse($course, 'WJ');
                } elseif ($course['courseTrainTypeCode'] == '1') {
                    $this->formatMessage('课程', '开始执行视频课程任务：'. $courseName, 'info', true, $courseProgress);
                    $result = $this->completeCourse($course, 'SP');
                } elseif ($course['courseTrainTypeCode'] == '2') {
                    $this->formatMessage('课程', '开始执行视频和文件课程任务：'. $courseName, 'info', true, $courseProgress);
                    $result = $this->completeCourse($course, 'SPWJ');
                } else {
                    $this->formatMessage('课程', '未知课程类型：'. $courseName . ' (类型代码: ' . ($course['courseTrainTypeCode'] ?? '未知') . ')', 'error', true, $courseProgress);
                    continue;
                }
                
                if ($result) {
                    $this->completedCourses++;
                    $this->formatMessage('课程', '执行课程任务：'. $courseName .' 成功，累计完成 ' . $this->completedCourses . ' 个课程任务', 'success', true, $courseProgress); 
                }
            }
        } catch (Exception $e) {
            $this->formatMessage('错误', '处理课程信息时发生异常：' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * 完成单个课程（统一方法，支持所有课程类型）
     * 
     * @param array $course 课程数据
     * @param string $type 课程类型：'WJ'=文件, 'SP'=视频, 'SPWJ'=视频+文件
     * @return bool 是否成功
     */
    private function completeCourse($course, $type) {
        $url = $this->config['api_base_url'] . '/app/trainCoursePlan/updateTCP';
        $data = $course;
        $data['isFinished'] = '1';
        $data['status'] = '1';
        $data['params'] = empty($course['params']) ? (object)[] : $course['params'];
        
        // 根据课程类型设置不同的时间字段
        switch ($type) {
            case 'WJ':
                // 文件课程
                $data['haveViewTime'] = $course['viewTime'] ?? 0;
                break;
            case 'SP':
                // 视频课程
                $data['haveVideoTime'] = $course['videoTime'] ?? 0;
                $data['pauseVideoTime'] = $course['videoTime'] ?? 0;
                break;
            case 'SPWJ':
                // 视频+文件课程
                $data['haveVideoTime'] = $course['videoTime'] ?? 0;
                $data['pauseVideoTime'] = $course['videoTime'] ?? 0;
                $data['haveViewTime'] = $course['viewTime'] ?? 0;
                break;
            default:
                $this->formatMessage($course['courseName'] ?? '未知课程', '未知的课程类型: ' . $type, 'error');
                return false;
        }
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        if (isset($response['code']) && $response['code'] === 500) {
            $this->formatMessage($course['courseName'] ?? '未知课程', '课程学习失败: ' . ($response['msg'] ?? '未知错误'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * 处理考试任务
     */
    private function processExams() {
        $this->formatMessage('系统', '开始执行考试任务', 'info');
        
        try {
            $allExams = [];
            
            // 获取未完成的考试（分页）
            $unfinishedExams = $this->getAllExams('0');
            
            // 获取已完成的考试（分页）
            $finishedExams = $this->getAllExams('1');
            
            // 合并所有考试
            $allExams = array_merge($unfinishedExams, $finishedExams);
            
            if (empty($allExams)) {
                $this->formatMessage('考试', '考试任务已全部完成', 'success');
                return;
            }
            
            $this->formatMessage('考试', '共 '. count($allExams) . ' 个考试任务', 'success');
            
            // 处理所有考试
            $this->processExamList($allExams);
        } catch (Exception $e) {
            $this->formatMessage('错误', '处理考试信息时发生异常：' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * 获取所有考试列表（支持分页）
     * 
     * @param string $isFinished '0'=未完成, '1'=已完成
     * @return array 所有考试数据
     */
    private function getAllExams($isFinished) {
        $url = $this->config['api_base_url'] . '/app/trainExamPlan/queryExamPlans';
        $pageSize = $this->config['page_size'] ?? 100; // 每页大小，默认100
        $pageNum = 1;
        $allExams = [];
        
        // 循环获取所有分页数据
        do {
            $data = [
                'isFinished' => $isFinished,
                'isMy' => '0',
                'appPageNum' => $pageNum,
                'appPageSize' => $pageSize,
                'total' => 0
            ];
            
            $response = $this->sendRequest($url, 'POST', $data);
            
            if (isset($response['code']) && $response['code'] === 500) {
                throw new Exception('获取考试信息失败：' . ($response['msg'] ?? '未知错误'));
            }
            
            // 收集当前页的考试
            if (isset($response['rows']) && is_array($response['rows'])) {
                $allExams = array_merge($allExams, $response['rows']);
            }
            
            $pageNum++;
            
            // 如果当前页返回的数据少于pageSize，说明已经是最后一页
        } while (isset($response['rows']) && count($response['rows']) >= $pageSize);
        
        return $allExams;
    }
    
    /**
     * 处理考试列表
     */
    private function processExamList($exams) {
        $totalExamCount = count($exams);
        $currentExamIndex = 0;
        
        foreach ($exams as $exam) {
            if (!isset($exam['exaName']) || !isset($exam['exaPlanId'])) {
                $this->formatMessage('考试', '考试数据不完整，跳过此考试', 'error');
                continue;
            }
            
            $currentExamIndex++;
            $examName = $exam['exaName'];
            
            // 计算进度（考试部分占50%，从50%开始）
            $examProgress = 50 + ($currentExamIndex / max(1, $totalExamCount)) * 50;
            
            $this->formatMessage('考试', '开始执行考试任务：'. $examName, 'info', true, $examProgress);
            
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
                $this->formatMessage('考试', '执行考试任务：'. $examName .' 成功，累计完成 ' . $this->completedExams . ' 个考试任务', 'success', true, $examProgress);
            }
        }
    }
    
    /**
     * 完成单个考试
     */
    private function completeExam($isFinished, $isPass, $exaTypeCode, $exaCount, $exaPlanId, $exaName) {
        // 提示用户正在获取考题
        $this->formatMessage($exaName, '正在获取考题，请稍候...', 'info');
        
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
        
        // 获取考题成功，显示题目数量
        $questionCount = count($response['userSubjects']);
        $this->formatMessage($exaName, "已获取 {$questionCount} 道题目，开始答题", 'success');
        
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
        $examTimeLimit = $this->config['exam_time_limit'] ?? 1800;
        $answerDelay = $this->config['exam_answer_delay'] ?? 30;
        
        $data = [
            "reExaTimeSec" => $examTimeLimit - ($index + 1) * $answerDelay,
            "exaPlanId" => $question['exaPlanId'],
            "isTrue" => "1",
            "subId" => $question['subId'],
            "subChoose" => $question['subTrueAnswer'],
            "sortNum" => $question['sortNum']
        ];
        
        $response = $this->sendRequest($url, 'POST', $data);
        
        if ($response === 2) {
            $this->formatMessage('第'.($index+1).'题', '提交答案成功', 'success');
        } else {
            // 提供更详细的错误信息
            $errorMsg = '提交答案失败';
            if (is_array($response) && isset($response['msg'])) {
                $errorMsg .= ': ' . $response['msg'];
            } elseif (is_array($response) && isset($response['code'])) {
                $errorMsg .= ' (错误代码: ' . $response['code'] . ')';
            } else {
                $errorMsg .= ' (响应: ' . json_encode($response) . ')';
            }
            $this->formatMessage('第'.($index+1).'题', $errorMsg, 'error');
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
     * 使用cURL发送API请求
     * 
     * @param string $url 请求URL
     * @param string $method 请求方法 (GET/POST)
     * @param array|null $data 请求数据
     * @param bool $useToken 是否使用Token
     * @param int $timeout 超时时间（秒）
     * @return array|false 返回API响应数据，失败时返回false
     */
    private function sendRequestWithCurl($url, $method, $data = null, $useToken = true, $timeout = 120) {
        if (!function_exists('curl_init')) {
            return false;
        }
        
        $ch = curl_init();
        
        $headers = ['Content-Type: application/json'];
        if ($useToken && $this->token) {
            $headers[] = "Authorization: {$this->token}";
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $data ? json_encode($data) : null,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            if ($decoded !== null || json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return false;
    }
    
    /**
     * 发送API请求（带重试机制）
     * 
     * @param string $url 请求URL
     * @param string $method 请求方法 (GET/POST)
     * @param array|null $data 请求数据
     * @param bool $useToken 是否使用Token
     * @param int|null $timeout 超时时间（秒），null时使用配置中的默认值
     * @param int|null $maxRetries 最大重试次数，null时使用配置中的默认值
     * @return array|mixed 返回API响应数据，失败时返回错误数组
     */
    private function sendRequest($url, $method, $data = null, $useToken = true, $timeout = null, $maxRetries = null) {
        // 使用配置的默认值或传入的参数
        $timeout = $timeout ?? $this->config['api_timeout'];
        $maxRetries = $maxRetries ?? $this->config['api_max_retries'];
        $retryDelay = $this->config['api_retry_delay'];
        $useCurl = $this->config['use_curl'] ?? true;
        
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                // 优先使用cURL
                if ($useCurl && function_exists('curl_init')) {
                    $response = $this->sendRequestWithCurl($url, $method, $data, $useToken, $timeout);
                    
                    if ($response !== false) {
                        // 成功获取响应，返回结果
                        if ($attempt > 1) {
                            $this->formatMessage('系统', "API请求在第{$attempt}次尝试时成功", 'success');
                        }
                        return $response;
                    } else {
                        $lastError = "cURL请求失败";
                    }
                } else {
                    // 降级使用file_get_contents
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
                            'timeout' => $timeout,
                        ],
                    ];
                    
                    $context = stream_context_create($options);
                    error_clear_last();
                    
                    $response = @file_get_contents($url, false, $context);
                    
                    if ($response !== false) {
                        $decoded = json_decode($response, true);
                        
                        if ($decoded !== null || json_last_error() === JSON_ERROR_NONE) {
                            if ($attempt > 1) {
                                $this->formatMessage('系统', "API请求在第{$attempt}次尝试时成功", 'success');
                            }
                            return $decoded;
                        } else {
                            $lastError = "JSON解析失败: " . json_last_error_msg();
                        }
                    } else {
                        $error = error_get_last();
                        $lastError = $error ? $error['message'] : '未知错误';
                        
                        if (strpos($lastError, 'timeout') !== false || strpos($lastError, 'timed out') !== false) {
                            $lastError = "请求超时（{$timeout}秒）";
                        }
                    }
                }
                
                // 如果不是最后一次尝试，等待后重试
                if ($attempt < $maxRetries) {
                    $waitTime = $attempt * $retryDelay;
                    $this->formatMessage('系统', "API请求失败，{$waitTime}秒后重试 (尝试 {$attempt}/{$maxRetries}): {$lastError}", 'warning');
                    sleep($waitTime);
                }
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                
                if ($attempt < $maxRetries) {
                    $waitTime = $attempt * $retryDelay;
                    $this->formatMessage('系统', "请求异常，{$waitTime}秒后重试 (尝试 {$attempt}/{$maxRetries}): {$lastError}", 'warning');
                    sleep($waitTime);
                }
            }
        }
        
        // 所有重试都失败
        $this->formatMessage('系统', "API请求最终失败（已重试{$maxRetries}次）", 'error');
        return [
            'code' => 500,
            'msg' => "请求失败（已重试{$maxRetries}次）: " . ($lastError ?? '未知错误')
        ];
    }

    /**
     * 格式化并输出消息（统一方法，支持控制是否记录日志）
     * 
     * @param string $title 消息标题
     * @param string $content 消息内容
     * @param string $type 消息类型：info/success/error/warning
     * @param bool $log 是否记录日志，默认true
     * @param int|null $progress 进度百分比（0-100），null时不包含
     */
    private function formatMessage($title, $content, $type = "info", $log = true, $progress = null) {
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
        
        // 添加进度信息
        if ($progress !== null) {
            $messageArray['progress'] = max(0, min(100, intval($progress)));
        }
        
        // 添加统计信息
        $executionTime = round(microtime(true) - $this->startTime, 2);
        $messageArray['stats'] = [
            'completedCourses' => $this->completedCourses,
            'completedExams' => $this->completedExams,
            'executionTime' => $executionTime
        ];
        
        // 添加用户姓名（如果存在）
        if (isset($this->userName)) {
            $messageArray['userName'] = $this->userName;
        }
        
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
            
            if ($log) {
                $this->logMessage($errorMsg, 'error');
            }
            echo $errorMsg . "\n";
        } else {
            // JSON编码成功，根据参数决定是否记录日志
            if ($log) {
                $this->logMessage($msg, $type);
            }
            echo $msg . "\n";
            // 仅在必要时添加较小的填充字符，减少带宽浪费
            // 只在消息内容较短时添加填充，确保浏览器能及时接收
            if (strlen($msg) < 100) {
                echo str_pad('', 512) . "\n"; // 减少到512字节
            }
        }
        
        // 刷新输出缓冲区
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
        
        // 减少延迟，提高响应速度（仅在必要时延迟）
        // 只在快速连续输出时添加短暂延迟，避免消息堆积
        static $lastOutputTime = 0;
        $currentTime = microtime(true);
        if ($currentTime - $lastOutputTime < 0.01) { // 如果两次输出间隔小于10ms
            usleep(5000); // 只延迟5ms
        }
        $lastOutputTime = $currentTime;
    }
    
    /**
     * 刷新日志缓冲区
     */
    private function flushLogBuffer() {
        if (empty($this->logBuffer)) {
            return;
        }
        
        $date = date('Y-m-d');
        $logFile = "{$this->config['log_dir']}/log_{$date}.txt";
        $errorLogFile = "{$this->config['err_log_dir']}/error_{$date}.txt";
        
        $logEntries = [];
        $errorLogEntries = [];
        
        foreach ($this->logBuffer as $logItem) {
            $logEntry = $logItem['entry'];
            $logType = $logItem['type'];
            
            $logEntries[] = $logEntry;
            
            if ($logType == 'error' || $logType == 'warning') {
                $errorLogEntries[] = $logEntry;
            }
        }
        
        // 批量写入常规日志
        if (!empty($logEntries)) {
            file_put_contents($logFile, implode(PHP_EOL, $logEntries) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        
        // 批量写入错误日志
        if (!empty($errorLogEntries)) {
            file_put_contents($errorLogFile, implode(PHP_EOL, $errorLogEntries) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        
        // 清空缓冲区
        $this->logBuffer = [];
        
        // 检查日志文件大小并轮转
        $this->rotateLogIfNeeded($logFile);
    }
    
    /**
     * 智能日志轮转
     * 
     * @param string $logFile 日志文件路径
     */
    private function rotateLogIfNeeded($logFile) {
        if (!file_exists($logFile)) {
            return;
        }
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        $maxFiles = $this->config['max_backup_files'] ?? 10;
        
        if (filesize($logFile) > $maxSize) {
            $date = date('Y-m-d');
            $backupFile = "{$this->config['log_dir']}/log_{$date}_" . date('His') . ".bak";
            rename($logFile, $backupFile);
            
            // 清理旧备份
            $backups = glob("{$this->config['log_dir']}/log_{$date}_*.bak");
            if (count($backups) > $maxFiles) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                foreach (array_slice($backups, 0, -$maxFiles) as $oldBackup) {
                    @unlink($oldBackup);
                }
            }
        }
    }
    
    /**
     * 记录日志
     */
    private function logMessage($message, $logType = 'info') {
        // 检查日志级别
        $logLevel = $this->config['log_level'] ?? 'info';
        $logLevels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentLevel = $logLevels[$logType] ?? 1;
        $minLevel = $logLevels[$logLevel] ?? 1;
        
        // 如果当前日志级别低于配置的最小级别，不记录
        if ($currentLevel < $minLevel) {
            return;
        }
        
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
        if (isset($this->userName)) {
            $user .= '/' . $this->userName;
        }
        
        // 解析消息内容
        $decoded = json_decode($message, true);
        $action = isset($decoded['title']) ? $decoded['title'] : '未知操作';
        $content = isset($decoded['content']) ? $decoded['content'] : $message;
        
        // 如果是分割线，直接使用内容，不添加格式化前缀
        if ($action === '分割线') {
            $logEntry = $content;
        } else {
            // 格式化日志内容 - 对齐
            $userPadded = str_pad($user, 7, ' ');
            $logTypePadded = str_pad($logType, 7, ' ');
            $ipPadded = str_pad($ip, 12, ' ');
            
            // 为处理中文字符对齐，计算实际宽度并补齐空格
            $actionPadded = $action;
            $targetWidth = 10;
            $currentWidth = mb_strwidth($action, 'UTF-8');
            if ($currentWidth < $targetWidth) {
                $actionPadded .= str_repeat(' ', $targetWidth - $currentWidth);
            }

            $logEntry = "[{$date} {$time}] [{$logTypePadded}] [用户:{$userPadded}] [IP:{$ipPadded}] [操作:{$actionPadded}] {$content}";
        }
        
        // 添加到缓冲区
        $this->logBuffer[] = [
            'entry' => $logEntry,
            'type' => $logType,
            'time' => $microtime
        ];
        
        // 如果缓冲区满了，刷新
        $bufferSize = $this->config['log_buffer_size'] ?? 100;
        if (count($this->logBuffer) >= $bufferSize) {
            $this->flushLogBuffer();
        }
    }

    /**
     * 确保日志目录存在
     */
    private function ensureLogDirectoryExists() {
        // 创建常规日志目录
        if (!file_exists($this->config['log_dir'])) {
            mkdir($this->config['log_dir'], 0777, true);
        }
        
        // 创建错误日志目录
        if (!file_exists($this->config['err_log_dir'])) {
            mkdir($this->config['err_log_dir'], 0777, true);
        }
    }
    
    /**
     * 加密Token数据
     * 
     * @param array $data 要加密的数据
     * @return string 加密后的字符串
     */
    private function encryptToken($data) {
        $key = hash('sha256', $this->config['encryption_key'], true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(json_encode($data), 'AES-256-CBC', $key, 0, $iv);
        if ($encrypted === false) {
            return false;
        }
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * 解密Token数据
     * 
     * @param string $encryptedData 加密的数据
     * @return array|false 解密后的数据，失败返回false
     */
    private function decryptToken($encryptedData) {
        try {
            $key = hash('sha256', $this->config['encryption_key'], true);
            $data = base64_decode($encryptedData);
            if ($data === false) {
                return false;
            }
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            if ($decrypted === false) {
                return false;
            }
            return json_decode($decrypted, true);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 检查Token是否过期
     * 
     * @param array $tokenData Token数据
     * @return bool 是否过期
     */
    private function isTokenExpired($tokenData) {
        if (!isset($tokenData['timestamp'])) {
            return true;
        }
        $expireTime = $tokenData['timestamp'] + ($this->config['token_expire_hours'] * 60 * 60);
        return time() > $expireTime;
    }
    
    /**
     * 加载保存的token
     */
    private function loadTokens() {
        $tokenFile = $this->config['token_file'];
        if (!file_exists($tokenFile)) {
            return [];
        }
        
        $content = file_get_contents($tokenFile);
        if (empty($content)) {
            return [];
        }
        
        // 尝试解析JSON
        $tokens = json_decode($content, true);
        if ($tokens === null) {
            return [];
        }
        
        // 检查是否是加密格式（加密数据是base64字符串，不是数组）
        // 如果整个文件是加密的，先解密
        if (is_string($tokens) && strlen($tokens) > 100) {
            $decrypted = $this->decryptToken($tokens);
            if ($decrypted !== false) {
                $tokens = $decrypted;
            }
        }
        
        // 遍历每个用户的token，如果是加密的则解密
        foreach ($tokens as $username => &$tokenData) {
            if (is_array($tokenData)) {
                // 检查是否是加密格式（有encrypted标记或password是加密字符串）
                if (isset($tokenData['encrypted']) && $tokenData['encrypted'] === true) {
                    // 解密token和password
                    if (isset($tokenData['token_encrypted'])) {
                        $decryptedToken = $this->decryptToken($tokenData['token_encrypted']);
                        if ($decryptedToken !== false) {
                            $tokenData['token'] = $decryptedToken['token'] ?? null;
                            $tokenData['password'] = $decryptedToken['password'] ?? null;
                        }
                    }
                    unset($tokenData['encrypted'], $tokenData['token_encrypted']);
                } elseif (is_string($tokenData['token'] ?? null) && strlen($tokenData['token'] ?? '') > 100) {
                    // 可能是旧格式的加密数据，尝试解密
                    $decrypted = $this->decryptToken($tokenData['token']);
                    if ($decrypted !== false && isset($decrypted['token'])) {
                        $tokenData['token'] = $decrypted['token'];
                        if (isset($decrypted['password'])) {
                            $tokenData['password'] = $decrypted['password'];
                        }
                    }
                }
            }
        }
        
        return $tokens;
    }
    
    /**
     * 保存token到文件
     */
    private function saveTokens($tokens) {
        // 加密每个用户的token数据
        $encryptedTokens = [];
        foreach ($tokens as $username => $tokenData) {
            if (is_array($tokenData)) {
                // 加密token和password
                $dataToEncrypt = [
                    'token' => $tokenData['token'] ?? null,
                    'password' => $tokenData['password'] ?? null
                ];
                $encrypted = $this->encryptToken($dataToEncrypt);
                if ($encrypted !== false) {
                    $encryptedTokens[$username] = [
                        'token_encrypted' => $encrypted,
                        'timestamp' => $tokenData['timestamp'] ?? time(),
                        'encrypted' => true
                    ];
                } else {
                    // 加密失败，保存未加密版本（降级处理）
                    $encryptedTokens[$username] = $tokenData;
                }
            } else {
                $encryptedTokens[$username] = $tokenData;
            }
        }
        
        file_put_contents($this->config['token_file'], json_encode($encryptedTokens, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * 开始日志会话
     */
    private function startLogSession() {
        // 添加开始分割线
        $this->logMessage(json_encode([
            'type' => 'info',
            'title' => '分割线',
            'content' => str_repeat('=', 120)
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
        // 刷新日志缓冲区
        $this->flushLogBuffer();
        
        // 计算执行时间
        $endTime = microtime(true);
        $executionTime = round($endTime - $this->startTime, 2);
        
        // 添加结束分割线和统计信息
        $this->formatMessage('任务结束', "执行时长：{$executionTime}秒，完成课程：{$this->completedCourses}个，完成考试：{$this->completedExams}个", 'info', true, 100);
        
        $this->logMessage(json_encode([
            'type' => 'info',
            'title' => '分割线',
            'content' => str_repeat('=', 120)
        ]), 'info');
        
        // 最后刷新一次日志缓冲区
        $this->flushLogBuffer();
    }
}

// 创建并运行系统
$system = new AutoLearnSystem();
$system->run();
?>
