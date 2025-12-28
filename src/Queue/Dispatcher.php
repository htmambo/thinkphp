<?php

declare(strict_types=1);

namespace Think\Queue;

use Think\Container;

/**
 * 队列任务分发器
 *
 * 负责执行队列任务
 *
 * @package Think\Queue
 */
class Dispatcher
{
    /**
     * 容器实例
     *
     * @var Container
     */
    protected Container $container;

    /**
     * 构造函数
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 执行队列任务
     *
     * @param \Think\Queue\Job $job
     * @return void
     */
    public function dispatch(Job $job): void
    {
        $job->handle($this);
    }

    /**
     * 从容器解析服务
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    public function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * 获取容器实例
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}

