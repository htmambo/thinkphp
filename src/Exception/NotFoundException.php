<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------

namespace Think\Exception;

/**
 * 资源未找到异常类
 * 用于处理请求的资源不存在的情况
 * HTTP 状态码: 404 Not Found
 */
class NotFoundException extends HttpException
{
    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public function __construct(
        string $message = "Resource not found",
        array $context = []
    ) {
        parent::__construct(404, $message, null, [], 0, $context);
    }
}
