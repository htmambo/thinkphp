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
 * 限流中间件
 *
 * 防止 API 滥用，支持多种限流策略
 * 基于 Redis/Memory 实现
 *
 * @package Think\Middleware
 */
class RateLimitMiddleware extends Behavior
{
    /**
     * @var string 存储驱动
     */
    private $driver;

    /**
     * @var array 限流规则
     */
    private $rules;

    /**
     * @var string 默认限流规则
     */
    private $defaultRule;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     */
    public function run(&$params)
    {
        // 检查是否启用限流
        if (!$this->isEnabled()) {
            return;
        }

        // 获取当前路由的限流规则
        $rule = $this->getRateLimitRule();

        // 检查是否超过限流
        if ($this->isRateLimited($rule)) {
            $this->handleRateLimitExceeded($rule);
        }
    }

    /**
     * 获取限流规则
     *
     * @return array
     */
    private function getRateLimitRule(): array
    {
        $this->rules = C('RATE_LIMIT_RULES', []);
        $this->defaultRule = C('RATE_LIMIT_DEFAULT', '60,1');

        $route = $this->getCurrentRoute();

        // 检查是否有特定规则
        if (isset($this->rules[$route])) {
            return $this->parseRule($this->rules[$route]);
        }

        // 检查通配符规则
        foreach ($this->rules as $pattern => $rule) {
            if ($this->matchWildcard($route, $pattern)) {
                return $this->parseRule($rule);
            }
        }

        // 使用默认规则
        return $this->parseRule($this->defaultRule);
    }

    /**
     * 解析限流规则
     *
     * @param string $rule 规则字符串（例如：60,1 表示 60 次/分钟）
     * @return array
     */
    private function parseRule(string $rule): array
    {
        $parts = explode(',', $rule);

        if (count($parts) !== 2) {
            return ['limit' => 60, 'window' => 60];
        }

        return [
            'limit' => (int)$parts[0],
            'window' => (int)$parts[1] * 60, // 转换为秒
        ];
    }

    /**
     * 检查是否超过限流
     *
     * @param array $rule 限流规则
     * @return bool
     */
    private function isRateLimited(array $rule): bool
    {
        $this->driver = C('RATE_LIMIT_DRIVER', 'memory');
        $key = $this->getRateLimitKey();

        if ($this->driver === 'redis') {
            return $this->isRateLimitedByRedis($key, $rule);
        }

        return $this->isRateLimitedByMemory($key, $rule);
    }

    /**
     * 通过 Redis 检查限流
     *
     * @param string $key 限流键
     * @param array $rule 限流规则
     * @return bool
     */
    private function isRateLimitedByRedis(string $key, array $rule): bool
    {
        $redis = $this->getRedisConnection();

        if (!$redis) {
            // Redis 连接失败，降级到内存
            return $this->isRateLimitedByMemory($key, $rule);
        }

        $current = $redis->get($key);

        if ($current === false) {
            // 首次请求，设置计数器
            $redis->setex($key, $rule['window'], 1);
            return false;
        }

        if ((int)$current >= $rule['limit']) {
            return true;
        }

        // 增加计数
        $redis->incr($key);

        return false;
    }

    /**
     * 通过内存检查限流
     *
     * @param string $key 限流键
     * @param array $rule 限流规则
     * @return bool
     */
    private function isRateLimitedByMemory(string $key, array $rule): bool
    {
        // 使用 $_SERVER 作为存储（非持久化，仅用于单进程）
        $cacheKey = '__RATE_LIMIT__' . $key;

        if (!isset($_SERVER[$cacheKey])) {
            $_SERVER[$cacheKey] = [
                'count' => 1,
                'expire' => time() + $rule['window'],
            ];
            return false;
        }

        $data = $_SERVER[$cacheKey];

        // 检查是否过期
        if (time() > $data['expire']) {
            $_SERVER[$cacheKey] = [
                'count' => 1,
                'expire' => time() + $rule['window'],
            ];
            return false;
        }

        if ($data['count'] >= $rule['limit']) {
            return true;
        }

        $_SERVER[$cacheKey]['count']++;

        return false;
    }

    /**
     * 获取限流键
     *
     * @return string
     */
    private function getRateLimitKey(): string
    {
        $ip = $this->getClientIp();
        $route = $this->getCurrentRoute();

        return "rate_limit:{$route}:{$ip}";
    }

    /**
     * 获取客户端 IP
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return '0.0.0.0';
    }

    /**
     * 获取当前路由
     *
     * @return string
     */
    private function getCurrentRoute(): string
    {
        $module = defined('MODULE_NAME') ? MODULE_NAME : '';
        $controller = defined('CONTROLLER_NAME') ? CONTROLLER_NAME : '';
        $action = defined('ACTION_NAME') ? ACTION_NAME : '';

        $parts = array_filter([$module, $controller, $action]);
        return implode('/', array_map('strtolower', $parts));
    }

    /**
     * 通配符匹配
     *
     * @param string $value 待匹配的值
     * @param string $pattern 模式
     * @return bool
     */
    private function matchWildcard(string $value, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if ($pattern === $value) {
            return true;
        }

        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
            return @preg_match($regex, $value) === 1;
        }

        return false;
    }

    /**
     * 获取 Redis 连接
     *
     * @return \Redis|null
     */
    private function getRedisConnection()
    {
        try {
            $redis = new \Redis();
            $host = C('REDIS_HOST', '127.0.0.1');
            $port = C('REDIS_PORT', 6379);
            $password = C('REDIS_PASSWORD', '');
            $database = C('REDIS_DATABASE', 0);

            if ($redis->connect($host, $port)) {
                if ($password) {
                    $redis->auth($password);
                }
                $redis->select($database);
                return $redis;
            }
        } catch (\Exception $e) {
            // 连接失败
        }

        return null;
    }

    /**
     * 判断限流是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('RATE_LIMIT_ON', false) === true;
    }

    /**
     * 处理超过限流
     *
     * @param array $rule 限流规则
     * @return void
     */
    private function handleRateLimitExceeded(array $rule): void
    {
        // 判断是否为 AJAX 请求
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // 判断是否期望 JSON 响应
        $expectJson = $isAjax || $this->expectsJson();

        if ($expectJson) {
            $this->sendJsonResponse($rule);
        }

        // 显示限流页面
        $this->showRateLimitPage($rule);
    }

    /**
     * 判断是否期望 JSON 响应
     *
     * @return bool
     */
    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return stripos($accept, 'application/json') !== false;
    }

    /**
     * 发送 JSON 响应
     *
     * @param array $rule 限流规则
     * @return void
     */
    private function sendJsonResponse(array $rule): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $rule['window']);
        }

        echo json_encode([
            'code' => 429,
            'message' => C('RATE_LIMIT_MESSAGE', '请求过于频繁，请稍后再试'),
            'retry_after' => $rule['window'],
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * 显示限流页面
     *
     * @param array $rule 限流规则
     * @return void
     */
    private function showRateLimitPage(array $rule): void
    {
        if (!headers_sent()) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $rule['window']);
        }

        $message = C('RATE_LIMIT_MESSAGE', '请求过于频繁，请稍后再试');

        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>429 Too Many Requests</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #f0ad4e; }
        p { color: #777; }
    </style>
</head>
<body>
    <h1>429 Too Many Requests</h1>
    <p>' . htmlspecialchars($message) . '</p>
    <p>请在 ' . $rule['window'] . ' 秒后重试</p>
</body>
</html>';

        exit;
    }
}
