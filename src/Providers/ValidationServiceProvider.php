<?php

declare(strict_types=1);

namespace Think\Providers;

use Think\Support\ServiceProvider;
use Think\Validation\Validator;
use Think\Validation\ValidatorInterface;

/**
 * 验证服务提供者
 *
 * 注册验证器到容器
 */
class ValidationServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 绑定接口到实现
        $this->app->bind(ValidatorInterface::class, Validator::class);

        // 注册单例别名
        $this->app->singleton('validator', function ($app) {
            return new Validator();
        });

        // 同样绑定到 validator.interface
        $this->app->bind('validator.interface', ValidatorInterface::class);
    }
}
