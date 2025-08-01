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

namespace Think\Console\Command\Queue;

use Think\Console\Output;
use Think\Container;

/**
 * 自定义服务基类
 * Class Service
 * @package think\admin
 */
abstract class Service
{

    /**
     * @var Output
     */
    protected $output;
    /**
     * Service constructor.
     */
    public function __construct($output)
    {
        $this->output = $output;
        $this->initialize();
    }

    /**
     * 初始化服务
     */
    protected function initialize()
    {
    }

    /**
     * 静态实例对象
     * @param array $var 实例参数
     * @param boolean $new 创建新实例
     * @return static|mixed
     */
    public static function instance(array $var = [], $new = false)
    {
        return Container::getInstance()->make(static::class, $var, $new);
    }
}