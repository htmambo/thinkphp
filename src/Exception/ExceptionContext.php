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

namespace Think\Exception;

/**
 * 异常上下文收集器
 * 用于收集异常发生时的请求上下文信息
 */
class ExceptionContext
{
    /**
     * 收集当前请求的上下文信息
     *
     * @return array 上下文数据
     */
    public static function capture(): array
    {
        return [
            'user_id' => self::getUserId(),
            'ip' => self::getClientIp(),
            'url' => self::getRequestUrl(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => self::getRequestId(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];
    }

    /**
     * 获取当前用户ID
     *
     * @return string|null
     */
    private static function getUserId(): ?string
    {
        // 优先从 Session 获取
        if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }

        // 其次从全局常量获取（如果定义）
        if (defined('USER_ID')) {
            return USER_ID;
        }

        // 再次从可能的认证上下文获取
        if (function_exists('C') && $userId = C('USER_ID')) {
            return $userId;
        }

        return null;
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private static function getClientIp(): string
    {
        // 检查是否通过代理
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // 验证IP地址格式
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * 获取请求URL
     *
     * @return string
     */
    private static function getRequestUrl(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'CLI';
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $protocol . '://' . $host . $uri;
    }

    /**
     * 生成唯一的请求ID
     *
     * @return string
     */
    private static function getRequestId(): string
    {
        // 如果已定义 REQUEST_ID 常量，直接使用
        if (defined('REQUEST_ID')) {
            return REQUEST_ID;
        }

        // 否则生成新的ID
        return uniqid('exc_', true);
    }

    /**
     * 收集数据库相关上下文
     *
     * @param array $config 数据库配置
     * @return array 脱敏后的数据库配置
     */
    public static function sanitizeDbConfig(array $config): array
    {
        $sanitized = $config;

        // 脱敏敏感信息
        if (isset($sanitized['password'])) {
            $sanitized['password'] = '********';
        }
        if (isset($sanitized['username'])) {
            $sanitized['username'] = '********';
        }

        // 移除不需要的信息
        unset($sanitized['dsn'], $sanitized['trace']);

        return $sanitized;
    }

    /**
     * 收集环境变量信息（仅包含安全的信息）
     *
     * @return array
     */
    public static function getSafeEnvironment(): array
    {
        $safeKeys = [
            'APP_DEBUG',
            'APP_STATUS',
            'RUNTIME_ENV',
            'LANG',
            'TZ',
        ];

        $env = [];
        foreach ($safeKeys as $key) {
            if (defined($key)) {
                $env[$key] = constant($key);
            }
        }

        return $env;
    }
}
