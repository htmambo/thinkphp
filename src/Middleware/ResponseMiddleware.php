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
 * 响应格式化中间件
 *
 * 统一 API 响应格式
 * 自动包装 JSON 响应
 * 错误统一处理
 *
 * @package Think\Middleware
 */
class ResponseMiddleware extends Behavior
{
    /**
     * @var bool 是否包装响应
     */
    private $wrap;

    /**
     * @var int 成功码
     */
    private $successCode;

    /**
     * @var string 成功消息
     */
    private $successMessage;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     */
    public function run(&$params)
    {
        // 检查是否启用响应格式化
        if (!$this->isEnabled()) {
            return;
        }

        // 判断是否为 API 请求
        if (!$this->isApiRequest()) {
            return;
        }

        // 注册关闭函数处理响应
        register_shutdown_function([$this, 'formatResponse']);
    }

    /**
     * 格式化响应
     *
     * @return void
     */
    public function formatResponse(): void
    {
        // 响应已经发送，不做处理
        if (headers_sent() || ob_get_level() === 0) {
            return;
        }

        // 获取输出内容
        $content = ob_get_clean();

        // 如果输出为空，不做处理
        if (empty($content)) {
            return;
        }

        // 尝试解析为 JSON
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // 不是 JSON，直接输出
            echo $content;
            return;
        }

        // 如果已经是标准格式，直接输出
        if ($this->isStandardFormat($data)) {
            echo $content;
            return;
        }

        // 包装为标准格式
        $formatted = $this->wrapResponse($data);

        // 发送响应
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($formatted, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 包装响应
     *
     * @param mixed $data 原始数据
     * @return array
     */
    private function wrapResponse($data): array
    {
        if (!$this->shouldWrap()) {
            return $data;
        }

        $statusCode = http_response_code();

        // 错误响应
        if ($statusCode >= 400) {
            return [
                'code' => $statusCode,
                'message' => $data['message'] ?? $this->getErrorMessage($statusCode),
                'data' => null,
            ];
        }

        // 成功响应
        return [
            'code' => $this->getSuccessCode(),
            'message' => $this->getSuccessMessage(),
            'data' => $data,
        ];
    }

    /**
     * 判断是否为标准格式
     *
     * @param array $data 数据
     * @return bool
     */
    private function isStandardFormat(array $data): bool
    {
        return isset($data['code']) && isset($data['message']);
    }

    /**
     * 是否应该包装响应
     *
     * @return bool
     */
    private function shouldWrap(): bool
    {
        $this->wrap = C('API_FORMAT_WRAP', true);

        return $this->wrap === true;
    }

    /**
     * 获取成功码
     *
     * @return int
     */
    private function getSuccessCode(): int
    {
        $this->successCode = C('API_SUCCESS_CODE', 0);

        return (int)$this->successCode;
    }

    /**
     * 获取成功消息
     *
     * @return string
     */
    private function getSuccessMessage(): string
    {
        $this->successMessage = C('API_SUCCESS_MSG', 'success');

        return $this->successMessage;
    }

    /**
     * 获取错误消息
     *
     * @param int $statusCode 状态码
     * @return string
     */
    private function getErrorMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        return $messages[$statusCode] ?? 'Error';
    }

    /**
     * 判断是否为 API 请求
     *
     * @return bool
     */
    private function isApiRequest(): bool
    {
        // 检查路由前缀
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (strpos($uri, '/api/') === 0) {
            return true;
        }

        // 检查 Accept 头
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        // 检查 AJAX 请求
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * 判断响应格式化是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('API_FORMAT_ON', false) === true;
    }
}
