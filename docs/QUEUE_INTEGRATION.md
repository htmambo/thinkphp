# Queue 队列系统集成文档

> 完成时间: 2024-12-28
> 版本: 1.0.0
> 状态: ✅ 完成

---

## 📋 目录

1. [概述](#概述)
2. [安装配置](#安装配置)
3. [快速开始](#快速开始)
4. [创建任务](#创建任务)
5. [队列命令](#队列命令)
6. [任务调度](#任务调度)
7. [失败处理](#失败处理)
8. [高级特性](#高级特性)
9. [生产环境部署](#生产环境部署)
10. [故障排查](#故障排查)

---

## 概述

ThinkPHP 现已集成 Laravel Queue 组件，提供强大的异步任务处理能力。

### 主要特性��当前实现范围）

- ✅ **多驱动支持**：Database、Redis、Sync
- ✅ **任务重试**：自动重试失败任务
- ✅ **延迟任务**：支持延迟执行
- 🚧 **任务链/批处理**：需手动实现（当前仅提供 Job 基类与基础分发）
- ✅ **失败记录**：记录失败任务便于排查
- ✅ **守护进程**：支持长时间运行的后台 Worker
- ✅ **内存控制**：防止内存泄漏自动重启

### 技术架构

```
ThinkPHP 应用
    ↓
Think\Queue\Job (任务基类)
    ↓
QueueServiceProvider (服务提供者)
    ↓
Illuminate\Queue\QueueManager
    ↓
Queue Driver (Database/Redis/Sync)
```

---

## 安装配置

### 1. 依赖安装

composer.json 已包含以下依赖：

```json
{
    "require": {
        "illuminate/queue": "^11.0",
        "illuminate/events": "^11.0"
    }
}
```

运行 composer 安装：

```bash
composer install
```

### 2. 注册服务提供者

在应用入口文件或配置文件中注册 QueueServiceProvider：

```php
// 在应用启动时注册
use Think\Queue\QueueServiceProvider;
use Think\Container;

$container = Container::getInstance();

$provider = new QueueServiceProvider($container);
$provider->register();
$provider->boot();

// 将 queue 服务注册到 Think Container
// 注意：QueueServiceProvider 已经在内部处理了注册
// 你可以直接使用 QueueServiceProvider::queue() 方法获取队列实例
```

### 3. 配置队列

将 `src/Queue/queue.php` 复制到应用配置目录：

```bash
cp src/Queue/queue.php Common/Conf/queue.php
```

在主配置文件中引入：

```php
// Common/Conf/config.php
return [
return array_merge(
    [
        // ... 其他配置
    ],
    include __DIR__ . '/queue.php'
);
```

### 4. 创建数据表（使用数据库驱动时）

```sql
-- 队列任务表
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 失败任务表
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

---

## 快速开始

### 1. 创建第一个任务

```php
<?php
// Application/Jobs/SendEmailJob.php

namespace Application\Jobs;

use Think\Queue\Job;
use Think\Queue\Dispatcher;

class SendEmailJob extends Job
{
    protected $email;
    protected $subject;
    protected $content;

    public function __construct(string $email, string $subject, string $content)
    {
        $this->email = $email;
        $this->subject = $subject;
        $this->content = $content;

        // 可选：设置队列属性
        $this->queue = 'emails';
        $this->tries = 3;
        $this->timeout = 120;
    }

    public function handle(Dispatcher $dispatcher): void
    {
        // 发送邮件逻辑
        $mailer = $dispatcher->makeFromThink('mailer');

        $result = $mailer->send($this->email, $this->subject, $this->content);

        if (!$result) {
            throw new \Exception('邮件发送失败');
        }
    }

    public function failed(\Throwable $e): void
    {
        // 任务失败时的处理
        // 例如：记录日志、发送告警等
        error_log('邮件发送失败: ' . $e->getMessage());
    }
}
```

### 2. 分发任务

```php
<?php
use Application\Jobs\SendEmailJob;

// 异步执行（推荐）
SendEmailJob::dispatch('user@example.com', '欢迎注册', '欢迎加入我们！');

// 延迟执行（60秒后执行）
SendEmailJob::dispatchLater(60, 'user@example.com', '延迟发送', '这是一封延迟邮件');

// 同步执行（用于测试）
SendEmailJob::dispatchNow('user@example.com', '测试邮件', '这是一封测试邮件');
```

### 3. 启动 Worker 处理任务

```bash
# 基本用法
php think queue:work

# 指定连接和队列
php think queue:work -c redis -Q emails

# 守护进程模式（推荐用于生产环境）
php think queue:work -d

# 只处理一个任务（用于调试）
php think queue:work -o

# 设置任务超时和重试次数
php think queue:work --timeout=120 --tries=5

# 设置内存限制和重启条件
php think queue:work --memory=256 --jobs=1000 --max-time=3600
```

---

## 创建任务

### 任务基类

所有队列任务都必须继承 `Think\Queue\Job`：

```php
use Think\Queue\Job;

class MyJob extends Job
{
    // 任务属性
    public ?string $connection = 'redis';
    public ?string $queue = 'default';
    public int $tries = 3;
    public int $timeout = 60;
    public int|array $backoff = 30; // 重试延迟（秒或数组）

    // 必须实现 handle 方法
    public function handle(Dispatcher $dispatcher): void
    {
        // 任务逻辑
    }

    // 可选：失败处理
    public function failed(\Throwable $e): void
    {
        // 失败处理逻辑
    }
}
```

### 任务属性

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `$connection` | string|null | null | 队列连接名称 |
| `$queue` | string|null | null | 队列名称 |
| `$tries` | int | 3 | 最大尝试次数 |
| `$timeout` | int | 60 | 任务超时时间（秒） |
| `$backoff` | int\|array | 0 | 重试延迟时间 |
| `$maxExceptions` | int | 3 | 最大异常数量 |
| `$afterCommit` | bool | false | 是否在事务提交后分发 |

### 依赖注入

在 `handle()` 方法中可以通过 Dispatcher 解析依赖：

```php
public function handle(Dispatcher $dispatcher): void
{
    // 解析 ThinkPHP 容器服务
    $db = $dispatcher->makeFromThink('db');
    $cache = $dispatcher->makeFromThink('cache');

    // 解析 Illuminate 容器服务
    $events = $dispatcher->make('events');

    // 使用服务完成任务
    $data = $db->table('users')->select();
}
```

---

## 队列命令

### queue:work - 处理队列任务

```bash
# 基本用法
php think queue:work

# 常用选项
-c, --connection   队列连接名称（default, redis, database）
-Q, --queue        队列名称
-d, --daemon       以守护进程方式运行
-o, --once         只处理一个任务后退出
-t, --tries        任务最大尝试次数（默认: 3）
-T, --timeout      任务超时时间（秒，默认: 60）
-s, --sleep        没有任务时休眠时间（秒，默认: 3）
-j, --jobs         处理多少个任务后重启（默认: 0）
-m, --max-time     运行多少秒后重启（默认: 0）
-M, --memory       内存限制（MB，默认: 128）
```

**示例**：

```bash
# 生产环境推荐配置
php think queue:work \
    --connection=redis \
    --queue=default,emails \
    --daemon \
    --tries=3 \
    --timeout=60 \
    --memory=256 \
    --sleep=3 \
    --jobs=1000 \
    --max-time=3600
```

### queue:restart - 重启所有 Worker

```bash
php think queue:restart
```

该命令会在缓存中创建一个重启信号，所有运行中的 Worker 在处理完当前任务后会自动重启。

### queue:list - 查看队列状态

```bash
# 查看队列状态
php think queue:list

# 指定连接和队列
php think queue:list --connection=redis --queue=emails

# 限制显示数量
php think queue:list --limit=50
```

---

## 任务调度

### 立即分发

```php
MyJob::dispatch($arg1, $arg2);
```

### 延迟分发

```php
// 60秒后执行
MyJob::dispatchLater(60, $arg1, $arg2);
```

### 同步执行

```php
// 立即同步执行（不放入队列）
MyJob::dispatchNow($arg1, $arg2);
```

### 指定队列和连接

```php
$job = new MyJob($arg1, $arg2);
$job->queue = 'high-priority';
$job->connection = 'redis';

$container = Container::getInstance();
$queue = $container->make('queue');
$queue->connection('redis')->pushOn('high-priority', $job);
```

---

## 失败处理

### 任务失败回调

```php
class MyJob extends Job
{
    public function failed(\Throwable $e): void
    {
        // 记录日志
        error_log('任务失败: ' . $e->getMessage());

        // 发送告警
        mail('admin@example.com', '任务失败', $e->getMessage());

        // 清理资源
        // ...
    }
}
```

### 查看失败任务

```bash
# 使用数据库查询
mysql> SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

### 重试失败任务

可以创建自定义命令重试失败任务：

```php
// 从失败任务表读取并重新分发
$failedJobs = Db::table('failed_jobs')->select();

foreach ($failedJobs as $job) {
    $payload = json_decode($job['payload'], true);
    // 重新分发任务...
    Db::table('failed_jobs')->where('id', $job['id'])->delete();
}
```

---

## 高级特性

### 任务中间件

```php
class MyJob extends Job
{
    public function middleware(): array
    {
        return [
            new RateLimitedMiddleware('emails'),
            new ThrottleMiddleware(10, 60), // 每分钟10次
        ];
    }
}
```

### 任务链（需手动实现）

```php
// 依次执行多个任务
$chain = [
    new Step1Job($data),
    new Step2Job($data),
    new Step3Job($data),
];

foreach ($chain as $job) {
    $job->dispatch();
}
```

### 批量任务（需手动实现）

```php
// 并行执行多个任务
$batch = [
    new ProcessDataJob(1),
    new ProcessDataJob(2),
    new ProcessDataJob(3),
];

foreach ($batch as $job) {
    $job->dispatch();
}
```

---

## 生产环境部署

### 1. 使用 Supervisor 管理 Worker

安装 Supervisor：

```bash
sudo apt-get install supervisor
```

配置文件 `/etc/supervisor/conf.d/queue-worker.conf`：

```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/think queue:work --daemon --sleep=3 --tries=3 --max-time=3600 --memory=256
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/queue-worker.log
stopwaitsecs=3600
numprocs=3
```

管理命令：

```bash
# 启动
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start queue-worker:*

# 停止
sudo supervisorctl stop queue-worker:*

# 重启
sudo supervisorctl restart queue-worker:*

# 查看状态
sudo supervisorctl status
```

### 2. 监控队列健康状态

```bash
# 创建定时任务检查队列状态
*/5 * * * * php /path/to/think queue:list --connection=redis | mail -s "队列状态" admin@example.com
```

### 3. 日志管理

```bash
# 配置日志轮换
/var/log/queue-worker.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 4. 性能优化

- 使用 Redis 驱动而不是 Database
- 根据负载调整 Worker 进程数
- 合理设置内存限制和重启条件
- 使用多个队列分配任务优先级

---

## 故障排查

### 问题 1：Worker 不处理任务

**可能原因**：
- Queue 服务未注册
- 队列配置错误
- 数据库连接失败

**解决方案**：
```bash
# 检查队列服务状态
php think queue:list

# 检查配置
php think

# 测试队列连接
php think queue:work -o
```

### 问题 2：任务执行失败

**可能原因**：
- 任务超时
- 内存不足
- 代码异常

**解决方案**：
```bash
# 增加超时时间
php think queue:work --timeout=120

# 增加内存限制
php think queue:work --memory=512

# 查看错误日志
tail -f /var/log/queue-worker.log
```

### 问题 3：Worker 内存泄漏

**可能原因**：
- 长时间运行未释放资源
- 静态变量累积

**解决方案**：
```bash
# 设置重启条件
php think queue:work --jobs=1000 --max-time=3600 --memory=256
```

### 问题 4：Redis 连接失败

**可能原因**：
- Redis 服务未启动
- 连接配置错误

**解决方案**：
```bash
# 检查 Redis 状态
redis-cli ping

# 测试连接
redis-cli -h 127.0.0.1 -p 6379

# 检查配置
C('REDIS_HOST'); C('REDIS_PORT');
```

---

## 示例项目

### 完整的邮件任务示例

```php
<?php
// Application/Jobs/SendWelcomeEmailJob.php

namespace Application\Jobs;

use Think\Queue\Job;
use Think\Queue\Dispatcher;

class SendWelcomeEmailJob extends Job
{
    protected $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->queue = 'emails';
        $this->tries = 3;
        $this->timeout = 120;
    }

    public function handle(Dispatcher $dispatcher): void
    {
        // 获取用户信息
        $db = $dispatcher->makeFromThink('db');
        $user = $db->table('users')->where('id', $this->userId)->find();

        if (!$user) {
            throw new \Exception('用户不存在');
        }

        // 发送邮件
        $subject = '欢迎加入我们';
        $content = "亲爱的 {$user['username']}，欢迎注册！";

        // 这里调用实际的邮件发送服务
        $mailer = $dispatcher->makeFromThink('mailer');
        $result = $mailer->send($user['email'], $subject, $content);

        if (!$result) {
            throw new \Exception('邮件发送失败');
        }
    }

    public function failed(\Throwable $e): void
    {
        // 记录失败日志
        error_log("欢迎邮件发送失败 (用户ID: {$this->userId}): " . $e->getMessage());
    }
}
```

### 在控制器中分发任务

```php
<?php
// Application/Controller/UserController.php

namespace Application\Controller;

use Think\Controller;
use Application\Jobs\SendWelcomeEmailJob;

class UserController extends Controller
{
    public function register()
    {
        // 创建用户
        $userId = D('User')->add([
            'username' => I('post.username'),
            'email' => I('post.email'),
            'created_at' => time(),
        ]);

        // 分发欢迎邮件任务（异步）
        SendWelcomeEmailJob::dispatch($userId);

        $this->success('注册成功', '/');
    }
}
```

---

## 最佳实践

1. **任务设计**
   - 任务应该是幂等的（可以安全地重复执行）
   - 任务应该简洁快速，避免长时间运行
   - 避免在任务中传递大量数据，传递 ID 即可

2. **队列配置**
   - 生产环境使用 Redis 驱动
   - 开发测试使用 Sync 驱动
   - 使用多个队列分配任务优先级

3. **Worker 管理**
   - 使用 Supervisor 管理 Worker 进程
   - 合理设置 Worker 数量和资源限制
   - 定期重启 Worker 防止内存泄漏

4. **监控告警**
   - 监控队列长度
   - 监控任务失败率
   - 设置告警阈值

5. **错误处理**
   - 实现 `failed()` 方法
   - 记录详细的错误日志
   - 设置合理的重试次数和延迟

---

## 常见问题 (FAQ)

### Q: 如何选择队列驱动？

**A**:
- **Sync**: 开发测试环境，任务立即同步执行
- **Database**: 简单的生产环境，无需额外服务
- **Redis**: 推荐的生产环境方案，性能最好

### Q: 如何实现任务优先级？

**A**: 使用不同的队列名称：
```php
$job->queue = 'high-priority'; // 高优先级
$job->queue = 'default';       // 默认优先级
$job->queue = 'low-priority';  // 低优先级

// Worker 处理顺序
php think queue:work --queue=high-priority,default,low-priority
```

### Q: 如何实现定时任务？

**A**: 结合 crontab 使用：
```bash
# 每分钟执行一次
* * * * * php /path/to/think queue:work --once
```

---

## 总结

ThinkPHP Queue 集成提供了完整的异步任务处理能力，包括：

- ✅ 多驱动支持（Database/Redis/Sync）
- ✅ 任务重试和失败处理
- ✅ 延迟任务和调度
- ✅ 完善的命令行工具
- ✅ 生产环境部署方案

通过合理使用队列系统，可以显著提升应用性能和用户体验。

---

**文档版本**: 1.0.0
**更新时间**: 2024-12-28
**维护者**: ThinkPHP Team
