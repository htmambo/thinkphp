<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------

namespace Think\Exception;

/**
 * 禁止访问异常类
 * 用于处理权限不足的情况
 * HTTP 状态码: 403 Forbidden
 */
class ForbiddenException extends HttpException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public function __construct(
        string $message = "Forbidden",
        array $context = []
    ) {
        parent::__construct(403, $message, null, [], 0, $context);
    }
}
