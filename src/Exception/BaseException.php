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
 * ThinkPHP 异常接口
 * 所有自定义异常类应实现此接口以获得统一的功能
 */
interface ThinkExceptionInterface
{
    /**
     * 获取异常上下文信息
     * @return array 上下文数据
     */
    public function getContext(): array;

    /**
     * 获取错误严重程度
     * @return int 错误级别 (使用 PHP 错误常量如 E_ERROR, E_WARNING 等)
     */
    public function getSeverity(): int;

    /**
     * 异常是否可恢复
     * @return bool 可恢复返回 true，不可恢复返回 false
     */
    public function isRecoverable(): bool;

    /**
     * 添加上下文信息
     * @param array $context 要添加的上下文数据
     * @return $this 返回当前实例以支持链式调用
     */
    public function withContext(array $context): self;
}

/**
 * ThinkPHP 异常基类
 * 所有自定义异常类应继承此类
 */
abstract class BaseException extends \Exception implements ThinkExceptionInterface
{
    /**
     * 异常上下文信息
     * @var array
     */
    protected array $context = [];

    /**
     * 错误严重程度
     * @var int
     */
    protected int $severity = E_ERROR;

    /**
     * 是否可恢复
     * @var bool
     */
    protected bool $recoverable = false;

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param int $code 异常代码
     * @param array $context 上下文信息
     * @param int $severity 错误严重程度
     * @param bool $recoverable 是否可恢复
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        array $context = [],
        int $severity = E_ERROR,
        bool $recoverable = false,
        ?\Throwable $previous = null
    ) {
        $this->context = $context;
        $this->severity = $severity;
        $this->recoverable = $recoverable;

        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取异常上下文信息
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取错误严重程度
     * @return int
     */
    public function getSeverity(): int
    {
        return $this->severity;
    }

    /**
     * 异常是否可恢复
     * @return bool
     */
    public function isRecoverable(): bool
    {
        return $this->recoverable;
    }

    /**
     * 添加上下文信息
     * @param array $context
     * @return $this
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }
}
