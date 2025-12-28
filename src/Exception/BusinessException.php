<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace Think\Exception;

use Think\Exception;

/**
 * 业务逻辑异常类
 * 用于处理业务逻辑错误（如库存不足、余额不足等）
 * HTTP 状态码: 422 Unprocessable Entity
 */
class BusinessException extends Exception
{
    /**
     * 默认错误代码
     */
    const DEFAULT_CODE = 422;

    /**
     * 构造函数
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param array $context 上下文信息
     */
    public function __construct(
        string $message = "Business logic error",
        int $code = 422,
        array $context = []
    ) {
        parent::__construct($message, $code, $context, E_WARNING, true);
    }
}
