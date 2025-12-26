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
 * ThinkPHP错误异常
 * 主要用于封装 set_error_handler 和 register_shutdown_function 得到的错误
 * 除开从 think\Exception 继承的功能
 * 其他和PHP系统\ErrorException功能基本一样
 */
class ErrorException extends Exception implements ThinkExceptionInterface
{
    /**
     * 获取错误级别（继承父类实现）
     * @return int
     */
    final public function getSeverity(): int
    {
        return parent::getSeverity();
    }

    /**
     * 错误异常构造函数
     *
     * @access public
     * @param int $severity 错误级别
     * @param string $message 错误详细信息
     * @param string $file 出错文件路径
     * @param int $line 出错行号
     * @param array $context 上下文信息（可选）
     */
    public function __construct($severity, $message, $file = '', $line = 0, $context = [])
    {
        $this->file = $file;
        $this->line = $line;
        $this->code = 0;
        $this->message = $message;

        // 自动判断是否可恢复
        $recoverable = $this->isRecoverableError($severity);

        // 自动收集请求上下文
        if (empty($context)) {
            $context = ExceptionContext::capture();
        }

        // 添加文件和行号到上下文
        $context['error_file'] = $file;
        $context['error_line'] = $line;

        // 调用父类构造函数
        parent::__construct($message, 0, $context, $severity, $recoverable);
    }

    /**
     * 判断错误是否可恢复
     *
     * @param int $severity 错误级别
     * @return bool
     */
    private function isRecoverableError(int $severity): bool
    {
        // 致命错误不可恢复
        $fatalErrors = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
        ];

        return !in_array($severity, $fatalErrors, true);
    }

    /**
     * 实现接口：是否可恢复
     *
     * @return bool
     */
    public function isRecoverable(): bool
    {
        return $this->isRecoverableError($this->getSeverity());
    }
}
