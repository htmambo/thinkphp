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

//----------------------------------
// ThinkPHP公共入口文件
//----------------------------------

// 记录开始运行时间
$GLOBALS['_beginTime'] = microtime(true);
// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
if (MEMORY_LIMIT_ON) {
    $GLOBALS['_startUseMems'] = memory_get_usage();
}

// 版本信息
const THINK_VERSION = '3.2.5';
if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

// URL 模式定义
const URL_COMMON   = 0; //普通模式
const URL_PATHINFO = 1; //PATHINFO模式
const URL_REWRITE  = 2; //REWRITE模式
const URL_COMPAT   = 3; // 兼容模式

// 系统常量定义
defined('APP_STATUS') or define('APP_STATUS', ''); // 应用状态 加载对应的配置文件
defined('APP_DEBUG') or define('APP_DEBUG', false); // 是否调试模式
defined('ONLY_SCRIPT') or define('ONLY_SCRIPT', false); // 仅CLI模式运行

defined('STORAGE_TYPE') or define('STORAGE_TYPE', 'File'); // 存储类型 默认为File

defined('CORE_PATH') or define('CORE_PATH', __DIR__ . '/'); // Think类库目录
defined('CHECK_ACTION_COMMENT') or define('CHECK_ACTION_COMMENT', 1);   //是否检查Action的注释，0：不检查，1：仅检查，2：必须存在

// 系统信息
define('IS_CGI', (0 === strpos(PHP_SAPI, 'cgi') || false !== strpos(PHP_SAPI, 'fcgi')) ? 1 : 0);
define('IS_WIN', strstr(PHP_OS, 'WIN') ? 1 : 0);
define('IS_CLI', PHP_SAPI == 'cli' ? 1 : 0);
// define('IS_YAR', isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'PHP Yar Rpc') !== false);
define('IS_YAR', $ua = preg_match('@php\s+yar\s+rpc@iU', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));

// 当前文件名
if (!defined('_PHP_FILE_')) {
    $root = dirname($_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME']);
    if (IS_CLI) {
        define('URL_MODEL', 0);
        define('URL_ROUTER_ON', false);
        if (!defined('APP_PATH')) {
            //直接调用的是ThinkPHP入口文件
            define('APP_PATH', getcwd() . '/Application/');
        }
        $file = '';
        if (isset($_SERVER) && isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
            $file = $_SERVER['argv'][0];
        }
        if (defined('ROOT_PATH')) {
            $root = ROOT_PATH;
        } elseif (basename($file) == 'ThinkPHP.php') {
            $root = getcwd();
            $file = $root . '/think';
        } else {
            $root = dirname(realpath($file));
        }
        define('_PHP_FILE_', $file);
    } elseif (IS_CGI) {
        //CGI/FASTCGI模式下
        $_temp = explode('.php', $_SERVER['PHP_SELF']);
        define('_PHP_FILE_', rtrim(str_replace($_SERVER['HTTP_HOST'], '', $_temp[0] . '.php'), '/'));
    } else {
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            if (stristr($_SERVER['SERVER_SOFTWARE'], 'PHP')) {
                $tmp = '';
                if (strpos($_SERVER['SCRIPT_NAME'], '?')) {
                    list($tmp, $_tmp) = explode('?', $_SERVER['SCRIPT_NAME']);
                } else {
                    $tmp = $_SERVER['PHP_SELF'];
                    if (strpos($tmp, '.php/') !== false) {
                        list($_tmp, $tmp) = explode('.php/', $tmp);
                        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_tmp . '.php';
                    }
                }
                // exit;
                if ($tmp && !isset($_SERVER['PATH_INFO'])) {
                    if ($_SERVER['PHP_SELF'] && $tmp == $_SERVER['PHP_SELF']) {
                        if(substr($_SERVER['REQUEST_URI'], 0, 2) == '/?') {
                            $tmp = '';
                        } else {
                            $scriptname = basename($_SERVER['SCRIPT_NAME']);
                            $_SERVER['SCRIPT_NAME'] = '/' . $scriptname;
                        }
                    }
                    $_SERVER['PATH_INFO'] = $tmp;
                }
                $root = $_SERVER['DOCUMENT_ROOT'];
                if (!$_SERVER['SCRIPT_NAME']) {
                    $_SERVER['SCRIPT_NAME'] = '/index.php';
                }
            }
        }
        define('_PHP_FILE_', rtrim($_SERVER['SCRIPT_NAME'], '/'));
    }

    // 防止有些同学习惯使用相对路径所造成的意外
    if ($root) {
        defined('ROOT_PATH') or define('ROOT_PATH', $root . DIRECTORY_SEPARATOR);
        if (getcwd() !== $root && is_dir($root)) {
            chdir($root);
        }
    }
}
//$tmp = $_SERVER;
//ksort($tmp);
//echo '<textarea style="width: 100%; height: 100px">' . print_r($tmp, 1) . '</textarea>';

defined('APP_PATH') or define('APP_PATH', ROOT_PATH . '/Application/');
defined('RUNTIME_PATH') or define('RUNTIME_PATH', APP_PATH . 'Runtime/'); // 系统运行时目录
defined('COMMON_PATH') or define('COMMON_PATH', APP_PATH . 'Common/'); // 应用公共目录
defined('CONF_PATH') or define('CONF_PATH', COMMON_PATH . 'Conf/'); // 应用配置目录
defined('LANG_PATH') or define('LANG_PATH', COMMON_PATH . 'Lang/'); // 应用语言目录
defined('HTML_PATH') or define('HTML_PATH', APP_PATH . 'Html/'); // 应用静态目录
defined('LOG_PATH') or define('LOG_PATH', RUNTIME_PATH . 'Logs/'); // 应用日志目录
defined('TEMP_PATH') or define('TEMP_PATH', RUNTIME_PATH . 'Temp/'); // 应用缓存目录
defined('DATA_PATH') or define('DATA_PATH', RUNTIME_PATH . 'Data/'); // 应用数据目录
defined('CACHE_PATH') or define('CACHE_PATH', RUNTIME_PATH . 'Cache/'); // 应用模板缓存目录
defined('ADDON_PATH') or define('ADDON_PATH', APP_PATH . 'Addon');

if (!defined('__ROOT__')) {
    $_root = rtrim(dirname(_PHP_FILE_), '/');
    define('__ROOT__', (('/' == $_root || '\\' == $_root) ? '' : $_root));
}
if(!defined('ONLY_SCRIPT') || ONLY_SCRIPT === false) {
    // 加载核心Think类
    require CORE_PATH . 'Think.php';
    // 应用初始化
    Think\Think::start();
}
