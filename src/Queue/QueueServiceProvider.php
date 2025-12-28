<?php

declare(strict_types=1);

namespace Think\Queue;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Events\Dispatcher as IlluminateDispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Queue\Connectors\SyncConnector;
use Think\Support\ServiceProvider;
use Think\Container as ThinkContainer;
use Think\Queue\ConfigRepository;

/**
 * Queue 服务提供者
 *
 * 将 Laravel Queue 集成到 ThinkPHP
 *
 * @package Think\Queue
 */
class QueueServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 创建 Illuminate Container 用于 QueueManager 内部使用
        $illuminateContainer = $this->getIlluminateContainer();

        // 注册事件分发器到 Illuminate Container
        $this->registerDispatcher($illuminateContainer);

        // 创建 QueueManager 并同时绑定到两个容器
        $queueManager = $this->createQueueManager($illuminateContainer);

        // 绑定到 Think Container（供 Job::dispatch() 和 Commands 使用）
        $this->app->instance('queue', $queueManager);

        // 也绑定到 Illuminate Container（供 QueueManager 内部使用）
        $illuminateContainer->instance('queue', $queueManager);
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        // Queue 服务启动逻辑（如果需要）
    }

    /**
     * 创建 Illuminate Container 实例
     *
     * @return \Illuminate\Container\Container
     */
    protected function getIlluminateContainer(): IlluminateContainer
    {
        $illuminateContainer = new IlluminateContainer();

        // 给 QueueManager 提供配置（使用 ConfigRepository 支持 dot key 解析）
        $queueConfig = $this->getQueueConfig();
        $config = new ConfigRepository(['queue' => $queueConfig]);
        $illuminateContainer->instance('config', $config);

        // 将 Think Container 单例绑定到 Illuminate Container
        // 这样 Job 执行链可以正确解析 Think\Container 依赖
        $illuminateContainer->instance(ThinkContainer::class, $this->app);
        $illuminateContainer->alias(ThinkContainer::class, 'think.container');

        // 将 Think Container 的关键服务复制到 Illuminate Container（仅当确实存在）
        $thinkContainer = $this->app;

        if ($thinkContainer->bound('db')) {
            $illuminateContainer->instance('db', $thinkContainer->get('db'));
        }

        if ($thinkContainer->bound('redis')) {
            $illuminateContainer->instance('redis', $thinkContainer->get('redis'));
        }

        return $illuminateContainer;
    }

    /**
     * 注册事件分发器
     *
     * @param \Illuminate\Container\Container $container
     * @return void
     */
    protected function registerDispatcher(IlluminateContainer $container): void
    {
        $container->instance('events', new IlluminateDispatcher($container));

        $container->alias('events', \Illuminate\Events\Dispatcher::class);
        $container->alias('events', \Illuminate\Contracts\Events\Dispatcher::class);

        // 注册 Bus Dispatcher（用于 Job 执行链）
        $busDispatcher = new \Illuminate\Bus\Dispatcher($container);
        $container->instance('Illuminate\Contracts\Bus\Dispatcher', $busDispatcher);
        $container->instance('Illuminate\Bus\Dispatcher', $busDispatcher);
        $container->alias('Illuminate\Bus\Dispatcher', 'bus.dispatcher');
    }

    /**
     * 创建队列管理器
     *
     * @param \Illuminate\Container\Container $container
     * @return \Illuminate\Queue\QueueManager
     */
    protected function createQueueManager(IlluminateContainer $container): QueueManager
    {
        $manager = new QueueManager($container);

        // 仅当依赖满足时启用 database connector（避免"看起来支持，实际不可用"）
        if ($container->bound('db')) {
            $manager->addConnector('database', function () use ($container) {
                return new DatabaseConnector($container->make('db'));
            });
        }

        // 仅当依赖满足时启用 redis connector
        if ($container->bound('redis')) {
            $manager->addConnector('redis', function () use ($container) {
                return new RedisConnector($container->make('redis'));
            });
        }

        // 添加 Sync 驱动（用于测试）
        $manager->addConnector('sync', function () {
            return new SyncConnector();
        });

        return $manager;
    }

    /**
     * 获取队列配置
     *
     * @return array
     */
    protected function getQueueConfig(): array
    {
        return [
            'default' => $this->getConfig('QUEUE_DEFAULT', 'sync'),
            'connections' => [
                'database' => [
                    'driver' => 'database',
                    'table' => $this->getConfig('QUEUE_TABLE', 'jobs'),
                    'queue' => $this->getConfig('QUEUE_QUEUE', 'default'),
                    'retry_after' => (int)$this->getConfig('QUEUE_RETRY_AFTER', 90),
                    'after_commit' => false,
                    // 数据库连接名称应由 db 管理器解析
                    'connection' => $this->getConfig('QUEUE_DB_CONNECTION', null),
                ],
                'redis' => [
                    'driver' => 'redis',
                    'connection' => $this->getConfig('QUEUE_REDIS_CONNECTION', 'default'),
                    'queue' => $this->getConfig('QUEUE_QUEUE', 'default'),
                    'retry_after' => (int)$this->getConfig('QUEUE_RETRY_AFTER', 90),
                    'block_for' => null,
                    'after_commit' => false,
                ],
                'sync' => [
                    'driver' => 'sync',
                ],
            ],
            'failed' => [
                'driver' => $this->getConfig('QUEUE_FAILED_DRIVER', 'database'),
                'database' => $this->getConfig('QUEUE_FAILED_DATABASE', 'default'),
                'table' => $this->getConfig('QUEUE_FAILED_TABLE', 'failed_jobs'),
            ],
        ];
    }

    /**
     * 获取配置值
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        // 优先使用 ThinkPHP 的 C() 函数
        if (function_exists('C')) {
            $value = C($key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        // 尝试从全局配置读取
        if (isset($GLOBALS[$key])) {
            return $GLOBALS[$key];
        }

        return $default;
    }

    /**
     * 获取队列实例
     *
     * @param string|null $name 队列连接名称
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public static function queue(?string $name = null): \Illuminate\Contracts\Queue\Queue
    {
        $container = ThinkContainer::getInstance();

        if ($container->bound('queue')) {
            $manager = $container->get('queue');
            return $manager->connection($name);
        }

        throw new \RuntimeException('Queue service provider not registered. Please register Think\\Queue\\QueueServiceProvider first.');
    }
}
