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

use Psr\Container\NotFoundExceptionInterface;

class ClassNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    protected $class;

    public function __construct($message, $class = '', ?\Throwable $previous = null)
    {
        $this->message = (string)$message;
        $this->class   = $class;
        parent::__construct($this->message, 0, $previous);
    }

    /**
     * 获取类名
     * @access public
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}
