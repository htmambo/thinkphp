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
 * 请求日志中间件
 *
 * 自动记录所有请求的详细信息，包括：
 * - 请求方法、URL、IP
 * - 请求参数（敏感信息脱敏）
 * - 响应状态码
 * - 执行时间
 * - 内存使用
 *
 * @package Think\Middleware
 */
class RequestLoggingMiddleware extends Behavior
{
    /**
     * @var float 请求开始时间
     */
    private $startTime;

    /**
     * @var int 请求开始内存使用
     */
    private $startMemory;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     */
    public function run(&$params)
    {
        // 记录请求开始
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        // 记录请求信息
        $this->logRequest();

        // 注册请求结束回调
        register_shutdown_function([$this, 'logResponse']);
    }

    /**
     * 记录请求信息
     *
     * @return void
     */
    private function logRequest(): void
    {
        if (!$this->isLogEnabled()) {
            return;
        }

        $data = [
            'time' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '-',
            'params' => $this->sanitizeParams($this->getRequestParams()),
        ];

        $this->writeLog('REQUEST', $data);
    }

    /**
     * 记录响应信息
     *
     * @return void
     */
    public function logResponse(): void
    {
        if (!$this->isLogEnabled()) {
            return;
        }

        $duration = microtime(true) - $this->startTime;
        $memory = memory_get_usage() - $this->startMemory;

        $data = [
            'time' => date('Y-m-d H:i:s'),
            'duration' => round($duration * 1000, 2) . 'ms',
            'memory' => $this->formatBytes($memory),
            'status' => http_response_code(),
        ];

        $this->writeLog('RESPONSE', $data);
    }

    /**
     * 判断日志是否启用
     *
     * @return bool
     */
    private function isLogEnabled(): bool
    {
        return C('REQUEST_LOG_ON', false) && PHP_SAPI !== 'cli';
    }

    /**
     * 获取请求参数
     *
     * @return array
     */
    private function getRequestParams(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            return $_GET;
        }

        if ($method === 'POST') {
            return $_POST;
        }

        // 其他方法从 php://input 读取
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * 参数脱敏（过滤敏感信息）
     *
     * @param array $params 原始参数
     * @return array 脱敏后的参数
     */
    private function sanitizeParams(array $params): array
    {
        $sensitiveKeys = [
            'password', 'passwd', 'pwd',
            'token', 'csrf_token', '_token',
            'secret', 'api_key', 'apikey',
            'credit_card', 'card_number',
        ];

        $sanitized = [];
        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '***FILTERED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParams($value);
            } else {
                $sanitized[$key] = substr((string)$value, 0, 100);
            }
        }

        return $sanitized;
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
     * 写入日志
     *
     * @param string $type 日志类型
     * @param array $data 日志数据
     * @return void
     */
    private function writeLog(string $type, array $data): void
    {
        $logPath = C('REQUEST_LOG_PATH', null, RUNTIME_PATH . 'Logs/Request/');
        $logFile = $logPath . date('Y-m-d') . '.log';

        // 确保目录存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // 格式化日志
        $message = sprintf(
            "[%s] [%s] %s\n",
            $type,
            $data['time'] ?? '',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // 写入文件
        error_log($message, 3, $logFile);
    }

    /**
     * 格式化字节数
     *
     * @param int $bytes 字节数
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
