<?php

declare(strict_types=1);

namespace Think\Routing;

use InvalidArgumentException;
use RuntimeException;

/**
 * 路由器
 *
 * 现代化的路由系统，支持命名路由、资源路由、路由组、RESTful 路由等功能。
 * 负责路由注册、编译、匹配和 URL 生成。
 *
 * @package Think\Routing
 */
final class Router
{
    /**
     * 路由定义列表
     *
     * @var array<int, RouteDefinition>
     */
    private array $routes = [];

    /**
     * 路由组栈
     *
     * @var array<int, array{prefix:string, middleware:array<int,string>, namespace:string, as:string}>
     */
    private array $groupStack = [];

    /**
     * 是否已编译
     */
    private bool $compiled = false;

    /**
     * 静态路由映射表
     *
     * method => [path => routeIndex]
     *
     * @var array<string, array<string, int>>
     */
    private array $staticMap = [];

    /**
     * 动态路由映射表
     *
     * method => [[regex => string, routeIndex => int, weight => int], ...]
     *
     * @var array<string, array<int, array{regex:string, routeIndex:int, weight:int}>>
     */
    private array $dynamicMap = [];

    /**
     * 命名路由映射表
     *
     * name => routeIndex
     *
     * @var array<string, int>
     */
    private array $nameMap = [];

    /**
     * 基础路径
     */
    private string $basePath = '';

    /**
     * 设置基础路径
     *
     * @param string $basePath 基础路径
     * @return $this 支持链式调用
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = rtrim($basePath, '/');
        return $this;
    }

    /**
     * 标记路由器为脏状态（需要重新编译）
     *
     * @return void
     */
    public function markDirty(): void
    {
        $this->compiled = false;
    }

    // ============================================
    // HTTP 方法快捷方式
    // ============================================

    /**
     * 添加 GET 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function get(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['GET'], $uri, $handler);
    }

    /**
     * 添加 POST 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function post(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['POST'], $uri, $handler);
    }

    /**
     * 添加 PUT 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function put(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['PUT'], $uri, $handler);
    }

    /**
     * 添加 PATCH 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function patch(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['PATCH'], $uri, $handler);
    }

    /**
     * 添加 DELETE 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function delete(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['DELETE'], $uri, $handler);
    }

    /**
     * 添加 OPTIONS 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function options(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['OPTIONS'], $uri, $handler);
    }

    /**
     * 添加 HEAD 路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function head(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['HEAD'], $uri, $handler);
    }

    /**
     * 添加多方法路由
     *
     * @param array<int, string> $methods HTTP 方法列表
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function match(array $methods, string $uri, mixed $handler): RouteDefinition
    {
        return $this->add($methods, $uri, $handler);
    }

    /**
     * 添加任意方法路由
     *
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function any(string $uri, mixed $handler): RouteDefinition
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], $uri, $handler);
    }

    // ============================================
    // 路由注册
    // ============================================

    /**
     * 添加路由
     *
     * @param array<int, string> $methods HTTP 方法列表
     * @param string $uri URI 模板
     * @param mixed $handler 路由处理器
     * @return RouteDefinition 路由定义对象
     */
    public function add(array $methods, string $uri, mixed $handler): RouteDefinition
    {
        // 获取当前路由组配置
        $group = $this->currentGroup();

        // 应用路由组前缀
        $uri = $this->joinPrefix($group['prefix'], $uri);

        // 应用路由组命名空间
        $handler = $this->applyNamespaceToHandler($group['namespace'], $handler);

        // 创建路由定义
        $route = new RouteDefinition(
            $this,
            $methods,
            $uri,
            $handler,
            $group['as'],
            $group['middleware'],
        );

        $this->routes[] = $route;
        $this->markDirty();

        return $route;
    }

    // ============================================
    // 路由组
    // ============================================

