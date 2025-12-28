<?php

declare(strict_types=1);

namespace Think;

/**
 * HTTP 请求抽象层
 *
 * 封装超全局变量（$_SERVER, $_GET, $_POST 等），
 * 提供面向对象的请求访问接口。
 *
 * 设计理念：
 * - 不可变对象（所有修改返回新实例）
 * - 类型安全（使用 declare(strict_types=1)）
 * - 向后兼容（保留对超全局变量的间接访问）
 */
final class Request
{
    /**
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * @var array<string, mixed>
     */
    private array $query;

    /**
     * @var array<string, mixed>
     */
    private array $request;

    /**
     * @var array<string, mixed>
     */
    private array $cookies;

    /**
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @var string|null
     */
    private ?string $rawBody;

    /**
     * 构造函数
     *
     * @param array<string, mixed> $server $_SERVER 数据
     * @param array<string, mixed> $query $_GET 数据
     * @param array<string, mixed> $request $_POST 数据
     * @param array<string, mixed> $cookies $_COOKIE 数据
     * @param array<string, mixed> $files $_FILES 数据
     * @param string|null $rawBody 原始请求体（PUT/DELETE 等）
     * @param array<string, string> $headers HTTP 头部
     */
    public function __construct(
        array $server,
        array $query = [],
        array $request = [],
        array $cookies = [],
        array $files = [],
        ?string $rawBody = null,
        array $headers = []
    ) {
        $this->server = $server;
        $this->query = $query;
        $this->request = $request;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->rawBody = $rawBody;
        $this->headers = $headers ?: $this->extractHeaders($server);
    }

    /**
     * 从全局变量创建 Request 实例
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $rawBody = null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // 对于非 GET/POST 请求，读取 php://input
        if (!in_array(strtoupper((string)$method), ['GET', 'POST'], true)) {
            $rawBody = file_get_contents('php://input') ?: null;
        }

        return new self(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $rawBody
        );
    }

    /**
     * 获取请求方法
     *
     * @return string GET, POST, PUT, DELETE 等
     */
    public function method(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * 获取完整 URI
     *
     * @return string
     */
    public function uri(): string
    {
        return (string)($this->server['REQUEST_URI'] ?? '/');
    }

    /**
     * 获取路径部分（不含查询字符串）
     *
     * @return string
     */
    public function path(): string
    {
        $uri = $this->uri();
        $pos = strpos($uri, '?');
        $path = $pos === false ? $uri : substr($uri, 0, $pos);
        $path = $path === '' ? '/' : $path;
        return $path;
    }

    /**
     * 获取查询参数（$_GET）
     *
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * 获取所有查询参数
     *
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * 获取请求参数（优先 POST，其次 GET）
     *
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->request)) {
            return $this->request[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    /**
     * 获取所有请求参数（GET + POST）
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * 获取 POST 参数
     *
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }

    /**
     * 获取所有 POST 参数
     *
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        return $this->request;
    }

    /**
     * 获取 Cookie 值
     *
     * @param string $key Cookie 名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * 获取上传的文件
     *
     * @param string $key 文件字段名
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * 获取 HTTP 头
     *
     * @param string $name 头名称（不区分大小写）
     * @param mixed $default 默认值
     * @return mixed
     */
    public function header(string $name, mixed $default = null): mixed
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    /**
     * 获取所有 HTTP 头
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 判断是否为 AJAX 请求
     *
     * 检测方式：
     * 1. X-Requested-With: XMLHttpRequest
     * 2. Accept: application/json
     * 3. 配置的 AJAX 提交参数（VAR_AJAX_SUBMIT）
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        // 检查 X-Requested-With 头
        $requestedWith = strtolower((string)$this->header('X-Requested-With', ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        // 检查 Accept 头
        $accept = strtolower((string)$this->header('Accept', ''));
        if ($accept !== '' && str_contains($accept, 'application/json')) {
            return true;
        }

        // 检查配置的 AJAX 参数
        if (function_exists('C')) {
            $ajaxParam = C('VAR_AJAX_SUBMIT');
            if ($ajaxParam) {
                if (!empty($this->request[$ajaxParam]) || !empty($this->query[$ajaxParam])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 判断是否为 HTTPS 请求
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return !empty($https) && strtolower((string)$https) !== 'off';
    }

    /**
     * 获取客户端 IP 地址
     *
     * @return string
     */
    public function ip(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            $ip = $this->server[$key] ?? '';
            if ($ip !== '') {
                // 处理多个 IP 的情况（X-Forwarded-For）
                $ips = explode(',', (string)$ip);
                $ip = trim($ips[0]);

                // 验证 IP 格式
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * 获取 User-Agent
     *
     * @return string
     */
    public function userAgent(): string
    {
        return (string)($this->server['HTTP_USER_AGENT'] ?? '');
    }

    /**
     * 获取原始请求体
     *
     * @return string|null
     */
    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    /**
     * 从 $_SERVER 提取 HTTP 头
     *
     * @param array<string, mixed> $server $_SERVER 数据
     * @return array<string, string>
     */
    private function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            // HTTP_* 字段转为 HTTP 头
            if (str_starts_with((string)$key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string)$key, 5)));
                $headers[$name] = (string)$value;
            }
        }

        // Content-Type 和 Content-Length 特殊处理
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string)$server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string)$server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * 获取底层数据（用于兼容旧代码）
     *
     * @param string $type 'server', 'query', 'request', 'cookies', 'files'
     * @return array<string, mixed>
     */
    public function getData(string $type): array
    {
        return match ($type) {
            'server' => $this->server,
            'query' => $this->query,
            'request' => $this->request,
            'cookies' => $this->cookies,
            'files' => $this->files,
            default => [],
        };
    }
}
