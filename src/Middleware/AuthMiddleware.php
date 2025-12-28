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
use Think\Exception;

/**
 * 认证中间件
 *
 * 验证用户登录状态，支持 Session/JWT 两种方式
 * 自动注入当前用户到控制器
 *
 * @package Think\Middleware
 */
class AuthMiddleware extends Behavior
{
    /**
     * @var array 白名单路由（不需要认证）
     */
    private $whitelist;

    /**
     * @var string 认证类型
     */
    private $authType;

    /**
     * @var string Session 键名
     */
    private $sessionKey;

    /**
     * @var string Token Header 名称
     */
    private $tokenHeader;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     * @throws Exception
     */
    public function run(&$params)
    {
        // 检查是否启用认证
        if (!$this->isEnabled()) {
            return;
        }

        // 检查是否在白名单中
        if ($this->isInWhitelist()) {
            return;
        }

        // 验证用户身份
        $userId = $this->authenticate();

        if (!$userId) {
            $this->handleAuthFailure();
        }

        // 注入当前用户 ID 到配置
        C('CURRENT_USER_ID', $userId);
        C('CURRENT_USER', $this->getUser($userId));
    }

    /**
     * 验证用户身份
     *
     * @return string|null 用户 ID
     */
    private function authenticate(): ?string
    {
        $this->authType = C('AUTH_TYPE', 'session');

        if ($this->authType === 'jwt') {
            return $this->authenticateByJwt();
        }

        return $this->authenticateBySession();
    }

    /**
     * 通过 Session 验证
     *
     * @return string|null 用户 ID
     */
    private function authenticateBySession(): ?string
    {
        $this->sessionKey = C('AUTH_SESSION_KEY', 'user_id');

        if (isset($_SESSION[$this->sessionKey]) && !empty($_SESSION[$this->sessionKey])) {
            return (string)$_SESSION[$this->sessionKey];
        }

        return null;
    }

    /**
     * 通过 JWT 验证
     *
     * @return string|null 用户 ID
     */
    private function authenticateByJwt(): ?string
    {
        $this->tokenHeader = C('AUTH_TOKEN_HEADER', 'Authorization');
        $header = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $this->tokenHeader))] ?? '';

        if (empty($header)) {
            return null;
        }

        // 提取 Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = $matches[1];
            return $this->verifyJwtToken($token);
        }

        return null;
    }

    /**
     * 验证 JWT Token
     *
     * @param string $token JWT Token
     * @return string|null 用户 ID
     */
    private function verifyJwtToken(string $token): ?string
    {
        // 简单实现：使用 base64 解码（实际应该使用 jwt 库）
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode($parts[1]);

        if ($payload === false) {
            return null;
        }

        $data = json_decode($payload, true);

        if (!is_array($data) || !isset($data['user_id'])) {
            return null;
        }

        // 检查过期时间
        if (isset($data['exp']) && time() > $data['exp']) {
            return null;
        }

        return (string)$data['user_id'];
    }

    /**
     * 获取用户信息
     *
     * @param string $userId 用户 ID
     * @return array|null
     */
    private function getUser(string $userId): ?array
    {
        $userModel = C('AUTH_USER_MODEL', 'User');

        try {
            $user = M($userModel)->find($userId);

            if ($user) {
                return is_array($user) ? $user : $user->toArray();
            }
        } catch (\Exception $e) {
            // 用户模型不存在或查询失败
        }

        return null;
    }

    /**
     * 判断认证是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('AUTH_ON', false) === true;
    }

    /**
     * 判断当前路由是否在白名单中
     *
     * @return bool
     */
    private function isInWhitelist(): bool
    {
        $this->whitelist = C('AUTH_WHITE_LIST', []);

        if (empty($this->whitelist)) {
            return false;
        }

        $route = $this->getCurrentRoute();

        return $this->matchWildcard($route, $this->whitelist);
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
                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (@preg_match($regex, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 处理认证失败
     *
     * @return void
     * @throws Exception
     */
    private function handleAuthFailure(): void
    {
        // 判断是否为 AJAX 请求
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // 判断是否期望 JSON 响应
        $expectJson = $isAjax || $this->expectsJson();

        if ($expectJson) {
            $this->sendJsonResponse();
        }

        // 跳转到登录页
        $loginUrl = C('AUTH_LOGIN_URL', U('Home/User/login'));
        redirect($loginUrl)->send();
        exit;
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
     * @return void
     */
    private function sendJsonResponse(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 401 Unauthorized');
        }

        echo json_encode([
            'code' => 401,
            'message' => C('AUTH_FAIL_MESSAGE', '未登录或登录已过期'),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
