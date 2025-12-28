<?php

declare(strict_types=1);

namespace Think\OpenApi\Middleware;

use Closure;
use Think\Response;

/**
 * 仅调试环境访问中间件
 *
 * 限制仅开发环境可访问 API 文档
 *
 * @package Think\OpenApi\Middleware
 */
class DebugOnlyMiddleware
{
    /**
     * 处理请求
     *
     * @param \Think\Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        // 检查是否在调试模式
        if (!$this->isDebugMode()) {
            return Response::notFound('Page not found');
        }

        return $next($request);
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

        // 默认拒绝访问
        return false;
    }
}
