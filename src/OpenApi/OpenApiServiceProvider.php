<?php

declare(strict_types=1);

namespace Think\OpenApi;

use Think\Support\ServiceProvider;
use Think\Routing\Router;

/**
 * OpenAPI 服务提供者
 *
 * 注册 API 文档相关服务和路由
 *
 * @package Think\OpenApi
 */
class OpenApiServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 仅在调试模式下注册
        if (!$this->isDebugMode()) {
            return;
        }

        // 注册 Router resolving 回调
        // 当 Router 被解析时，注册 docs 路由
        $this->app->resolving(Router::class, function (Router $router) {
            if ($this->isEnabled()) {
                DocsRoutes::register($router);
            }
        });

        // 也支持通过 'router' 别名解析
        $this->app->resolving('router', function ($router) {
            if ($router instanceof Router && $this->isEnabled()) {
                DocsRoutes::register($router);
            }
        });
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        // 仅在调试模式下启动
        if (!$this->isDebugMode()) {
            return;
        }

        // 如果 Router 已经实例化，直接注册路由
        if ($this->isEnabled() && $this->app->bound(Router::class)) {
            $router = $this->app->get(Router::class);
            if ($router instanceof Router) {
                DocsRoutes::register($router);
            }
        }
    }

    /**
     * 检查是否为调试模式
     *
     * @return bool
     */
    protected function isDebugMode(): bool
    {
        // 优先检查 APP_DEBUG 常量
        if (defined('APP_DEBUG')) {
            return APP_DEBUG === true;
        }

        // 其次检查配置
        if (function_exists('C')) {
            return C('APP_DEBUG') === true;
        }

        // 默认禁用
        return false;
    }

    /**
     * 检查是否启用 OpenAPI
     *
     * @return bool
     */
    protected function isEnabled(): bool
    {
        if (function_exists('C')) {
            return C('OPENAPI_ENABLED', true) === true;
        }

        return true;
    }
}
