<?php

declare(strict_types=1);

namespace Think\Support;

use Think\Container;

/**
 * 服务提供者抽象基类
 *
 * 用于组织和延迟加载应用服务。
 *
 * 使用方式：
 * 1. 继承此类
 * 2. 在 register() 方法中注册服务到容器
 * 3. 在 boot() 方法中执行依赖已就绪后的启动逻辑
 *
 * 示例：
 * ```php
 * class DatabaseServiceProvider extends ServiceProvider
 * {
 *     public function register(): void
 *     {
 *         $this->app->singleton('db', function ($app) {
 *             return new DatabaseManager($app);
 *         });
 *     }
 *
 *     public function boot(): void
 *     {
 *         // 所有服务已注册后执行
 *     }
 * }
 * ```
 */
abstract class ServiceProvider
{
    /**
     * 容器实例
     * @var Container
     */
    protected Container $app;

    /**
     * 构造函数
     *
     * @param Container $app 容器实例
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * 注册服务到容器
     *
     * 在此方法中绑定服务到容器，但不应执行依赖其他服务的逻辑。
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * 启动服务
     *
     * 在所有服务的 register() 方法执行完毕后调用。
     * 可以在此方法中执行依赖其他服务的逻辑。
     *
     * @return void
     */
    public function boot(): void
    {
        // 默认空实现，子类可选择性覆盖
    }

    /**
     * 获取服务路径
     *
     * 用于延迟加载服务提供者。
     *
     * @return string
     */
    public static function deferredPath(): string
    {
        // 默认返回当前类路径，子类可覆盖
        return static::class;
    }
}
