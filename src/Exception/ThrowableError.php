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

class ThrowableError extends \ErrorException
{
    private $_msg = '';

    final function getFunc()
    {
        $func = '';
        if (preg_match('/^class ([\'"]*)([^\']+)\\1 not found/i', $this->_msg, $matches)) {
            $func = $matches[2];
        } else if($this->_msg && substr($this->_msg, -1) == ')') {
            $pos = strrpos($this->_msg, ' ');
            $func = substr($this->_msg, $pos + 1);
        }
        return $func;
    }
    public function __construct(\Throwable $e)
    {
        $this->_msg = $e->getMessage();
        if ($e instanceof \ParseError) {
            $message  = '[E_PARSE]Parse error: ' . $e->getMessage();
            $severity = E_PARSE;
        } elseif ($e instanceof \TypeError) {
            $message  = '[E_RECOVERABLE_ERROR]Type error: ' . $e->getMessage();
            $severity = E_RECOVERABLE_ERROR;
        } else {
            $message  = '[E_ERROR]Fatal error: ' . $e->getMessage();
            $severity = E_ERROR;
        }

        parent::__construct(
            $message,
            $e->getCode()?:$severity,
            $severity,
            $e->getFile(),
            $e->getLine()
        );
        $this->setTrace($e->getTrace());
    }

    protected function setTrace($trace)
    {
        $traceReflector = new \ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($this, $trace);
    }
}
