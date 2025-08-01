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
 * Database相关异常处理类
 */
class DbException extends Exception
{
    /**
     * DbException constructor.
     * @access public
     * @param  string    $message
     * @param  array     $config
     * @param  string    $sql
     * @param  string    $func
     * @param  integer   $code
     */
    public function __construct($message, array $config = [], $sql = '', $func = '', $code = 10500)
    {
        $this->message = $message;
        $this->code    = $code;
        foreach(['line', 'file', 'function'] as $k) {
            if(isset($config[$k])) {
                $this->$k = $config[$k];
                unset($config[$k]);
            }
        }
        $this->setFunc($func);
        if(isset($config['trace'])) {
            $this->setData('trace', $config['trace']);
            unset($config['trace']);
        }

        if($config) {
            unset($config['dsn']);
            $config['password'] = $config['username'] = '********';
            $this->setData('Database Config', $config);
        }
        if($sql)
        $this->setData('SQL', [$sql]);
    }

}
