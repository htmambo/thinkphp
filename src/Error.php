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

namespace Think;

use Think\Console\Output as ConsoleOutput;
use Think\Exception\ErrorException;
use Think\Exception\Handle;
use Think\Exception\ThrowableError;

class Error
{
    /**
     * 配置参数
     * @var array
     */
    protected static $exceptionHandler;

    /**
     * 注册异常处理
     * @access public
     * @return void
     */
    public static function register()
    {
        if (APP_DEBUG) {
            error_reporting(E_ALL ^ E_NOTICE ^ E_USER_NOTICE);
        } else {
            error_reporting(0);
        }
        set_error_handler([__CLASS__, 'appError']);
        set_exception_handler([__CLASS__, 'appException']);
        register_shutdown_function([__CLASS__, 'appShutdown']);
    }

    /**
     * Exception Handler
     * @access public
     * @param \Exception|\Throwable $e
     */
    public static function appException($e)
    {
        if ($e instanceof \Exception) {
            C('FORCE_AJAX_OUTPUT', false);
        } else {
            $e = new ThrowableError($e);
        }

        if (IS_CLI) {
            self::getExceptionHandler()->renderForConsole(new ConsoleOutput, $e);
        } else {
            self::getExceptionHandler()->render($e);
        }
        self::getExceptionHandler()->report($e);
    }

    /**
     * Error Handler
     * @access public
     * @param integer $errno   错误编号
     * @param integer $errstr  详细错误信息
     * @param string $errfile  出错的文件
     * @param integer $errline 出错行号
     * @throws ErrorException
     */
    public static function appError($errno, $errstr, $errfile = '', $errline = 0)
    {
        $halt      = error_reporting();
        if($def = C('IGNORE_ERROR_TYPE')) {
            $halt = E_ALL ^ $def;
        }
        if ($halt & $errno) {
            $error = '';
            switch($errno)
            {
                case E_ERROR:
                    //致命的运行时错误。这类错误一般是不可恢复的情况，例如内存分配导致的问题。后果是导致脚本终止不再继续运行。
                    $error = '[E_ERROR]';
                    break;
                case E_WARNING:
                    //运行时警告 (非致命错误)。仅给出提示信息，但是脚本不会终止运行。
                    $error = '[E_WARNING]';
                    break;
                case E_PARSE:
                    //编译时语法解析错误。解析错误仅仅由分析器产生。
                    $error = '[E_PARSE]';
                    break;
                case E_NOTICE:
                    //运行时通知。表示脚本遇到可能会表现为错误的情况，但是在可以正常运行的脚本里面也可能会有类似的通知。
                    $error = '[E_NOTICE]';
                    break;
                case E_CORE_ERROR:
                    //在 PHP 初始化启动过程中发生的致命错误。该错误类似 E_ERROR，但是是由 PHP 引擎核心产生的。
                    $error = '[E_CORE_ERROR]';
                    break;
                case E_CORE_WARNING:
                    //PHP 初始化启动过程中发生的警告 (非致命错误) 。类似 E_WARNING，但是是由 PHP 引擎核心产生的。
                    $error = '[E_CORE_WARNING]';
                    break;
                case E_COMPILE_ERROR:
                    //致命编译时错误。类似 E_ERROR，但是是由 Zend 脚本引擎产生的。
                    $error = '[E_COMPILE_ERROR]';
                    break;
                case E_COMPILE_WARNING:
                    //编译时警告 (非致命错误)。类似 E_WARNING，但是是由 Zend 脚本引擎产生的。
                    $error = '[E_COMPILE_WARNING]';
                    break;
                case E_USER_ERROR:
                    //用户产生的错误信息。
                    $error = '[E_USER_ERROR]';
                    break;
                case E_USER_WARNING:
                    //用户产生的警告信息。
                    $error = '[E_USER_WARNING]';
                    break;
                case E_USER_NOTICE:
                    //用户产生的通知信息。
                    $error = '[E_USER_NOTICE]';
                    break;
                // case E_STRICT:
                    //启用 PHP 对代码的修改建议，以确保代码具有最佳的互操作性和向前兼容性。
                    // $error = '[E_STRICT]';
                    // break;
                case E_RECOVERABLE_ERROR:
                    //可被捕捉的致命错误。 它表示发生了一个可能非常危险的错误，但是还没有导致PHP引擎处于不稳定的状态。 如果该错误没有被用户自定义句柄捕获 (参见 set_error_handler())，将成为一个 E_ERROR　从而脚本会终止运行。
                    $error = '[E_RECOVERABLE_ERROR]';
                    break;
                case E_DEPRECATED:
                    //运行时通知。启用后将会对在未来版本中可能无法正常工作的代码给出警告。
                    $error = '[E_DEPRECATED]';
                    break;
                case E_USER_DEPRECATED:
                    //用户产生的警告信息。
                    $error = '[E_USER_DEPRECATED]';
                    break;
            }
            // 将错误信息托管至 think\exception\ErrorException
            $exception = new ErrorException($errno, $error . $errstr, $errfile, $errline);
//            throw $exception;
            self::getExceptionHandler()->report($exception);
        }
    }

    /**
     * Shutdown Handler
     * @access public
     */
    public static function appShutdown()
    {
        if (!is_null($error = error_get_last()) && self::isFatal($error['type'])) {
            // 将错误信息托管至think\ErrorException
            $exception = new ErrorException($error['type'], $error['message'], $error['file'], $error['line']);

            self::appException($exception);
        }

        // 写入日志
    }

    /**
     * 确定错误类型是否致命
     *
     * @access protected
     * @param int $type
     * @return bool
     */
    protected static function isFatal($type)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * 设置异常处理类
     *
     * @access public
     * @param mixed $handle
     * @return void
     */
    public static function setExceptionHandler($handle)
    {
        self::$exceptionHandler = $handle;
    }

    /**
     * Get an instance of the exception handler.
     *
     * @access public
     * @return Handle
     */
    public static function getExceptionHandler()
    {
        static $handle;

        if (!$handle) {
            // 异常处理handle
            $class = self::$exceptionHandler;

            if ($class && is_string($class) && class_exists($class) && is_subclass_of($class, "\\think\\exception\\Handle")) {
                $handle = new $class;
            }
            else {
                $handle = new Handle;
                if ($class instanceof \Closure) {
                    $handle->setRender($class);
                }
            }
        }

        return $handle;
    }
}
