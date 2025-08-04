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

use Think\Exception\ClassNotFoundException;

include __DIR__ . '/Loader.php';

/**
 * ThinkPHP 引导类
 */
class Think
{

    // 类映射
    private static $_map = array();

    // 实例化对象
    private static $_instance = array();

    /**
     * 应用程序初始化
     * @access public
     * @return void
     */
    public static function start()
    {
        include CORE_PATH . 'Helper/functions.php';
        // 注册AUTOLOAD方法
        Loader::register();
        // spl_autoload_register('Think\Think::autoload');
        // 设定错误和异常处理
        Error::register();

        // 初始化文件存储方式
        Storage::connect(STORAGE_TYPE);

        $runtimefile = RUNTIME_PATH . 'common~runtime.php';
        if (!APP_DEBUG && Storage::has($runtimefile)) {
            Storage::load($runtimefile);
        } else {
            $content = '';
            // 读取应用模式
            $mode = include CORE_PATH . 'Helper/core.php';
            // 加载核心文件
            foreach ($mode['core'] as $file) {
                if (is_file($file)) {
                    include $file;
                    if (!APP_DEBUG) {
                        $content .= compile($file);
                    }

                }
            }

            // 加载应用模式配置文件
            foreach ($mode['config'] as $key => $file) {
                is_numeric($key) ? C(load_config($file)) : C($key, load_config($file));
            }

            // 加载模式别名定义
            if (isset($mode['alias'])) {
                Loader::addClassMap(is_array($mode['alias']) ? $mode['alias'] : include $mode['alias']);
            }

            // 加载应用别名定义文件
            if (is_file(CONF_PATH . 'alias.php')) {
                Loader::addClassMap(include CONF_PATH . 'alias.php');
            }

            // 加载模式行为定义
            if (isset($mode['tags'])) {
                Hook::import(is_array($mode['tags']) ? $mode['tags'] : include $mode['tags']);
            }

            // 加载应用行为定义
            if (is_file(CONF_PATH . 'tags.php'))
            // 允许应用增加开发模式配置定义
            {
                Hook::import(include CONF_PATH . 'tags.php');
            }

            // 加载框架底层语言包
            L(include CORE_PATH . 'Lang/' . strtolower(C('DEFAULT_LANG')) . '.php');

            if (!APP_DEBUG) {
                $content .= "\nnamespace { Think\\Loader::addClassMap(" . var_export(self::$_map, true) . ");";
                $content .= "\nL(" . var_export(L(), true) . ");\nC(" . var_export(C(), true) . ');Think\Hook::import(' . var_export(Hook::get(), true) . ');}';
                Storage::put($runtimefile, strip_whitespace('<?php ' . $content));
                chmod($runtimefile, 0644);
            } else {
                // 调试模式加载系统默认的配置文件
                C(include CORE_PATH . 'Helper/Conf/debug.php');
                // 读取应用调试配置文件
                if (is_file(CONF_PATH . 'debug.php')) {
                    C(include CONF_PATH . 'debug.php');
                }

            }
        }

        // 读取当前应用状态对应的配置文件
        if (APP_STATUS && is_file(CONF_PATH . APP_STATUS . '.php')) {
            C(include CONF_PATH . APP_STATUS . '.php');
        }

        // 设置系统时区
        date_default_timezone_set(C('DEFAULT_TIMEZONE'));

        // 记录加载文件时间
        G('loadTime');
        // 运行应用
        App::run();
    }

    /**
     * 取得对象实例 支持调用类的静态方法
     * @param string $class 对象类名
     * @param string $method 类的静态方法名
     * @return object
     */
    public static function instance($class, $method = '')
    {
        $identify = $class . $method;
        if (!isset(self::$_instance[$identify])) {
            if (class_exists($class)) {
                $o = new $class();
                if (!empty($method) && method_exists($o, $method)) {
                    self::$_instance[$identify] = call_user_func(array(&$o, $method));
                } else {
                    self::$_instance[$identify] = $o;
                }

            } else {
                throw new ClassNotFoundException(L('_CLASS_NOT_EXIST_') . '4:' . $class);
            }

        }
        return self::$_instance[$identify];
    }

    /**
     * 添加和获取页面Trace记录
     * @param string $value 变量
     * @param string $label 标签
     * @param string $level 日志级别(或者页面Trace的选项卡)
     * @param boolean $record 是否记录日志
     * @return void|array
     */
    public static function trace($value = '[think]', $label = '', $level = 'DEBUG', $record = false)
    {
        static $_trace = array();
        if ('[think]' === $value) {
            // 获取trace信息
            return $_trace;
        } else {
            $info  = ($label ? $label . ':' : '') . print_r($value, true);
            $level = strtoupper($level);

            Log::record($info, $level, $record);
            if ((defined('IS_AJAX') && IS_AJAX) || $record) {
            } else {
                if (!isset($_trace[$level]) || count($_trace[$level]) > C('TRACE_MAX_RECORD')) {
                    $_trace[$level] = array();
                }
                $_trace[$level][] = $info;
            }
        }
    }
}
