<?php

declare(strict_types=1);

namespace Think\OpenApi;

use Think\Routing\Router;
use Think\OpenApi\Controller\DocsController;
use Think\OpenApi\Middleware\DebugOnlyMiddleware;

/**
 * Docs 路由注册
 *
 * @package Think\OpenApi
 */
class DocsRoutes
{
    /**
     * 注册文档相关路由
     *
     * @param Router $router
     * @return void
     */
    public static function register(Router $router): void
    {
        // 获取配置路径
        $docsPath = self::getDocsPath();
        $specPath = self::getSpecPath();

        // 注册 /docs 路由（Swagger UI）
        $router->get($docsPath, DocsController::class . '@index')
            ->middlewareAdd(DebugOnlyMiddleware::class);

        // 注册 /openapi.json 路由（OpenAPI Spec）
        $router->get($specPath, DocsController::class . '@specJson')
            ->middlewareAdd(DebugOnlyMiddleware::class);
    }

    /**
     * 获取 /docs 路径
     *
     * @return string
     */
    protected static function getDocsPath(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_DOCS_PATH');
            if ($path) {
                return $path;
            }
        }

        return '/docs';
    }

    /**
     * 获取 /openapi.json 路径
     *
     * @return string
     */
    protected static function getSpecPath(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_SPEC_PATH');
            if ($path) {
                return $path;
            }
        }

        return '/openapi.json';
    }
}
