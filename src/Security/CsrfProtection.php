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

namespace Think\Security;

/**
 * CSRF 保护类
 *
 * 提供 CSRF token 验证功能
 */
class CsrfProtection
{
    /**
     * 验证 CSRF token
     *
     * @param string|null $token 要验证的 token
     * @return bool
     */
    public static function validate($token = null)
    {
        if ($token === null) {
            $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (empty($token)) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            return false;
        }

        // 使用 hash_equals 防止时序攻击
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * 验证 CSRF token（失败时抛出异常）
     *
     * @param string|null $token
     * @throws \Exception
     */
    public static function verify($token = null)
    {
        if (!self::validate($token)) {
            // CSRF 攻击检测
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            error_log("CSRF attack detected from IP: {$ip} at URI: {$uri}");

            // 返回 403 错误
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }

    /**
     * 重新生成 token（每次验证后）
     *
     * @return string 新的 token
     */
    public static function regenerate()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 生成新的 token
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['_csrf_token'];
    }

    /**
     * 获取当前 CSRF token
     *
     * @return string
     */
    public static function getToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * 生成 CSRF meta 标签
     *
     * @return string
     */
    public static function metaTag()
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * 生成 CSRF hidden 字段
     *
     * @return string
     */
    public static function hiddenField()
    {
        $token = self::getToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" />';
    }
}
