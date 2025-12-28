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
 * 权限验证中间件
 *
 * 基于 RBAC 的权限验证
 * 支持注解式权限声明
 * 支持路由级和方法级权限
 *
 * @package Think\Middleware
 */
class PermissionMiddleware extends Behavior
{
    /**
     * @var array 白名单路由
     */
    private $whitelist;

    /**
     * @var string 权限模型
     */
    private $permissionModel;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     * @throws Exception
     */
    public function run(&$params)
    {
        // 检查是否启用权限验证
        if (!$this->isEnabled()) {
            return;
        }

        // 检查用户是否已登录
        $userId = C('CURRENT_USER_ID');
        if (!$userId) {
            return; // 未登录由 AuthMiddleware 处理
        }

        // 检查是否在白名单中
        if ($this->isInWhitelist()) {
            return;
        }

        // 验证权限
        if (!$this->checkPermission()) {
            $this->handlePermissionFailure();
        }
    }

    /**
     * 检查权限
     *
     * @return bool
     */
    private function checkPermission(): bool
    {
        // 优先检查注解权限
        $annotationPermission = $this->getPermissionFromAnnotation();

        if ($annotationPermission !== null) {
            return $this->verifyPermission($annotationPermission);
        }

        // 检查路由权限
        return $this->checkRoutePermission();
    }

    /**
     * 从注解中获取权限
     *
     * @return string|null
     */
    private function getPermissionFromAnnotation(): ?string
    {
        $controller = CONTROLLER_NAME;
        $action = ACTION_NAME;
        $className = 'App\\Modules\\' . MODULE_NAME . '\\Controller\\' . $controller;

        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($className);
            $method = $reflection->getMethod($action . C('ACTION_SUFFIX'));

            $comment = $method->getDocComment();

            if ($comment && preg_match('/@permission\s+([a-zA-Z0-9:_]+)/i', $comment, $matches)) {
                return $matches[1];
            }
        } catch (\ReflectionException $e) {
            // 方法不存在
        }

        return null;
    }

    /**
     * 检查路由权限
     *
     * @return bool
     */
    private function checkRoutePermission(): bool
    {
        $route = $this->getCurrentRoute();
        $userId = C('CURRENT_USER_ID');

        return $this->verifyPermission($route, $userId);
    }

    /**
     * 验证权限
     *
     * @param string $permission 权限标识
     * @param string|null $userId 用户 ID
     * @return bool
     */
    private function verifyPermission(string $permission, ?string $userId = null): bool
    {
        $userId = $userId ?? C('CURRENT_USER_ID');

        if (!$userId) {
            return false;
        }

        $this->permissionModel = C('PERMISSION_MODEL', 'Rbac');

        if ($this->permissionModel === 'Rbac') {
            return $this->verifyByRbac($userId, $permission);
        }

        return $this->verifyByAcl($userId, $permission);
    }

    /**
     * 通过 RBAC 验证权限
     *
     * @param string $userId 用户 ID
     * @param string $permission 权限标识
     * @return bool
     */
    private function verifyByRbac(string $userId, string $permission): bool
    {
        // 获取用户角色
        $roles = $this->getUserRoles($userId);

        if (empty($roles)) {
            return false;
        }

        // 超级管理员拥有所有权限
        if (in_array('super_admin', $roles)) {
            return true;
        }

        // 检查角色权限
        foreach ($roles as $role) {
            if ($this->roleHasPermission($role, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取用户角色
     *
     * @param string $userId 用户 ID
     * @return array
     */
    private function getUserRoles(string $userId): array
    {
        $roleTable = C('RBAC_ROLE_TABLE', 'rbac_role');
        $userRoleTable = C('RBAC_USER_ROLE_TABLE', 'rbac_user_role');

        try {
            $roles = M()->table($userRoleTable)
                ->alias('ur')
                ->join("JOIN {$roleTable} r ON ur.role_id = r.id")
                ->where("ur.user_id = '{$userId}'")
                ->field('r.code')
                ->select();

            return array_column($roles, 'code');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 检查角色是否拥有权限
     *
     * @param string $roleCode 角色代码
     * @param string $permission 权限标识
     * @return bool
     */
    private function roleHasPermission(string $roleCode, string $permission): bool
    {
        $permissionTable = C('RBAC_PERMISSION_TABLE', 'rbac_permission');
        $rolePermissionTable = C('RBAC_ROLE_PERMISSION_TABLE', 'rbac_role_permission');
        $roleTable = C('RBAC_ROLE_TABLE', 'rbac_role');

        try {
            $count = M()->table($rolePermissionTable)
                ->alias('rp')
                ->join("JOIN {$permissionTable} p ON rp.permission_id = p.id")
                ->join("JOIN {$roleTable} r ON rp.role_id = r.id")
                ->where("r.code = '{$roleCode}' AND p.code = '{$permission}'")
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 通过 ACL 验证权限
     *
     * @param string $userId 用户 ID
     * @param string $permission 权限标识
     * @return bool
     */
    private function verifyByAcl(string $userId, string $permission): bool
    {
        // 简单的 ACL 实现
        $aclTable = C('ACL_TABLE', 'acl');

        try {
            $count = M()->table($aclTable)
                ->where("user_id = '{$userId}' AND permission = '{$permission}'")
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 判断权限验证是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('PERMISSION_ON', false) === true;
    }

    /**
     * 判断当前路由是否在白名单中
     *
     * @return bool
     */
    private function isInWhitelist(): bool
    {
        $this->whitelist = C('PERMISSION_WHITE_LIST', []);

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
     * 处理权限验证失败
     *
     * @return void
     * @throws Exception
     */
    private function handlePermissionFailure(): void
    {
        // 判断是否为 AJAX 请求
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // 判断是否期望 JSON 响应
        $expectJson = $isAjax || $this->expectsJson();

        if ($expectJson) {
            $this->sendJsonResponse();
        }

        // 显示权限不足页面
        $this->showForbiddenPage();
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
            header('HTTP/1.1 403 Forbidden');
        }

        echo json_encode([
            'code' => 403,
            'message' => C('PERMISSION_FAIL_MESSAGE', '权限不足'),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * 显示权限不足页面
     *
     * @return void
     */
    private function showForbiddenPage(): void
    {
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
        }

        $template = C('PERMISSION_403_TEMPLATE', '');

        if ($template && file_exists($template)) {
            include $template;
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>403 Forbidden</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #d9534f; }
        p { color: #777; }
    </style>
</head>
<body>
    <h1>403 Forbidden</h1>
    <p>' . C('PERMISSION_FAIL_MESSAGE', '权限不足') . '</p>
</body>
</html>';
        }

        exit;
    }
}
