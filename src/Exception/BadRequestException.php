<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------

namespace Think\Exception;

/**
 * 错误请求异常类
 * 用于处理客户端请求错误（如参数格式错误、必填参数缺失等）
 * HTTP 状态码: 400 Bad Request
 */
class BadRequestException extends HttpException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public function __construct(
        string $message = "Bad Request",
        array $context = []
    ) {
        parent::__construct(400, $message, null, [], 0, $context);
    }
}
