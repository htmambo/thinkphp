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
 * 维护模式中间件
 *
 * 系统维护时统一拦截
 * 支持 IP 白名单
 * 自定义维护页面
 *
 * @package Think\Middleware
 */
class MaintenanceMiddleware extends Behavior
{
    /**
     * @var array IP 白名单
     */
    private $whitelist;

    /**
     * @var string 维护消息
     */
    private $message;

    /**
     * @var string 维护页面模板
     */
    private $template;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     */
    public function run(&$params)
    {
        // 检查是否开启维护模式
        if (!$this->isEnabled()) {
            return;
        }

        // 检查是否在白名单中
        if ($this->isInWhitelist()) {
            return;
        }

        // 显示维护页面
        $this->showMaintenancePage();
    }

    /**
     * 显示维护页面
     *
     * @return void
     */
    private function showMaintenancePage(): void
    {
        // 设置 HTTP 状态码
        if (!headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Retry-After: 3600');
        }

        $this->message = $this->getMessage();
        $this->template = $this->getTemplate();

        // 检查是否有自定义模板
        if ($this->template && file_exists($this->template)) {
            include $this->template;
        } else {
            $this->showDefaultMaintenancePage();
        }

        exit;
    }

    /**
     * 显示默认维护页面
     *
     * @return void
     */
    private function showDefaultMaintenancePage(): void
    {
        echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统维护中</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-weight: 500;
        }
        .info-value {
            color: #333;
            font-weight: 600;
        }
        @media (max-width: 600px) {
            .container {
                padding: 40px 20px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>系统维护中</h1>
        <p>' . htmlspecialchars($this->message) . '</p>
        <div class="info">
            <div class="info-item">
                <span class="info-label">状态</span>
                <span class="info-value">维护中</span>
            </div>
            <div class="info-item">
                <span class="info-label">预计恢复</span>
                <span class="info-value">尽快</span>
            </div>
            <div class="info-item">
                <span class="info-label">当前时间</span>
                <span class="info-value">' . date('Y-m-d H:i:s') . '</span>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * 获取维护消息
     *
     * @return string
     */
    private function getMessage(): string
    {
        return C('MAINTENANCE_MESSAGE', '系统正在进行维护，请稍后再试。');
    }

    /**
     * 获取维护页面模板
     *
     * @return string
     */
    private function getTemplate(): string
    {
        return C('MAINTENANCE_TEMPLATE', '');
    }

    /**
     * 判断是否在白名单中
     *
     * @return bool
     */
    private function isInWhitelist(): bool
    {
        $this->whitelist = C('MAINTENANCE_WHITELIST', []);

        if (empty($this->whitelist)) {
            return false;
        }

        $ip = $this->getClientIp();

        return in_array($ip, $this->whitelist);
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
     * 判断维护模式是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('MAINTENANCE_ON', false) === true;
    }
}
