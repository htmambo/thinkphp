# Monolog 日志系统集成

本文档描述了ThinkPHP框架中使用Monolog进行日志管理的实现和改进。

## 概述

所有日志功能已完全迁移至基于Monolog库的统一日志系统。Monolog提供了灵活的、可扩展的日志处理能力。

## 改动概要

### 1. Log.php - 核心日志类改进

**改动内容：**
- 升级到完整的Monolog集成，使用 `RotatingFileHandler` 而不仅是 `StreamHandler`
- 添加日志文件轮转功能（支持按大小轮转）
- 改进日志格式化（使用 `LineFormatter` 提供统一的日志格式）
- 添加配置管理（$config 静态属性）
- 新增 `getLogger()` 方法供外部获取Logger实例

**主要特性：**
- 日志级别常量：EMERG, ALERT, CRIT, ERR, WARN, NOTICE, INFO, DEBUG, SQL
- 自动初始化日志目录
- 日志文件轮转（最多保留10个日志文件）
- 统一的日志格式：`[时间] 通道.级别: 消息 上下文 额外数据`
- 非CLI环境下支持浏览器控制台输出
- 完全向后兼容的API

**配置参数：**
- `LOG_PATH`: 日志目录（默认: APP_PATH . 'Runtime/Logs/'）
- `LOG_LEVEL`: 允许记录的日志级别（默认: 'EMERG,ALERT,CRIT,ERR'）
- `LOG_FILE_SIZE`: 日志文件大小限制（默认: 2097152 字节）
- `LOG_MAX_FILES`: 保留的最大日志文件数（默认: 10）

### 2. App.php - 应用初始化

**改动内容：**
- 在 `App::run()` 方法中添加 `Log::init()` 调用
- 确保日志系统在应用启动时完成初始化

**位置：** `App.php` line 253

### 3. Console.php - 命令行工具初始化

**改动内容：**
- 在 `Console::init()` 方法中添加 `Log::init()` 调用
- 确保CLI命令执行时日志系统已初始化

**位置：** `Console.php` line 118

### 4. ShowPageTraceBehavior.php - 页面Trace日志记录

**改动内容：**
- 将 `error_log()` 的直接写入改为使用 `Log::write()` 方法
- 统一使用Monolog进行日志记录

**变更：**
```php
// 之前
error_log(str_replace('<br/>', "\r\n", $content), 3, C('LOG_PATH') . date('y_m_d') . '_trace.log');

// 之后
\Think\Log::write(str_replace('<br/>', "\r\n", $content), \Think\Log::INFO);
```

## 日志级别对应表

| ThinkPHP级别 | Monolog级别 | 日志级别值 |
|------------|-----------|---------|
| EMERGENCY  | EMERGENCY | 600 |
| ALERT      | ALERT     | 550 |
| CRITICAL   | CRITICAL  | 500 |
| ERROR      | ERROR     | 400 |
| WARNING    | WARNING   | 300 |
| NOTICE     | NOTICE    | 250 |
| INFO       | INFO      | 200 |
| DEBUG      | DEBUG     | 100 |
| SQL        | INFO      | 200 |

## 使用示例

### 基本日志记录

```php
// 直接写入日志
\Think\Log::write('This is an error message', \Think\Log::ERR);

// 使用魔术方法（按级别）
\Think\Log::error('An error occurred');
\Think\Log::info('Information message');
\Think\Log::debug('Debug message');

// 条件记录（根据配置的日志级别过滤）
\Think\Log::record('Important info', \Think\Log::INFO, false);

// 强制记录（忽略级别过滤）
\Think\Log::record('Force log this', \Think\Log::DEBUG, true);
```

### 获取Logger实例

```php
$logger = \Think\Log::getLogger();
$logger->info('Some message with context', ['user_id' => 123]);
```

## 日志输出

日志文件位置：`{LOG_PATH}/application.log`

日志文件会按大小自动轮转，保留最多10个备份文件：
- `application.log` - 当前日志文件
- `application.log.1` - 第一个备份
- `application.log.2` - 第二个备份
- 以此类推...

## 向后兼容性

所有现有的日志调用方式都已保留兼容性：
- `Log::write()` - 直接写入日志
- `Log::record()` - 条件性记录日志
- `Log::error()`, `Log::info()` 等魔术方法 - 按级别记录
- 所有旧的参数都被保留（虽然某些参数如 $type, $destination, $directSave 在Monolog实现中未使用）

## 性能考虑

- 日志初始化只执行一次（单例模式）
- 使用RotatingFileHandler提高性能
- 日志级别过滤在配置层面进行
- 浏览器控制台输出仅在非CLI环境启用

## 扩展性

Log类可以轻松扩展以支持更多的Handler：

```php
// 在应用中自定义日志Handler
$logger = \Think\Log::getLogger();
$logger->pushHandler(new \Monolog\Handler\EmailHandler(...));
$logger->pushHandler(new \Monolog\Handler\SyslogHandler(...));
// 等等
```
