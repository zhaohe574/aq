# 自动考试系统

这是一个基于PHP的自动化考试处理系统，专为教育机构开发，用于自动完成在线考试任务。系统可以自动登录、获取考试信息、答题并提交，大幅提高工作效率。

## 项目特点

- **模块化架构设计**：使用面向对象编程和依赖注入，便于扩展和维护
- **完整的错误处理和日志记录**：详细记录每一步操作，便于排查问题
- **安全的用户认证和授权**：支持特定用户访问，防止未授权使用
- **Token缓存和自动刷新**：智能管理API令牌，减少重复登录
- **实时进度反馈**：前端流式显示处理状态，提供良好用户体验
- **美观的用户界面**：响应式设计，支持暗色模式，适配各种设备
- **高度可配置**：通过配置文件轻松调整系统行为

## 项目结构

```
/
├── config/                 # 配置文件目录
│   └── config.php         # 主配置文件（API设置、用户授权、日志配置等）
├── src/                   # 源代码目录
│   ├── Core/             # 核心类
│   │   ├── Auth.php      # 认证类（处理用户认证和Token管理）
│   │   ├── Logger.php    # 日志类（处理日志记录和轮转）
│   │   └── Api.php       # API请求类（处理所有HTTP请求）
│   ├── Services/         # 业务逻辑
│   │   ├── ExamService.php    # 考试服务（处理考试相关业务）
│   │   └── CourseService.php  # 课程服务（处理课程相关业务）
│   └── Utils/            # 工具类（辅助功能）
├── public/               # 公共访问目录
│   ├── index.php        # 后端入口文件
│   ├── index.html       # 前端页面
│   └── .htaccess        # Web服务器配置
├── storage/             # 存储目录
│   ├── logs/           # 日志文件
│   │   ├── log_*.txt   # 一般日志
│   │   └── error_*.txt # 错误日志
│   └── cache/          # 缓存文件
│       └── tokens.json # 缓存的令牌信息
├── vendor/             # Composer依赖
├── composer.json       # Composer配置
└── .gitignore          # Git忽略文件
```

## 系统要求

- **PHP**: 8.1或更高版本
- **扩展**: JSON扩展（必须）
- **Web服务器**: Apache（推荐）或Nginx
- **权限**: storage目录需要写入权限
- **浏览器**: 支持现代浏览器（Chrome, Firefox, Safari, Edge）

## 安装步骤

1. **克隆项目到本地**：
   ```bash
   git clone https://github.com/yourusername/auto-exam-system.git
   cd auto-exam-system
   ```

2. **安装依赖**：
   ```bash
   composer install
   ```

3. **配置权限**：
   ```bash
   # Linux/macOS
   chmod -R 777 storage/
   
   # Windows (确保IUSR或相应的应用程序池用户有写入权限)
   ```

4. **配置Web服务器**：

   **Apache**:
   ```apache
   <VirtualHost *:80>
       ServerName exam-system.local
       DocumentRoot /path/to/auto-exam-system/public
       
       <Directory "/path/to/auto-exam-system/public">
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/error.log
       CustomLog ${APACHE_LOG_DIR}/access.log combined
   </VirtualHost>
   ```

   **Nginx**:
   ```nginx
   server {
       listen 80;
       server_name exam-system.local;
       root /path/to/auto-exam-system/public;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
           index index.html index.php;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

5. **修改配置**：
   
   编辑 `config/config.php` 文件，根据需要调整配置：
   
   ```php
   // 设置授权用户
   'authorized_users' => ['JS05533', 'JS00003'],
   
   // 调整API设置
   'api' => [
       'base_url' => 'http://your-api-url.com/api',
       'timeout' => 30,
   ],
   
   // 调整日志设置
   'app' => [
       'debug' => true,  // 生产环境设为false
       'log_level' => 'info',
   ],
   ```

## 使用说明

1. **访问系统**：
   - 打开浏览器，访问配置的域名或IP
   - 默认访问 `index.html` 页面

2. **登录系统**：
   - 使用授权账号登录（目前支持：JS05533, JS00003）
   - 输入对应的密码

3. **系统处理流程**：
   - 系统会自动获取用户Token
   - 获取可用的考试和课程任务
   - 自动完成考试（包括获取题目、答题和提交）
   - 实时显示处理进度和结果
   - 详细记录每一步操作到日志文件

4. **查看结果**：
   - 所有处理结果会实时显示在界面上
   - 成功完成的任务会以绿色标识
   - 错误或警告会用相应颜色标识

## 调试与排错

1. **启用调试模式**：
   - 在 `config/config.php` 中设置 `'debug' => true`
   - 调整日志级别：`'log_level' => 'debug'`

2. **查看日志**：
   - 常规日志：`storage/logs/log_YYYY-MM-DD.txt`
   - 错误日志：`storage/logs/error_YYYY-MM-DD.txt`

3. **常见问题**：
   - **认证失败**：检查用户名和密码是否正确，用户是否在授权列表中
   - **API连接失败**：检查API地址和网络连接
   - **执行超时**：考虑增加 `timeout` 配置值或优化代码

4. **浏览器调试**：
   - 使用浏览器开发者工具的网络面板查看请求
   - 检查控制台是否有JavaScript错误

## 安全说明

- **存储目录保护**：确保 `storage/` 目录不可通过Web直接访问
- **敏感信息保护**：`tokens.json` 文件包含敏感信息，确保其安全
- **授权限制**：只有配置中的授权用户才能使用系统
- **HTTPS建议**：生产环境建议使用HTTPS协议访问
- **日志安全**：日志文件可能包含敏感信息，定期检查和清理

## 开发指南

### 添加新功能

1. **新增服务类**：
   - 在 `src/Services/` 目录下创建新的服务类
   - 遵循现有的依赖注入模式

2. **扩展前端功能**：
   - 修改 `public/index.html` 添加新的UI元素
   - 在JavaScript中添加对应的处理逻辑

### 代码风格

- 遵循PSR-4自动加载规范
- 使用强类型声明
- 保持方法简短，单一职责
- 编写详细的注释

### 日志最佳实践

- 使用适当的日志级别：
  - `debug`: 详细的调试信息
  - `info`: 一般信息
  - `warning`: 警告（不影响主要功能）
  - `error`: 错误（影响功能）
- 在关键操作前后添加日志记录
- 记录足够上下文信息，便于排查问题

## 版本历史

- **2.0** (当前)
  - 重构项目结构，采用模块化设计
  - 添加实时进度反馈
  - 改进日志系统
  - 添加用户界面

- **1.0**
  - 初始版本
  - 基本的考试自动化功能

## 贡献指南

1. Fork 项目
2. 创建特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建Pull Request

## 联系方式

如有问题或建议，请通过以下方式联系：

- **项目维护者**：[您的名字]
- **电子邮件**：[您的邮箱]

## 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件