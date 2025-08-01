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

use think\migration\Factory;
use think\migration\FactoryBuilder;

if (!function_exists('factory')) {
    /**
     * Create a model factory builder for a given class, name, and amount.
     *
     * @param mixed  class|class,name|class,amount|class,name,amount
     * @return FactoryBuilder
     */
    function factory()
    {
        /** @var Factory $factory */
        $factory = app(Factory::class);

        $arguments = func_get_args();

        if (isset($arguments[1]) && is_string($arguments[1])) {
            return $factory->of($arguments[0], $arguments[1])->times($arguments[2] ?? null);
        }
        elseif (isset($arguments[1])) {
            return $factory->of($arguments[0])->times($arguments[1]);
        }

        return $factory->of($arguments[0]);
    }
}

if (!function_exists('database_path')) {
    /**
     * 获取数据迁移脚本地址
     * @param string $path
     * @return string
     */
    function database_path($path = '')
    {
        return app()->getRootPath() . 'database' . DIRECTORY_SEPARATOR . $path;
    }
}
