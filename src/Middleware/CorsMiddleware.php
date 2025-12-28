<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think\Middleware;

use Think\Behavior;

/**
 * 跨域中间件
 *
 * 自动处理 CORS 请求
 * 支持预检请求（OPTIONS）
 * 可配置允许的域名
 *
 * @package Think\Middleware
 */
class CorsMiddleware extends Behavior
{
    /**
     * @var array 允许的域名
     */
    private $allowOrigins;

    /**
     * @var array 允许的方法
     */
    private $allowMethods;

    /**
     * @var array 允许的头部
     */
    private $allowHeaders;

    /**
     * @var bool 是否允许携带凭证
     */
    private $allowCredentials;

    /**
     * @var int 预检请求缓存时间（秒）
     */
    private $maxAge;

    /**
     * @var array 暴露的响应头
     */
    private $exposeHeaders;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     */
    public function run(&$params)
    {
        // 检查是否启用 CORS
        if (!$this->isEnabled()) {
            return;
        }

        // 处理预检请求
        if ($this->isPreflightRequest()) {
            $this->handlePreflightRequest();
        }

        // 设置 CORS 头
        $this->setCorsHeaders();
    }

    /**
     * 判断是否为预检请求
     *
     * @return bool
     */
    private function isPreflightRequest(): bool
    {
        return (
            isset($_SERVER['REQUEST_METHOD']) &&
            strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS' &&
            isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])
        );
    }

    /**
     * 处理预检请求
     *
     * @return void
     */
    private function handlePreflightRequest(): void
    {
        $this->setCorsHeaders();

        // 设置预检请求的额外头
        if (!headers_sent()) {
            header('Access-Control-Max-Age: ' . $this->getMaxAge());
        }

        exit;
    }

    /**
     * 设置 CORS 头
     *
     * @return void
     */
    private function setCorsHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        $origin = $this->getAllowedOrigin();

        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        $methods = $this->getAllowMethods();
        if ($methods) {
            header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        }

        $headers = $this->getAllowHeaders();
        if ($headers) {
            header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
        }

        if ($this->allowCredentials()) {
            header('Access-Control-Allow-Credentials: true');
        }

        $exposeHeaders = $this->getExposeHeaders();
        if ($exposeHeaders) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $exposeHeaders));
        }
    }

    /**
     * 获取允许的来源
     *
     * @return string|null
     */
    private function getAllowedOrigin(): ?string
    {
        $this->allowOrigins = C('CORS_ALLOW_ORIGINS', ['*']);

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (empty($requestOrigin)) {
            return null;
        }

        // 允许所有来源
        if (in_array('*', $this->allowOrigins)) {
            return $requestOrigin;
        }

        // 检查是否在白名单中
        if (in_array($requestOrigin, $this->allowOrigins)) {
            return $requestOrigin;
        }

        // 检查通配符匹配
        foreach ($this->allowOrigins as $pattern) {
            if ($this->matchOrigin($requestOrigin, $pattern)) {
                return $requestOrigin;
            }
        }

        return null;
    }

    /**
     * 匹配来源
     *
     * @param string $origin 请求来源
     * @param string $pattern 模式
     * @return bool
     */
    private function matchOrigin(string $origin, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace(
                ['\\*', '\\.'],
                ['.*', '\\.'],
                preg_quote($pattern, '/')
            ) . '$/i';
            return @preg_match($regex, $origin) === 1;
        }

        return strtolower($origin) === strtolower($pattern);
    }

    /**
     * 获取允许的方法
     *
     * @return array
     */
    private function getAllowMethods(): array
    {
        $this->allowMethods = C('CORS_ALLOW_METHODS', [
            'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'
        ]);

        return array_map('strtoupper', $this->allowMethods);
    }

    /**
     * 获取允许的头部
     *
     * @return array
     */
    private function getAllowHeaders(): array
    {
        $this->allowHeaders = C('CORS_ALLOW_HEADERS', [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-CSRF-TOKEN',
        ]);

        // 添加请求头中的自定义头
        $requestHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        if ($requestHeaders) {
            $headers = array_map('trim', explode(',', $requestHeaders));
            $this->allowHeaders = array_merge($this->allowHeaders, $headers);
        }

        return array_unique($this->allowHeaders);
    }

    /**
     * 是否允许携带凭证
     *
     * @return bool
     */
    private function allowCredentials(): bool
    {
        $this->allowCredentials = C('CORS_ALLOW_CREDENTIALS', false);

        return $this->allowCredentials === true;
    }

    /**
     * 获取预检请求缓存时间
     *
     * @return int
     */
    private function getMaxAge(): int
    {
        $this->maxAge = C('CORS_MAX_AGE', 86400);

        return (int)$this->maxAge;
    }

    /**
     * 获取暴露的响应头
     *
     * @return array
     */
    private function getExposeHeaders(): array
    {
        $this->exposeHeaders = C('CORS_EXPOSE_HEADERS', []);

        return $this->exposeHeaders;
    }

    /**
     * 判断 CORS 是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('CORS_ON', false) === true;
    }
}
