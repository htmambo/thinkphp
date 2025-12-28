<?php

declare(strict_types=1);

namespace Think\Routing;

use InvalidArgumentException;

/**
 * 路由定义
 *
 * 表示一条路由的定义，包含 HTTP 方法、URI 模板、处理器和元数据。
 * 负责 URI 模板解析、参数约束管理、编译匹配 regex 和 URL 生成。
 *
 * @package Think\Routing
 */
final class RouteDefinition
{
    /**
     * HTTP 方法列表
     *
     * @var array<int, string>
     */
    private array $methods;

    /**
     * URI 模板（如 /users/{id}）
     */
    private string $uriTemplate;

    /**
     * 路由处理器
     *
     * @var mixed
     */
    private mixed $handler;

    /**
     * 路由名称
     */
    private ?string $name = null;

    /**
     * 中间件列表
     *
     * @var array<int, string>
     */
    private array $middleware = [];

    /**
     * 参数约束（正则表达式）
     *
     * @var array<string, string>
     */
    private array $where = [];

    /**
     * 参数默认值
     *
     * @var array<string, string>
     */
    private array $defaults = [];

    /**
     * 编译后的正则表达式
     */
    private ?string $compiledRegex = null;

    /**
     * 编��后的参数名列表
     *
     * @var array<int, string>
     */
    private array $compiledParamNames = [];

    /**
     * 是否为静态路由
     */
    private bool $isStatic = false;

    /**
     * 静态路由路径
     */
    private string $staticPath = '';

    /**
     * 路由名称前缀
     */
    private string $namePrefix = '';

    /**
     * 构造函数
     *
     * @param Router $router 路由器实例
     * @param array<int, string> $methods HTTP 方法列表
     * @param string $uriTemplate URI 模板
     * @param mixed $handler 路由处理器
     * @param string $namePrefix 路由名称前缀
     * @param array<int, string> $middleware 中间件列表
     * @throws InvalidArgumentException 如果 HTTP 方法列表为空
     */
    public function __construct(
        private readonly Router $router,
        array $methods,
        string $uriTemplate,
        mixed $handler,
        string $namePrefix = '',
        array $middleware = [],
    ) {
        // 标准化并验证 HTTP 方法
        $methods = array_values(array_unique(array_map(
            static fn(string $m): string => strtoupper($m),
            $methods
        )));

        if ($methods === []) {
            throw new InvalidArgumentException('Route methods must not be empty');
        }

        $this->methods = $methods;
        $this->uriTemplate = self::normalizePath($uriTemplate);
        $this->handler = $handler;
        $this->namePrefix = $namePrefix;
        $this->middleware = self::normalizeMiddleware($middleware);

        // 标记路由器为脏状态
        $this->router->markDirty();
    }

    /**
     * 获取 HTTP 方法列表
     *
     * @return array<int, string> HTTP 方法列表
     */
    public function methods(): array
    {
        return $this->methods;
    }

    /**
     * 获取 URI 模板
     *
     * @return string URI 模板
     */
    public function uriTemplate(): string
    {
        return $this->uriTemplate;
    }

    /**
     * 获取路由处理器
     *
     * @return mixed 路由处理器
     */
    public function handler(): mixed
    {
        return $this->handler;
    }

    /**
     * 获取路由名称
     *
     * @return string|null 路由名称，如果未设置则返回 null
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * 获取中间件列表
     *
     * @return array<int, string> 中间件列表
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * 设置路由名称
     *
     * @param string $name 路由名称
     * @return $this 支持链式调用
     * @throws InvalidArgumentException 如果路由名称为空
     */
    public function nameAs(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Route name must not be empty');
        }

        // 应用名称前缀
        $this->name = $this->namePrefix !== ''
            ? rtrim($this->namePrefix, '.') . '.' . ltrim($name, '.')
            : $name;

