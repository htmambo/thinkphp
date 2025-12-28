<?php

declare(strict_types=1);

namespace Think\Support;

use Think\Container;

/**
 * 服务提供者管理器
 *
 * 负责加载、注册和启动服务提供者。
 *
 * 功能：
 * - 按配置加载服务提供者
 * - 调用 register() 注册服务
 * - 调用 boot() 启动服务
 * - 确保注册在启动之前完成
 */
final class ServiceProviderManager
{
    /**
     * 容器实例
     * @var Container
     */
    private Container $app;

    /**
     * 已注册的服务提供者
     * @var array<int, ServiceProvider>
     */
    private array $providers = [];

    /**
     * 已启动的标记
     * @var bool
     */
    private bool $booted = false;

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
     * 注册服务提供者
     *
     * @param array<int, string> $providers 服务提供者类名数组
     * @return void
     * @throws \InvalidArgumentException 如果提供者类无效
     */
    public function register(array $providers): void
    {
        foreach ($providers as $providerClass) {
            $providerClass = (string)$providerClass;

            // 跳过空字符串
            if ($providerClass === '') {
                continue;
            }

            // 验证提供者类
            if (!class_exists($providerClass)) {
                throw new \InvalidArgumentException('Service provider class does not exist: ' . $providerClass);
            }

            if (!is_subclass_of($providerClass, ServiceProvider::class)) {
                throw new \InvalidArgumentException(
                    'Service provider must extend ' . ServiceProvider::class . ': ' . $providerClass
                );
            }

            // 实例化并注册
            $provider = new $providerClass($this->app);
            $provider->register();

            $this->providers[] = $provider;
        }
    }

    /**
     * 启动所有已注册的服务提供者
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * 判断是否已启动
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * 获取所有已注册的服务提供者
     *
     * @return array<int, ServiceProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