    /**
     * 定义路由组
     *
     * @param array{prefix?:string, middleware?:array<int,string>|string, namespace?:string, as?:string} $attributes 路由组属性
     * @param callable $callback 路由组定义回调
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $parent = $this->currentGroup();

        // 解析路由组属性
        $prefix = $attributes['prefix'] ?? '';
        $namespace = $attributes['namespace'] ?? '';
        $as = $attributes['as'] ?? '';

        $middleware = [];
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [(string)$attributes['middleware']];
        }

        // 合并父路由组和当前路由组属性
        $merged = [
            'prefix' => $this->joinPrefix($parent['prefix'], (string)$prefix),
            'namespace' => $this->joinNamespace($parent['namespace'], (string)$namespace),
            'as' => $this->joinNamePrefix($parent['as'], (string)$as),
            'middleware' => array_values(array_unique(array_merge(
                $parent['middleware'],
                $this->normalizeMiddleware($middleware),
            ))),
        ];

        // 压入路由组栈
        $this->groupStack[] = $merged;

        try {
            // 执行路由组回调
            $callback($this);
        } finally {
            // 弹出路由组栈
            array_pop($this->groupStack);
        }
    }

    // ============================================
    // 资源路由
    // ============================================

    /**
     * 定义资源路由
     *
     * 自动生成 CRUD 路由。
     *
     * @param string $name 资源名称
     * @param string $controller 控制器
     * @param array{only?:array<int,string>, except?:array<int,string>, names?:array<string,string>, parameters?:array<string,string>, middleware?:array<int,string>|string} $options 资源路由选项
     * @return void
     * @throws InvalidArgumentException 如果资源名称为空
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $name = trim($name, '/');
        if ($name === '') {
            throw new InvalidArgumentException('Resource name must not be empty');
        }

        // 获取资源参数名（默认只裁掉一个尾部 's'）
        $param = $options['parameters'][$name] ?? (str_ends_with($name, 's') ? substr($name, 0, -1) : $name);
        $param = $param !== '' ? $param : 'id';

        // 解析 only/except 选项
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;

        // 解析命名选项
        $names = $options['names'] ?? [];

        // 解析中间件选项
        $mw = $options['middleware'] ?? [];
        $mw = is_array($mw) ? $mw : [(string)$mw];

        // 定义资源路由（Laravel 风格 7 路由）
        $actions = [
            'index' => ['GET', '/' . $name, $controller . '@index'],
            'create' => ['GET', '/' . $name . '/create', $controller . '@create'],
            'store' => ['POST', '/' . $name, $controller . '@store'],
            'show' => ['GET', '/' . $name . '/{' . $param . '}', $controller . '@show'],
            'edit' => ['GET', '/' . $name . '/{' . $param . '}/edit', $controller . '@edit'],
            'update' => [['PUT', 'PATCH'], '/' . $name . '/{' . $param . '}', $controller . '@update'],
            'destroy' => ['DELETE', '/' . $name . '/{' . $param . '}', $controller . '@destroy'],
        ];

        foreach ($actions as $action => [$methods, $uri, $handler]) {
            // 应用 only/except 过滤
            if (is_array($only) && in_array($action, $only, true) === false) {
                continue;
            }
            if (is_array($except) && in_array($action, $except, true) === true) {
                continue;
            }

            // 处理多方法路由
            $methods = is_array($methods) ? $methods : [$methods];

            // 添加路由并设置中间件
            $route = $this->add($methods, $uri, $handler)->middlewareAdd($mw);

            // 设置路由名称
            $defaultName = $name . '.' . $action;
            $route->nameAs($names[$action] ?? $defaultName);
        }
    }

    // ============================================
    // 路由匹配
    // ============================================

    /**
     * 匹配路径
     *
     * @param string $method HTTP 方法
     * @param string $path 请求路径
     * @return RouteMatch|null 匹配结果，如果未匹配则返回 null
     */
    public function matchPath(string $method, string $path): ?RouteMatch
    {
        // 确保路由已编译
        $this->compile();

        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        // HEAD 请求回退到 GET
        $methodsToTry = [$method];
        if ($method === 'HEAD') {
            $methodsToTry[] = 'GET';
        }

        foreach ($methodsToTry as $m) {
            // 优先匹配静态路由
            if (isset($this->staticMap[$m][$path])) {
                $idx = $this->staticMap[$m][$path];
                return new RouteMatch($this->routes[$idx], $m, $path, []);
            }

            // 匹配动态路由
            if (!isset($this->dynamicMap[$m])) {
                continue;
            }

            foreach ($this->dynamicMap[$m] as $row) {
                $regex = $row['regex'];
                if (preg_match($regex, $path, $matches) !== 1) {
                    continue;
                }

                // 提取命名参数（过滤空字符串，用于可选参数）
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k) && $v !== '') {
                        $params[$k] = (string)$v;
                    }
                }

