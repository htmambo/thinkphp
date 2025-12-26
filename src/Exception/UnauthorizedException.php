<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------

namespace Think\Exception;

/**
 * 未授权异常类
 * 用于处理未认证的请求
 * HTTP 状态码: 401 Unauthorized
 */
class UnauthorizedException extends HttpException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public function __construct(
        string $message = "Unauthorized",
        array $context = []
    ) {
        parent::__construct(401, $message, null, [], 0, $context);
    }
}
