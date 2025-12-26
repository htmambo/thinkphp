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

namespace Think\Driver\Messager;

/**
 * 消息推送驱动抽象基类
 *
 * 所有消息推送驱动必须继承此类并实现 send() 方法。
 * 提供了统一的 HTTP 请求封装、参数验证、SSL 配置等通用功能。
 *
 * @package Think\Driver\Messager
 * @abstract
 */
abstract class Driver
{
    /**
     * 驱动配置参数
     *
     * @var array
     */
    protected $config = [];

    /**
     * 日志记录器实例（PSR-3）
     *
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger = null;

    /**
     * 构造函数
     *
     * @param array $config 驱动配置参数
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->_initialize();
    }

    /**
     * 初始化回调方法
     *
     * 子类可以重写此方法以进行自定义初始化操作
     *
     * @return void
     */
    protected function _initialize(): void
    {
    }

    /**
     * 参数数据验证
     *
     * 验证消息内容和数据是否为空，不允许发送空消息。
     *
     * @param string $content 消息内容
     * @param array $data 额外数据
     * @return void
     * @throws \Exception 当内容为空且数据为空时抛出
     */
    public function check(string $content, array $data): void
    {
        if ($content === '' && empty($data)) {
            throw new \Exception('不允许发送空消息');
        }
    }

    /**
     * 执行 HTTP 请求
     *
     * @param string $url 请求 URL
     * @param array $query 查询参数
     * @param mixed $data 请求数据
     * @param array $header 请求头
     * @param string $cookie Cookie
     * @return string 响应内容
     */
    protected function curl(string $url, array $query = [], $data = [], array $header = [], string $cookie = ''): string
    {
        $ch = curl_init();
        $url = $this->buildUrl($url, $query);

        curl_setopt($ch, CURLOPT_URL, $url);
        $this->setupSsl($ch, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $cookie = $this->setupCookie($cookie);
        if ($cookie) {
            $header[] = 'Cookie: ' . $cookie;
        }

        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            $data = $this->prepareData($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * 构建完整 URL（包含查询参数）
     *
     * @param string $url 基础 URL
     * @param array $query 查询参数
     * @return string 完整 URL
     */
    private function buildUrl(string $url, array $query): string
    {
        if ($query) {
            $url .= strpos($url, '?') ? '&' : '?';
            $url .= http_build_query($query);
        }
        return $url;
    }

    /**
     * 配置 SSL 验证
     *
     * @param \CurlHandle $ch cURL 句柄
     * @param string $url 请求 URL
     * @return void
     */
    private function setupSsl($ch, string $url): void
    {
        if (stripos($url, 'https://') !== false) {
            // 生产环境必须验证 SSL，开发环境可以临时禁用
            // 建议配置 ssl_ca_cert 证书路径以支持自定义 CA 证书
            $caCert = $this->config['ssl_ca_cert'] ?? null;
            if ($caCert && file_exists($caCert)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caCert);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } elseif (defined('APP_ENV') && APP_ENV === 'production') {
                // 生产环境启用 SSL 验证
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } else {
                // 开发环境禁用 SSL 验证（记录警告）
                $this->logWarning('SSL verification disabled in non-production environment', [
                    'url' => $url,
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            }
        }
    }

    /**
     * 处理 Cookie 参数
     *
     * @param string|array $cookie Cookie 参数
     * @return string 处理后的 Cookie 字符串
     */
    private function setupCookie($cookie): string
    {
        if (!$cookie) {
            return '';
        }

        if (is_array($cookie)) {
            $tmp = $cookie;
            $cookie = '';
            foreach ($tmp as $k => $v) {
                if (is_numeric($k) && strpos($v, '=')) {
                    $cookie .= trim(trim($v), ';');
                } else {
                    $cookie .= $k . '=' . $v;
                }
                $cookie .= '; ';
            }
        }
        return $cookie;
    }

    /**
     * 准备请求数据
     *
     * @param mixed $data 原始数据
     * @return string 准备后的数据
     */
    private function prepareData($data): string
    {
        if (is_array($data)) {
            if (isset($data['form_params'])) {
                return http_build_query($data['form_params'], '', '&');
            }
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    /**
     * 送信
     *
     * @param string $content
     * @param string $subject
     * @param array $data
     * @param string|null $recipient
     * @param mixed ...$params
     *
     * @return bool
     */
    public function send(string $content, string $subject = '', array $data = [], ?string $recipient = null, ...$params): bool
    {
    }

    /**
     * 获取页脚
     *
     * @return string
     */
    protected function getFooter(): string
    {
        $footer = '';
        // 这里可以设置一些公用的信息
        return $footer;
    }

    /**
     * 设置日志记录器
     *
     * 注入 PSR-3 兼容的日志记录器，用于记录推送过程中的错误和调试信息。
     *
     * @param \Psr\Log\LoggerInterface $logger 日志记录器实例
     * @return void
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * 记录错误日志
     *
     * 如果设置了日志记录器则使用，否则回退到 error_log。
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        } elseif (function_exists('error_log')) {
            $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
            error_log($message . $contextStr);
        }
    }

    /**
     * 记录警告日志
     *
     * @param string $message 警告消息
     * @param array $context 上下文信息
     * @return void
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        } elseif (function_exists('error_log')) {
            $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
            error_log('WARNING: ' . $message . $contextStr);
        }
    }

    /**
     * 记录信息日志
     *
     * @param string $message 信息消息
     * @param array $context 上下文信息
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }
}