        $this->router->markDirty();
        return $this;
    }

    /**
     * 添加中间件
     *
     * @param string|array<int, string> $middleware 中间件类名或类名数组
     * @return $this 支持链式调用
     */
    public function middlewareAdd(string|array $middleware): self
    {
        $more = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_values(array_unique(array_merge(
            $this->middleware,
            self::normalizeMiddleware($more),
        )));

        $this->router->markDirty();
        return $this;
    }

    /**
     * 设置参数约束
     *
     * @param array<string, string>|string $name 参数名或参数约束数组
     * @param string|null $pattern 正则表达式（当 $name 为字符串时）
     * @return $this 支持链式调用
     * @throws InvalidArgumentException 如果参数或模式为空
     */
    public function where(array|string $name, ?string $pattern = null): self
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->where((string)$k, (string)$v);
            }
            return $this;
        }

        $key = trim($name);
        if ($key === '' || $pattern === null || $pattern === '') {
            throw new InvalidArgumentException('where(name, pattern) requires non-empty values');
        }

        $this->where[$key] = $pattern;
        $this->invalidateCompiled();
        $this->router->markDirty();
        return $this;
    }

    /**
     * 设置参数默认值
     *
     * @param array<string, string> $defaults 默认值数组
     * @return $this 支持链式调用
     */
    public function defaults(array $defaults): self
    {
        foreach ($defaults as $k => $v) {
            $this->defaults[(string)$k] = (string)$v;
        }
        $this->invalidateCompiled();
        $this->router->markDirty();
        return $this;
    }

    /**
     * 使编译缓存失效
     *
     * 当路由定义发生变化时（如约束、默认值），需要清除编译缓存。
     *
     * @return void
     */
    private function invalidateCompiled(): void
    {
        $this->compiledRegex = null;
        $this->compiledParamNames = [];
        $this->isStatic = false;
        $this->staticPath = '';
    }

    /**
     * 编译路由（生成正则表达式和静态路径）
     *
     * @return void
     */
    public function compile(): void
    {
        // 如果已经编译过，直接返回
        if ($this->compiledRegex !== null || $this->isStatic) {
            return;
        }

        // 检查是否为静态路由（不包含参数）
        if (str_contains($this->uriTemplate, '{') === false) {
            $this->isStatic = true;
            $this->staticPath = $this->uriTemplate;
            return;
        }

        // 解析 URI 模板并生成正则表达式
        $template = trim($this->uriTemplate, '/');
        $segments = $template === '' ? [] : explode('/', $template);

        $regex = '#^';
        $paramNames = [];

        foreach ($segments as $index => $segment) {
            // 匹配 {param} 或 {param?} 或 {param:pattern} 或 {param?:pattern}
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(\?)?(?::([^}]+))?\}$/', $segment, $m) === 1) {
                $name = $m[1];
                $optional = isset($m[2]) && $m[2] === '?';
                $inlinePattern = isset($m[3]) ? $m[3] : null;

                // 检测重复参数名
                if (in_array($name, $paramNames, true)) {
                    throw new InvalidArgumentException('Duplicate route parameter name: ' . $name);
                }

                // 获取参数约束模式
                $pattern = $this->where[$name] ?? $inlinePattern ?? '[^/]+';

                $paramNames[] = $name;

                if ($optional) {
                    // 仅支持尾段可选（简化实现）
                    if ($index !== count($segments) - 1) {
                        throw new InvalidArgumentException('Optional parameters are only supported at the end of the URI template');
                    }
                    $regex .= '(?:/(?P<' . $name . '>' . $pattern . '))?';
                } else {
                    $regex .= '/(?P<' . $name . '>' . $pattern . ')';
                }

                continue;
            }

            // 静态段：字面量
            $regex .= '/' . preg_quote($segment, '#');
        }

        if ($segments === []) {
            $regex .= '/';
        }

        $regex .= '$#';

        $this->compiledRegex = $regex;
        $this->compiledParamNames = $paramNames;
    }

    /**
     * 判断是否为静态路由
     *
     * @return bool 如果为静态路由返回 true
     */
    public function isStatic(): bool
    {
        $this->compile();
        return $this->isStatic;
    }

    /**
     * 获取静态路径
     *
     * @return string 静态路径
     */
    public function staticPath(): string
    {
        $this->compile();
        return $this->staticPath;
    }

    /**
     * 获取编译后的正则表达式
     *
     * @return string|null 正则表达式，如果为静态路由则返回 null
     */
    public function compiledRegex(): ?string
    {
        $this->compile();
        return $this->compiledRegex;
    }

    /**
     * 获取编译后的参数名列表
     *
     * @return array<int, string> 参数名列表
     */
    public function compiledParamNames(): array
    {
        $this->compile();
        return $this->compiledParamNames;
    }

    /**
     * 根据参数构建 URL 路径
     *
     * @param array<string, string|int> $params 路由参数
     * @param array<string, string|int|bool> $query 查询参数
     * @return string 构建的 URL 路径
     * @throws InvalidArgumentException 如果缺少必需参数
     */
    public function buildPath(array $params = [], array $query = []): string
    {
        $path = $this->uriTemplate;

        // 替换路由参数
        $path = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)(\?)?(?::[^}]+)?\}/',
            function (array $m) use ($params): string {
                $name = $m[1];
                $optional = isset($m[2]) && $m[2] === '?';

                // 获取参数值
                $value = $params[$name] ?? $this->defaults[$name] ?? null;

                // 处理可选参数
                if ($value === null || $value === '') {
                    if ($optional) {
                        return '';
                    }
                    throw new InvalidArgumentException('Missing required route parameter: ' . $name);
                }

                return rawurlencode((string)$value);
            },
            $path
        );

        $path = self::normalizePath($path);

        // 添加查询字符串（使用 RFC3986 编码）
        if ($query !== []) {
            $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }

        return $path;
    }

    /**
     * 导出为可缓存的数据结构
     *
     * @return array<string, mixed> 可序列化的路由数据
     * @throws InvalidArgumentException 如果处理器是闭包
     */
    public function exportCompiled(): array
    {
        $this->compile();

        // 闭包无法序列化
        if ($this->handler instanceof \Closure) {
            throw new InvalidArgumentException('Cannot export route with Closure handler for caching');
        }

        return [
            'methods' => $this->methods,
            'uriTemplate' => $this->uriTemplate,
            'handler' => $this->handler,
            'name' => $this->name,
            'middleware' => $this->middleware,
            'where' => $this->where,
            'defaults' => $this->defaults,
            'compiledRegex' => $this->compiledRegex,
            'compiledParamNames' => $this->compiledParamNames,
            'isStatic' => $this->isStatic,
            'staticPath' => $this->staticPath,
        ];
    }

    /**
     * 标准化路径
     *
     * @param string $path 原始路径
     * @return string 标准化后的路径
     */
    private static function normalizePath(string $path): string
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

    /**
     * 标准化中间件列表
     *
     * @param array<int, string> $middleware 中间件列表
     * @return array<int, string> 标准化后的中间件列表
     */
    private static function normalizeMiddleware(array $middleware): array
    {
        $result = [];
        foreach ($middleware as $m) {
            $m = trim((string)$m);
            if ($m !== '') {
                $result[] = $m;
            }
        }
        return array_values(array_unique($result));
    }
}
