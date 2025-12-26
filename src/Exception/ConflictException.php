<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------

namespace Think\Exception;

/**
 * 冲突异常类
 * 用于处理请求与当前资源状态冲突的情况
 * HTTP 状态码: 409 Conflict
 */
class ConflictException extends HttpException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public function __construct(
        string $message = "Conflict",
        array $context = []
    ) {
        parent::__construct(409, $message, null, [], 0, $context);
    }
}
