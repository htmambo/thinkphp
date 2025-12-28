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
use Think\Security\CsrfTokenManager;
use Think\Exception;

/**
 * CSRF 验证中间件
 *
 * 在请求入口统一验证 CSRF Token，替代分散的 validate 调用
 * Laravel 风格：非安全方法自动验证，支持白名单
 *
 * @package Think\Middleware
 */
class CsrfMiddleware extends Behavior
{
    /**
     * @var CsrfTokenManager Token 管理器
     */
    private $manager;

    /**
     * @var array 需要验证的 HTTP 方法
     */
    private $unsafeMethods;

    /**
     * @var array 白名单（支持通配符）
     */
    private $whitelist;

    /**
     * @var string Token 字段名
     */
    private $tokenName;

    /**
     * @var array Token 来源（post、header、json）
     */
    private $sources;

    /**
     * @var bool 是否启用 Origin/Referer 校验
     */
    private $checkOrigin;

    /**
     * @var array 信任的来源
     */
    private $trustedOrigins;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->manager = new CsrfTokenManager();
        $this->unsafeMethods = C('CSRF_UNSAFE_METHODS', ['POST', 'PUT', 'PATCH', 'DELETE']);
        $this->whitelist = C('CSRF_WHITELIST', []);
        $this->tokenName = C('CSRF_TOKEN_NAME', '_token');
        $this->sources = C('CSRF_SOURCES', ['post', 'header']);
        $this->checkOrigin = C('CSRF_ORIGIN_CHECK', false);
        $this->trustedOrigins = C('CSRF_TRUSTED_ORIGINS', []);
    }

    /**
     * 执行行为 run方法是Behavior唯一的接口
     *
     * @access public
     * @param mixed $params 行为参数
     * @return mixed
     * @throws Exception
     */
    public function run(&$params)
    {
        // 检查是否应该跳过验证
        if ($this->shouldSkip()) {
            return;
        }

        // 验证 Token
        if (!$this->validate()) {
            $this->handleFailure();
        }

        // Origin/Referer 二次校验（可选）
        if ($this->checkOrigin && !$this->validateOrigin()) {
            $this->handleOriginFailure();
        }
    }

    /**
     * 判断是否应该跳过验证
     *
     * @return bool
     */
    private function shouldSkip(): bool
    {
        // 1. 检查是否为 CLI 环境
        if (PHP_SAPI === 'cli') {
            return true;
        }

        // 2. 检查 HTTP 方法是否安全
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array(strtoupper($method), array_map('strtoupper', $this->unsafeMethods), true)) {
            return true;
        }

        // 3. 检查是否在白名单中
        $route = $this->getCurrentRoute();
        if ($this->matchWildcard($route, $this->whitelist)) {
            return true;
        }

        return false;
    }

    /**
     * 验证 Token
     *
     * @return bool
     */
    private function validate(): bool
    {
        $value = $this->extractToken();

        if (!$value) {
            return false;
        }

        // 如果是一次性 token，使用 validateAndRemove
        $oneTime = C('CSRF_ONE_TIME', null, false);

        if ($oneTime) {
            return $this->manager->validateAndRemove($value);
        } else {
            return $this->manager->validate($value);
        }
    }

    /**
     * 从请求中提取 Token
     *
     * @return string|null
     */
    private function extractToken(): ?string
    {
        foreach ($this->sources as $source) {
            $source = strtolower(trim($source));

            switch ($source) {
                case 'post':
                    if (isset($_POST[$this->tokenName]) && $_POST[$this->tokenName] !== '') {
                        return (string)$_POST[$this->tokenName];
                    }
                    break;

                case 'get':
                    if (isset($_GET[$this->tokenName]) && $_GET[$this->tokenName] !== '') {
                        return (string)$_GET[$this->tokenName];
                    }
                    break;

                case 'header':
                    $token = $this->extractFromHeader();
                    if ($token !== null && $token !== '') {
                        return $token;
                    }
                    break;

                case 'json':
                    $token = $this->extractFromJson();
                    if ($token !== null && $token !== '') {
                        return $token;
                    }
                    break;
            }
        }

        return null;
    }

    /**
     * 从 HTTP Header 提取 Token
     *
     * @return string|null
     */
    private function extractFromHeader(): ?string
    {
        $headers = ['X-CSRF-TOKEN', 'X-XSRF-TOKEN'];

        foreach ($headers as $header) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $header));

            if (isset($_SERVER[$serverKey]) && $_SERVER[$serverKey] !== '') {
                return trim((string)$_SERVER[$serverKey]);
            }
        }

        // 也尝试使用配置的 header 名称
        $headerName = C('CSRF_HEADER_NAME', 'X-CSRF-TOKEN');
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));

        if (isset($_SERVER[$serverKey]) && $_SERVER[$serverKey] !== '') {
            return trim((string)$_SERVER[$serverKey]);
        }

        return null;
    }

    /**
     * 从 JSON Body 提取 Token
     *
     * @return string|null
     */
    private function extractFromJson(): ?string
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') === false && stripos($contentType, '+json') === false) {
            return null;
        }

        // 使用静态缓存避免重复读取
        static $cachedJson = null;

        if ($cachedJson === null) {
            $raw = @file_get_contents('php://input');

            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $cachedJson = is_array($decoded) ? $decoded : [];
            } else {
                $cachedJson = [];
            }
        }

        if (isset($cachedJson[$this->tokenName]) && $cachedJson[$this->tokenName] !== '') {
            return (string)$cachedJson[$this->tokenName];
        }

        return null;
    }

    /**
     * 验证 Origin/Referer（Django 风格双重防护）
     *
     * @return bool
     */
    private function validateOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

        if (empty($origin)) {
            return false;
        }

        return $this->matchWildcard($origin, $this->trustedOrigins);
    }

    /**
     * 处理验证失败
     *
     * @return void
     * @throws Exception
     */
    private function handleFailure(): void
    {
        $failMode = C('CSRF_FAIL_MODE', 'exception');

        if ($failMode === 'ajax_json' && $this->isAjaxRequest()) {
            $this->sendJsonError('CSRF token validation failed');
        }

        throw new Exception('CSRF token validation failed', 400);
    }

    /**
     * 处理 Origin 验证失败
     *
     * @return void
     * @throws Exception
     */
    private function handleOriginFailure(): void
    {
        $failMode = C('CSRF_FAIL_MODE', 'exception');

        if ($failMode === 'ajax_json' && $this->isAjaxRequest()) {
            $this->sendJsonError('CSRF origin validation failed');
        }

        throw new Exception('CSRF origin validation failed', 400);
    }

    /**
     * 发送 JSON 错误响应
     *
     * @param string $message 错误消息
     * @return void
     */
    private function sendJsonError(string $message): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 400 Bad Request');
        }

        echo json_encode([
            'success' => false,
            'code' => 400,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * 判断是否为 AJAX 请求
     *
     * @return bool
     */
    private function isAjaxRequest(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        if (!empty($_SERVER['HTTP_AJAX']) && $_SERVER['HTTP_AJAX'] === 'true') {
            return true;
        }

        if (defined('IS_AJAX') && IS_AJAX) {
            return true;
        }

        return false;
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
        return implode('/', $parts);
    }

    /**
     * 通配符匹配
     *
     * @param string $value 待匹配的值
     * @param array $patterns 模式列表
     * @return bool
     */
    private function matchWildcard(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if ($pattern === $value) {
                return true;
            }

            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';
                if (@preg_match($regex, $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
