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

/**
 * 分布式文件存储类
 *
 * @method static boolean has($filename, $type = '')
 * @method static string read($filename, $type = '')
 * @method static boolean put($filename, $content, $type = '')
 * @method static boolean append($filename, $content, $type = '')
 * @method static void load($_filename, $vars = null)
 * @method static boolean unlink($filename, $type = '')
 * @method static mixed get($filename, $name, $type = '')
 * @method static array listFiles($path, $filter = '.', $recurse = false, $fullpath = false, $excludeDirs = [], $excludeFiles = [])
 */
class Storage
{

    /**
     * 操作句柄
     * @var string
     * @access protected
     */
    protected static $handler;

    /**
     * 连接分布式文件系统
     * @access public
     * @param string $type 文件类型
     * @param array $options  配置数组
     * @return void
     */
    public static function connect($type = 'File', $options = array())
    {
        $class         = 'Think\\Driver\\Storage\\' . ucwords($type);
        self::$handler = new $class($options);
    }

    public static function __callstatic($method, $args)
    {
        //调用缓存驱动的方法
        if (method_exists(self::$handler, $method)) {
            return call_user_func_array(array(self::$handler, $method), $args);
        }
    }
}
