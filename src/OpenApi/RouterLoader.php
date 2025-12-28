<?php

declare(strict_types=1);

namespace Think\OpenApi;

use Think\Container;
use Think\Routing\Router;
use InvalidArgumentException;

/**
 * 路由加载器
 *
 * 从容器或配置文件加载路由
 *
 * @package Think\OpenApi
 */
class RouterLoader
{
    /**
     * 已加载的 Router 实例
     *
     * @var Router|null
     */
    protected ?Router $router = null;

    /**
     * 加载路由
     *
     * @return Router
     * @throws InvalidArgumentException
     */
    public function load(): Router
    {
        if ($this->router !== null) {
            return $this->router;
        }

        $container = Container::getInstance();

        // 1. 尝试从容器获取
        if ($container->bound(Router::class)) {
            $this->router = $container->get(Router::class);
            return $this->router;
        }

        if ($container->bound('router')) {
            $this->router = $container->get('router');
            return $this->router;
        }

        // 2. 尝试从配置文件加载
        if (function_exists('C')) {
            $routesFile = C('OPENAPI_ROUTES_FILE');
            if ($routesFile && file_exists($routesFile)) {
                $router = require $routesFile;
                if ($router instanceof Router) {
                    $this->router = $router;
                    return $this->router;
                }
            }
        }

        throw new InvalidArgumentException(
            '无法获取 Router 实例。请确保：' .
            '1. Router 已绑定到容器 (Container::getInstance()->instance(Router::class, $router))，或 ' .
            '2. 配置 OPENAPI_ROUTES_FILE 指向返回 Router 的文件'
        );
    }

    /**
     * 设置 Router 实例
     *
     * @param Router $router
     * @return self
     */
    public function setRouter(Router $router): self
    {
        $this->router = $router;
        return $this;
    }

    /**
     * 获取编译后的路由
     *
     * @return array
     */
    public function getCompiledRoutes(): array
    {
        $router = $this->load();
        $compiled = $router->exportCompiled();

        return $compiled['routes'] ?? [];
    }
}
