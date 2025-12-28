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

/**
 * PDO异常处理类
 * 重新封装了系统的\PDOException类
 */
class PDOException extends DbException
{
    /**
     * PDOException constructor.
     * @access public
     * @param \PDOException $exception
     * @param array $config
     * @param string $sql
     * @param string $func
     * @param int $code
     */
    public function __construct(\PDOException $exception, array $config, $sql, $func = '', $code = 10501)
    {
        $error = $exception->errorInfo;

        $this->setData('PDO Error Info', [
            'SQLSTATE'             => $error[0],
            'Driver Error Code'    => isset($error[1]) ? $error[1] : 0,
            'Driver Error Message' => isset($error[2]) ? $error[2] : '',
        ]);

        // 传递原始 PDOException 作为异常链
        parent::__construct($exception->getMessage(), $config, $sql, $func, $code, $exception);
    }
}