                return new RouteMatch($this->routes[$row['routeIndex']], $m, $path, $params);
            }
        }

        return null;
    }

    /**
     * 匹配请求对象
     *
     * 支持 Think\Request 和 PSR-7 风格的请求对象。
     *
     * @param object $request 请求对象
     * @return RouteMatch|null 匹配结果，如果未匹配则返回 null
     * @throws InvalidArgumentException 如果请求对象不支持
     */
    public function matchRequest(object $request): ?RouteMatch
    {
        // Think\Request: method()/path()
        if (method_exists($request, 'method') && method_exists($request, 'path')) {
            $method = (string)$request->method();
            $path = (string)$request->path();
            return $this->matchPath($method, $path);
        }

        // PSR-7 风格：getMethod()/getUri()->getPath()
        if (method_exists($request, 'getMethod') && method_exists($request, 'getUri')) {
            $method = (string)$request->getMethod();
            $uri = $request->getUri();
            if (is_object($uri) && method_exists($uri, 'getPath')) {
                $path = (string)$uri->getPath();
                return $this->matchPath($method, $path);
            }
        }

        throw new InvalidArgumentException('Unsupported request object; cannot extract method/path');
    }

    // ============================================
    // URL 生成
    // ============================================

    /**
     * 根据路由名称生成 URL
     *
     * @param string $name 路由名称
     * @param array<string, string|int> $params 路由参数
     * @param array<string, string|int|bool> $query 查询参数
     * @return string 生成的 URL
     * @throws RuntimeException 如果路由名称不存在
     */
    public function url(string $name, array $params = [], array $query = []): string
    {
        // 确保路由已编译
        $this->compile();

        if (!isset($this->nameMap[$name])) {
            throw new RuntimeException('Route name not found: ' . $name);
        }

        $route = $this->routes[$this->nameMap[$name]];
        $path = $route->buildPath($params, $query);

        // 应用基础路径
        if ($this->basePath !== '') {
            return $this->basePath . $path;
        }

        return $path;
    }

    // ============================================
    // 路由缓存
    // ============================================

    /**
     * 导出编译后的路由（用于缓存）
     *
     * @return array<string, mixed> 可序列化的路由数据
     */
    public function exportCompiled(): array
    {
        $this->compile();

        // 导出所有路由定义
        $exportRoutes = [];
        foreach ($this->routes as $r) {
            $exportRoutes[] = $r->exportCompiled();
        }

        return [
            'version' => 1,
            'routes' => $exportRoutes,
            'staticMap' => $this->staticMap,
            'dynamicMap' => $this->dynamicMap,
            'nameMap' => $this->nameMap,
        ];
    }

    /**
     * 从缓存数据恢复路由器
     *
     * @param array<string, mixed> $data 缓存数据
     * @return self 路由器实例
     * @throws InvalidArgumentException 如果数据格式无效
     */
    public static function fromCompiled(array $data): self
    {
        if (($data['version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported compiled routes version');
        }

        $router = new self();

        $routes = $data['routes'] ?? null;
        if (!is_array($routes)) {
            throw new InvalidArgumentException('Invalid compiled routes payload');
        }

        // 重建路由定义对象
        foreach ($routes as $r) {
            if (!is_array($r)) {
                throw new InvalidArgumentException('Invalid route entry');
            }

            $route = new RouteDefinition(
                $router,
                $r['methods'] ?? [],
                (string)($r['uriTemplate'] ?? '/'),
                $r['handler'] ?? null,
                '', // namePrefix 已在 name 字段固化
                (array)($r['middleware'] ?? []),
            );

            // 恢复路由名称
            if (isset($r['name']) && is_string($r['name']) && $r['name'] !== '') {
                $route->nameAs($r['name']);
            }

            // 恢复参数约束
            if (isset($r['where']) && is_array($r['where'])) {
                $route->where($r['where']);
            }

            // 恢复参数默认值
            if (isset($r['defaults']) && is_array($r['defaults'])) {
                $route->defaults($r['defaults']);
            }

            $router->routes[] = $route;
        }

        // 直接恢复编译索引（跳过 compile）
        $router->staticMap = is_array($data['staticMap'] ?? null) ? $data['staticMap'] : [];
        $router->dynamicMap = is_array($data['dynamicMap'] ?? null) ? $data['dynamicMap'] : [];
        $router->nameMap = is_array($data['nameMap'] ?? null) ? $data['nameMap'] : [];

        // 校验索引范围
        $routeCount = count($router->routes);
        foreach ($router->nameMap as $idx) {
            if (!is_int($idx) || $idx < 0 || $idx >= $routeCount) {
                throw new InvalidArgumentException('Invalid route index in nameMap: ' . $idx);
            }
        }

        foreach ($router->staticMap as $methodMap) {
            foreach ($methodMap as $idx) {
                if (!is_int($idx) || $idx < 0 || $idx >= $routeCount) {
                    throw new InvalidArgumentException('Invalid route index in staticMap: ' . $idx);
                }
            }
        }

        foreach ($router->dynamicMap as $method => $list) {
            if (!is_array($list)) {
                throw new InvalidArgumentException('Invalid dynamicMap for method: ' . $method);
            }
            foreach ($list as $row) {
                if (!is_array($row) || !isset($row['routeIndex'], $row['regex'])) {
                    throw new InvalidArgumentException('Invalid dynamicMap entry for method: ' . $method);
                }
                $idx = $row['routeIndex'];
                if (!is_int($idx) || $idx < 0 || $idx >= $routeCount) {
                    throw new InvalidArgumentException('Invalid route index in dynamicMap: ' . $idx);
                }
            }
        }

        $router->compiled = true;

        return $router;
    }

    // ============================================
    // 内部方法
    // ============================================

    /**
     * 编译路由
     *
     * @return void
     */
    private function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        $this->staticMap = [];
        $this->dynamicMap = [];
        $this->nameMap = [];

        foreach ($this->routes as $idx => $route) {
            $route->compile();

            // 注册命名路由
            $name = $route->name();
            if ($name !== null) {
                if (isset($this->nameMap[$name])) {
                    throw new RuntimeException('Duplicate route name: ' . $name);
                }
                $this->nameMap[$name] = $idx;
            }

            // 构建路由映射表
            foreach ($route->methods() as $method) {
                if ($route->isStatic()) {
                    // 静态路由
                    $path = $route->staticPath();
                    $this->staticMap[$method][$path] = $idx;
                    continue;
                }

                // 动态路由
                $regex = $route->compiledRegex();
                if ($regex === null) {
                    continue;
                }

                // 计算权重（用于排序）
                $weight = $this->computeWeight($route->uriTemplate());

                $this->dynamicMap[$method][] = [
                    'regex' => $regex,
                    'routeIndex' => $idx,
                    'weight' => $weight,
                ];
            }
        }

        // 按权重排序动态路由（更具体的路由优先）
        foreach ($this->dynamicMap as $method => $list) {
            usort($list, static function (array $a, array $b): int {
                return $b['weight'] <=> $a['weight'];
            });
            $this->dynamicMap[$method] = $list;
        }

        $this->compiled = true;
    }

    /**
     * 计算路由权重（用于排序）
     *
     * 字面段越多、长度越长的路由权重越高（更具体）。
     *
     * @param string $uriTemplate URI 模板
     * @return int 权重值
     */
    private function computeWeight(string $uriTemplate): int
    {
        $segments = array_values(array_filter(explode('/', trim($uriTemplate, '/')), static fn($s) => $s !== ''));
        $literal = 0;

        foreach ($segments as $s) {
            // 非参数段（字面量）增加权重
            if (str_starts_with($s, '{') === false) {
                $literal++;
            }
        }

        // 权重 = 字面段数 * 1000 + 长度
        return ($literal * 1000) + strlen($uriTemplate);
    }

    /**
     * 获取当前路由组配置
     *
     * @return array{prefix:string, middleware:array<int,string>, namespace:string, as:string} 路由组配置
     */
    private function currentGroup(): array
    {
        if ($this->groupStack === []) {
            return ['prefix' => '', 'middleware' => [], 'namespace' => '', 'as' => ''];
        }
        return $this->groupStack[count($this->groupStack) - 1];
    }

    /**
     * 拼接路径前缀
     *
     * @param string $parent 父前缀
     * @param string $child 子前缀
     * @return string 拼接后的前缀
     */
    private function joinPrefix(string $parent, string $child): string
    {
        $parent = trim($parent);
        $child = trim($child);

        if ($parent === '') {
            return $child;
        }
        if ($child === '') {
            return $parent;
        }

        return rtrim($parent, '/') . '/' . ltrim($child, '/');
    }

    /**
     * 拼接命名空间
     *
     * @param string $parent 父命名空间
     * @param string $child 子命名空间
     * @return string 拼接后的命名空间
     */
    private function joinNamespace(string $parent, string $child): string
    {
        $parent = trim($parent, '\\');
        $child = trim($child, '\\');

        if ($parent === '') {
            return $child;
        }
        if ($child === '') {
            return $parent;
        }

        return $parent . '\\' . $child;
    }

    /**
     * 拼接名称前缀
     *
     * @param string $parent 父前缀
     * @param string $child 子前缀
     * @return string 拼接后的前缀
     */
    private function joinNamePrefix(string $parent, string $child): string
    {
        $parent = trim($parent);
        $child = trim($child);

        if ($parent === '') {
            return $child;
        }
        if ($child === '') {
            return $parent;
        }

        return rtrim($parent, '.') . '.' . ltrim($child, '.');
    }

    /**
     * 应用命名空间到处理器
     *
     * @param string $namespace 命名空间
     * @param mixed $handler 处理器
     * @return mixed 应用命名空间后的处理器
     */
    private function applyNamespaceToHandler(string $namespace, mixed $handler): mixed
    {
        $namespace = trim($namespace, '\\');
        if ($namespace === '') {
            return $handler;
        }

        if (!is_string($handler)) {
            return $handler;
        }

        // 处理 "Controller@method" 格式
        if (str_contains($handler, '@')) {
            [$controller, $method] = explode('@', $handler, 2);
            $controller = ltrim($controller, '\\');
            if (!str_contains($controller, '\\')) {
                $controller = $namespace . '\\' . $controller;
            }
            return $controller . '@' . $method;
        }

        // 处理 "Controller::method" 格式
        if (str_contains($handler, '::')) {
            [$controller, $method] = explode('::', $handler, 2);
            $controller = ltrim($controller, '\\');
            if (!str_contains($controller, '\\')) {
                $controller = $namespace . '\\' . $controller;
            }
            return $controller . '::' . $method;
        }

        return $handler;
    }

    /**
     * 标准化中间件列表
     *
     * @param array<int, string> $middleware 中间件列表
     * @return array<int, string> 标准化后的中间件列表
     */
    private function normalizeMiddleware(array $middleware): array
    {
        $out = [];
        foreach ($middleware as $m) {
            $m = trim((string)$m);
            if ($m !== '') {
                $out[] = $m;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * 标准化路径
     *
     * @param string $path 原始路径
     * @return string 标准化后的路径
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        // 去除查询字符串
        $parsed = parse_url($path);
        if (is_array($parsed) && isset($parsed['path'])) {
            $path = (string)$parsed['path'];
        }

        if ($path === '') {
            $path = '/';
        }

        // 确保以 / 开头
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        // 规范化重复斜杠
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        // 去除尾部斜杠（根路径除外）
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
