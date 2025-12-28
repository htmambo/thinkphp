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

use Think\Exception;

/**
 * HTTP 异常类
 * 用于处理 HTTP 相关的错误（404, 500 等）
 */
class HttpException extends Exception implements ThinkExceptionInterface
{
    /**
     * HTTP 状态码
     * @var int
     */
    private $statusCode;

    /**
     * HTTP 响应头
     * @var array
     */
    private $headers;

    /**
     * 构造函数
     *
     * @param int $statusCode HTTP 状态码
     * @param string|null $message 错误消息
     * @param \Exception|null $previous 前一个异常
     * @param array $headers HTTP 响应头
     * @param int $code 异常代码
     * @param array $context 上下文信息
     */
    public function __construct(
        $statusCode,
        $message = null,
        ?\Exception $previous = null,
        array $headers = [],
        $code = 0,
        array $context = []
    ) {
        $this->statusCode = (int)$statusCode;
        $this->headers = $headers;

        // 根据状态码确定严重程度和可恢复性
        $severity = $this->determineSeverity($statusCode);
        $recoverable = $this->isRecoverableStatus($statusCode);

        // 调用父类构造函数
        parent::__construct(
            (string)$message,
            (int)$code,
            $context,
            $severity,
            $recoverable,
            $previous
        );
    }

    /**
     * 获取 HTTP 状态码
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取 HTTP 响应头
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 实现 ThinkExceptionInterface::getContext
     * 将 headers 作为上下文信息的一部分
     *
     * @return array
     */
    public function getContext(): array
    {
        // 合并父类上下文和 headers
        return array_merge(parent::getContext(), [
            'headers' => $this->headers,
            'status_code' => $this->statusCode,
        ]);
    }

    /**
     * 实现 ThinkExceptionInterface::getSeverity
     * 根据状态码确定错误严重程度
     *
     * @return int
     */
    public function getSeverity(): int
    {
        // 使用父类的 severity，或者根据状态码动态计算
        return parent::getSeverity();
    }

    /**
     * 实现 ThinkExceptionInterface::isRecoverable
     * 根据状态码判断是否可恢复
     *
     * @return bool
     */
    public function isRecoverable(): bool
    {
        // 使用父类的 recoverable，或者根据状态码动态计算
        return parent::isRecoverable();
    }

    /**
     * 实现 ThinkExceptionInterface::withContext
     * 支持 headers 作为上下文信息
     *
     * @param array $context
     * @return $this
     */
    public function withContext(array $context): self
    {
        return parent::withContext($context);
    }

    /**
     * 根据状态码确定错误严重程度
     *
     * @param int $statusCode
     * @return int
     */
    private function determineSeverity(int $statusCode): int
    {
        // 5xx 错误视为严重错误
        if ($statusCode >= 500) {
            return E_ERROR;
        }

        // 4xx 错误视为警告
        if ($statusCode >= 400) {
            return E_WARNING;
        }

        // 3xx 重定向视为提示
        if ($statusCode >= 300) {
            return E_NOTICE;
        }

        return E_ERROR;
    }

    /**
     * 根据状态码判断是否可恢复
     *
     * @param int $statusCode
     * @return bool
     */
    private function isRecoverableStatus(int $statusCode): bool
    {
        // 客户端错误 (4xx) 通常是可恢复的
        // 服务器错误 (5xx) 通常是不可恢复的
        return $statusCode < 500;
    }
}
