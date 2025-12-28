<?php

declare(strict_types=1);

namespace Think\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Think\Container;

/**
 * 队列任务基类
 *
 * 所有异步任务都应该继承此类
 *
 * @package Think\Queue
 */
abstract class Job implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    /**
     * 队列连接名称
     *
     * @var string|null
     */
    public ?string $connection = null;

    /**
     * 队列名称
     *
     * @var string|null
     */
    public ?string $queue = null;

    /**
     * 任务最大尝试次数
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * 任务超时时间（秒）
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * 任务失败前的延迟时间（秒）
     *
     * @var int|array
     */
    public int|array $backoff = 0;

    /**
     * 任务执行的最大异常数量
     *
     * @var int
     */
    public int $maxExceptions = 3;

    /**
     * 任务是否应在事务提交后分发
     *
     * @var bool
     */
    public bool $afterCommit = false;

    /**
     * 处理任务
     *
     * @param \Think\Queue\Dispatcher $dispatcher
     * @return void
     */
    abstract public function handle(Dispatcher $dispatcher): void;

    /**
     * 任务失败时的处理
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed(\Throwable $e): void
    {
        // 默认不做任何处理
        // 子类可以覆盖此方法来实现自定义失败处理
    }

    /**
     * 分发任务到队列
     *
     * @param mixed ...$arguments
     * @return void
     */
    public static function dispatch(...$arguments): void
    {
        $job = new static(...$arguments);

        $container = Container::getInstance();

        if ($container->bound('queue')) {
            $queue = $container->make('queue');

            $connection = $job->connection ?: null;
            $queueName = $job->queue ?: null;

            $conn = $queue->connection($connection);
            if ($queueName !== null && $queueName !== '') {
                $conn->pushOn($queueName, $job);
            } else {
                $conn->push($job);
            }
        } else {
            throw new \RuntimeException('Queue service not available. Please register QueueServiceProvider first.');
        }
    }

    /**
     * 延迟分发任务到队列
     *
     * @param int $delay 延迟时间（秒）
     * @param mixed ...$arguments
     * @return void
     */
    public static function dispatchLater(int $delay, ...$arguments): void
    {
        $job = new static(...$arguments);

        $container = Container::getInstance();

        if ($container->bound('queue')) {
            $queue = $container->make('queue');

            $connection = $job->connection ?: null;
            $queueName = $job->queue ?: null;

            $conn = $queue->connection($connection);
            if ($queueName !== null && $queueName !== '') {
                $conn->laterOn($queueName, $delay, $job);
            } else {
                $conn->later($delay, $job);
            }
        } else {
            throw new \RuntimeException('Queue service not available. Please register QueueServiceProvider first.');
        }
    }

    /**
     * 同步执行任务（不放入队列）
     *
     * @param mixed ...$arguments
     * @return void
     */
    public static function dispatchNow(...$arguments): void
    {
        $job = new static(...$arguments);

        $container = Container::getInstance();

        if ($container->bound('queue')) {
            $queue = $container->make('queue');
            $queue->connection('sync')->push($job);
        } else {
            // 如果队列服务不可用，直接执行
            $dispatcher = new Dispatcher($container);
            $job->handle($dispatcher);
        }
    }

    /**
     * 获取队列中间件
     *
     * @return array
     */
    public function middleware(): array
    {
        return [];
    }
}
