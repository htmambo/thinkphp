<?php

declare(strict_types=1);

namespace Think\Routing;

/**
 * 路由匹配结果
 *
 * 表示一次路由匹配的结果，包含匹配到的路由定义、请求方法、路径和参数。
 * 这是一个不可变的 DTO (Data Transfer Object) 对象。
 *
 * @package Think\Routing
 */
final class RouteMatch
{
    /**
     * 构造函数
     *
     * @param RouteDefinition $route 匹配到的路由定义
     * @param string $method HTTP 请求方法
     * @param string $path 请求路径
     * @param array<string, string> $parameters 路由参数
     */
    public function __construct(
        private readonly RouteDefinition $route,
        private readonly string $method,
        private readonly string $path,
        private readonly array $parameters = [],
    ) {
    }

    /**
     * 获取路由定义
     *
     * @return RouteDefinition 路由定义对象
     */
    public function route(): RouteDefinition
    {
        return $this->route;
    }

    /**
     * 获取 HTTP 请求方法
     *
     * @return string 请求方法（GET、POST 等）
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * 获取请求路径
     *
     * @return string 请求路径
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * 获取所有路由参数
     *
     * @return array<string, string> 路由参数数组
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * 获取单个路由参数
     *
     * @param string $key 参数名
     * @param string|null $default 默认值
     * @return string|null 参数值
     */
    public function parameter(string $key, ?string $default = null): ?string
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * 获取路由名称
     *
     * @return string|null 路由名称，如果未设置则返回 null
     */
    public function name(): ?string
    {
        return $this->route->name();
    }

    /**
     * 获取中间件列表
     *
     * @return array<int, string> 中间件类名数组
     */
    public function middleware(): array
    {
        return $this->route->middleware();
    }

    /**
     * 获取路由处理器
     *
     * @return mixed 路由处理器（可以是闭包、控制器字符串等）
     */
    public function handler(): mixed
    {
        return $this->route->handler();
    }
}
