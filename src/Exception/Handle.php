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

use Exception;
use Think\Console\Output;
use Think\Log;

class Handle
{
    protected $render;
    protected $ignoreReport = [
        '\\Think\\Exception\\HttpException',
    ];

    public function setRender($render)
    {
        $this->render = $render;
    }

    /**
     * Report or log an exception.
     *
     * @access public
     * @param  \Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            if (APP_DEBUG) {
                $data = [
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'message' => $this->getMessage($exception),
                    'code'    => $this->getCode($exception),
                ];
                $log = '[' . $data['code'] . ']' . $data['message'] . '[' . $data['file'] . ':' . $data['line'] . ']';
            } else {
                $data = [
                    'code'    => $this->getCode($exception),
                    'message' => $this->getMessage($exception),
                ];
                $log = '[' . $data['code'] . ']' . $data['message'];
            }
            if (C('LOG_RECORD')) {
                $log .= "\r\n" . $exception->getTraceAsString();
                Log::write($log);
            }
        }
    }

    protected function isIgnoreReport(Exception $exception)
    {
        foreach ($this->ignoreReport as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param  \Exception $e
     * @return Response
     */
    public function render(Exception $e)
    {
        if ($this->render && $this->render instanceof \Closure) {
            $result = call_user_func_array($this->render, [$e]);

            if ($result) {
                return $result;
            }
        }

        return $this->convertExceptionToResponse($e);
    }

    /**
     * @access public
     * @param  Output    $output
     * @param  Exception $e
     */
    public function renderForConsole(Output $output, Exception $e)
    {
        if(APP_DEBUG) {
            $output->setVerbosity(Output::VERBOSITY_DEBUG);
        }

        $output->renderException($e);
    }

    /**
     * @access protected
     * @param  Exception $exception
     * @return Response
     */
    protected function convertExceptionToResponse(Exception $exception)
    {
        // 收集异常数据
        if (APP_DEBUG) {
            // 调试模式，获取详细的错误信息
            $trace = processTrace($exception);
            $data = [
                'name'    => get_class($exception),
                'file'    => $trace[0]['file'],
                'line'    => $trace[0]['line'],
                'message' => $this->getMessage($exception),
                'trace'   => $trace,
                'code'    => $this->getCode($exception),
                'source'  => $this->getSourceCode($trace),
                'datas'   => $this->getExtendData($exception),
                'tables'  => [
                    'GET Data'              => $_GET,
                    'POST Data'             => $_POST,
                    'Files'                 => $_FILES,
                    'Cookies'               => $_COOKIE,
                    'Session'               => isset($_SESSION) ? $_SESSION : [],
                    'Server/Request Data'   => $_SERVER,
                    'Environment Variables' => $_ENV,
                    'ThinkPHP Constants'    => $this->getConst(),
                ],
            ];
        } else {
            // 部署模式仅显示 Code 和 Message
            $data = [
                'code'    => $this->getCode($exception),
                'message' => $this->getMessage($exception),
            ];

            if (!C('SHOW_ERROR_MSG')) {
                // 不显示详细错误信息
                $data['message'] = '出错了！！！';
            }
        }

        //保留一层
        while (ob_get_level() > 1) {
            ob_end_clean();
        }

        $data['echo'] = ob_get_clean();
        //如果当前页面是以AJAX方式提交的，需要处理一下
        if ((defined('IS_AJAX') && constant('IS_AJAX')) || C('FORCE_AJAX_OUTPUT')) {
            header('Content-Type:application/json; charset=utf-8');
            $data = [
                'status' => 0,
                'info' => $data['message'],
                'file' => $data['file'],
                'line' => $data['line'],
            ];
            if(!APP_DEBUG) {
                unset($data['file'], $data['line']);
            }
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            if(isset($_GET[C('VAR_JSONP_HANDLER')])){
                $handler = $_GET[C('VAR_JSONP_HANDLER')];
                $data = $handler . '(' . $data . ');';
            };
            exit($data);
        }

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        if (!isset($statusCode)) {
            $statusCode = 500;
        }
        send_http_status($statusCode);

        // 安全修复: 使用显式变量赋值替代extract()
        $name = $data['name'] ?? '';
        $message = $data['message'] ?? '';
        $file = $data['file'] ?? '';
        $line = $data['line'] ?? '';
        $trace = $data['trace'] ?? '';
        $exceptionFile = C('TMPL_EXCEPTION_FILE', null, CORE_PATH . 'Helper/Tpl/think_exception.tpl');
        if(file_exists($exceptionFile)) {
            include $exceptionFile;
        } else {
            dump($data);
            dump($exceptionFile);
        }
    }

    /**
     * 获取错误编码
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  \Exception $exception
     * @return integer                错误编码
     */
    protected function getCode(Exception $exception)
    {
        $code = $exception->getCode();

        if (!$code && $exception instanceof ErrorException) {
            $code = $exception->getSeverity();
        }

        return $code;
    }

    /**
     * 获取错误信息
     * ErrorException则使用错误级别作为错误编码
     * @access protected
     * @param  \Exception $exception
     * @return string                错误信息
     */
    protected function getMessage(Exception $exception)
    {
        return $exception->getMessage();
    }

    /**
     * 获取出错文件内容
     * 获取错误的前9行和后9行
     * @access protected
     * @param  array $trace
     * @return array                 错误文件内容
     */
    protected function getSourceCode($trace)
    {
        $line = (int)$trace[0]['line'];
        $file = $trace[0]['file'];
        if(!file_exists($file)) return [];
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($file);
            $source   = [
                'first'  => $first,
                'source' => array_slice($contents, $first - 1, 19),
            ];
        } catch (Exception $e) {
            $source = [];
        }

        return $source;
    }

    /**
     * 获取异常扩展信息
     * 用于非调试模式html返回类型显示
     * @access protected
     * @param  \Exception $exception
     * @return array                 异常类定义的扩展数据
     */
    protected function getExtendData(Exception $exception)
    {
        $data = [];

        if ($exception instanceof \Think\Exception && method_exists($exception, 'getdata')) {
            $data = $exception->getData();
        }

        return $data;
    }

    /**
     * 获取常量列表
     * @access private
     * @return array 常量列表
     */
    private static function getConst()
    {
        $const = get_defined_constants(true);

        return isset($const['user']) ? $const['user'] : [];
    }
}
