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

/**
 * Think 系统函数库
 */

/**
 * 获取和设置配置参数 支持批量定义
 *
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @param mixed $default 默认值
 * @return mixed
 */
function C($name = null, $value = null, $default = null)
{
    static $_config = array();
    // 无参数时获取所有
    if (empty($name)) {
        return $_config;
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtoupper($name);
            if (is_null($value)) {
                if (defined($name)) {
                    return constant($name);
                } else {
                    return isset($_config[$name]) ? $_config[$name] : $default;
                }
            }
            $_config[$name] = $value;
            return $_config[$name] ?: $default;
        }
        // 二维数组设置和获取支持
        $name = explode('.', $name);
        $name[0] = strtoupper($name[0]);
        $result = &$_config;
        foreach ($name as $k => $v) {
            if ($k === 0) {
                //key必须大写，元素里面所涉及到的就无所谓了
                $v = strtoupper($v);
            }
            $result = &$result[$v];
        }
        if (is_null($value)) {
            return $result;
            //return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : $default;
        }
        $result = $value;
        //$_config[$name[0]][$name[1]] = $value;
        return null;
    }
    // 批量设置
    if (is_array($name)) {
        $_config = array_merge($_config, array_change_key_case($name, CASE_UPPER));
        return null;
    }
    return null; // 避免非法参数
}

/**
 * 加载配置文件 支持格式转换 仅支持一级配置
 *
 * @param string $file 配置文件名
 * @return array
 * @throws \Think\Exception
 */
function load_config($file)
{
    if (!file_exists($file)) {
        return [];
    }
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'php':
            if (file_exists($file)) {
                return include $file;
            }
            return [];
        case 'ini':
            return parse_ini_file($file);
        case 'xml':
            return (array)simplexml_load_file($file);
        case 'json':
            return json_decode(file_get_contents($file), true);
        default:
            E(L('_NOT_SUPPORT_') . ':' . $ext);
    }
}

/**
 * 抛出异常处理
 *
 * @param string $msg 异常消息
 * @param integer $code 异常代码 默认为0
 * @return void
 * @throws Think\Exception
 */
function E($msg, $code = 0, $file = '', $line = '')
{
    if ($file) {
        $result = new Think\Exception\ErrorException($code, $msg, $file, $line);
        $result->setFunc('Think\\Exception\\ErrorException');
    } else {
        $result = new Think\Exception($msg, $code);
        $result->setFunc('Think\\Exception');
    }
    throw $result;
}

/**
 * 记录和统计时间（微秒）和内存使用情况
 * 使用方法:
 * <code>
 * G('begin'); // 记录开始标记位
 * // ... 区间运行代码
 * G('end'); // 记录结束标签位
 * echo G('begin','end',6); // 统计区间运行时间 精确到小数后6位
 * echo G('begin','end','m'); // 统计区间内存使用情况
 * 如果end标记位没有定义，则会自动以当前作为标记位
 * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
 * </code>
 *
 * @param string $start 开始标签
 * @param string $end 结束标签
 * @param integer|string $dec 小数位或者m
 *
 * @return string|null
 */
function G($start, $end = '', $dec = 4)
{
    static $_info = array();
    static $_mem = array();
    if (is_float($end)) {
        // 记录时间
        $_info[$start] = $end;
    } elseif (!empty($end)) {
        // 统计时间和内存使用
        if (!isset($_info[$end])) {
            $_info[$end] = microtime(true);
        }

        if (MEMORY_LIMIT_ON && 'm' == $dec) {
            if (!isset($_mem[$end])) {
                $_mem[$end] = memory_get_usage();
            }

            return number_format(($_mem[$end] - $_mem[$start]) / 1024);
        } else {
            return number_format(($_info[$end] - $_info[$start]), $dec);
        }
    } else {
        // 记录时间和内存使用
        $_info[$start] = microtime(true);
        if (MEMORY_LIMIT_ON) {
            $_mem[$start] = memory_get_usage();
        }
    }
    return null;
}

/**
 * 获取和设置语言定义(不区分大小写)
 *
 * @param string|array $key 语言变量
 * @param mixed $value 语言值或者变量
 * @return mixed
 */
function L($key = null, $value = null)
{
    static $_lang = array();
    // 空参数返回所有定义
    if (empty($key)) {
        return $_lang;
    }

    // 判断语言获取(或设置)
    // 若不存在,直接返回全大写$name
    if (is_string($key)) {
        $name = strtoupper($key);
        $result = null;
        if (is_null($value)) {
            $result = isset($_lang[$name]) ? $_lang[$name] : $key;
        } elseif (is_array($value)) {
            // 支持变量
            $search = array_keys($value);
            foreach ($search as &$v) {
                $v = '{$' . $v . '}';
            }
            $result = str_replace($search, $value, isset($_lang[$name]) ? $_lang[$name] : $key);
        }
        if (!is_null($result)) {
            if (IS_CLI) {
                $ret = PHP_EOL;
            } else {
                $ret = '<br />';
            }
            return str_replace('{\n}', $ret, $result);
        }
        $_lang[$name] = $value; // 语言定义
        return null;
    }
    // 批量定义
    if (is_array($key)) {
        $_lang = array_merge($_lang, array_change_key_case($key, CASE_UPPER));
    }

    return null;
}

/**
 * 添加和获取页面Trace记录
 *
 * @param string $value 变量
 * @param string $label 标签
 * @param string $level 日志级别
 * @param boolean $record 是否记录日志
 * @return void|array
 */
function trace($value = '[think]', $label = '', $level = 'DEBUG', $record = false)
{
    return Think\Think::trace($value, $label, $level, $record);
}

/**
 * 编译文件
 *
 * @param string $filename 文件名
 * @return string
 */
function compile($filename)
{
    $content = php_strip_whitespace($filename);
    $content = trim(substr($content, 5));
    // 替换预编译指令
    $content = preg_replace('/\/\/\[RUNTIME\](.*?)\/\/\[\/RUNTIME\]/s', '', $content);
    if (0 === strpos($content, 'namespace')) {
        $content = preg_replace('/namespace\s(.*?);/', 'namespace \\1{', $content, 1);
    } else {
        $content = 'namespace {' . $content;
    }
    if ('?>' == substr($content, -2)) {
        $content = substr($content, 0, -2);
    }

    return $content . '}';
}

/**
 * 获取模版文件 格式 资源://模块@主题/控制器/操作
 *
 * @param string $template 模版资源地址
 * @param string $layer 视图层（目录）名称
 * @return string
 */
function T($template = '', $layer = '')
{
    // 解析模版资源地址
    if (false === strpos($template, '://')) {
        $template = 'http://' . str_replace(':', '/', $template);
    }
    $info = parse_url($template);
    $file = $info['host'] . (isset($info['path']) ? $info['path'] : '');
    $module = isset($info['user']) ? $info['user'] . '/' : MODULE_NAME . '/';
    $extend = $info['scheme'];
    $layer = $layer ? $layer : C('DEFAULT_V_LAYER');

    // 获取当前主题的模版路径
    $auto = C('AUTOLOAD_NAMESPACE');
    if ($auto && isset($auto[$extend])) {
        // 扩展资源
        $baseUrl = $auto[$extend] . $module . $layer . '/';
    } elseif (C('VIEW_PATH')) {
        // 改变模块视图目录
        $baseUrl = C('VIEW_PATH');
    } elseif (C('TMPL_PATH')) {
        // 指定全局视图目录
        $baseUrl = C('TMPL_PATH') . $module;
    } else {
        $baseUrl = APP_PATH . $module . $layer . '/';
    }

    // 获取主题
    $theme = substr_count($file, '/') < 2 ? C('DEFAULT_THEME') : '';

    // 分析模板文件规则
    $depr = C('TMPL_FILE_DEPR');
    if ('' == $file) {
        // 如果模板文件名为空 按照默认规则定位
        $file = CONTROLLER_NAME . $depr . ACTION_NAME;
    } elseif (false === strpos($file, '/')) {
        $file = CONTROLLER_NAME . $depr . $file;
    } elseif ('/' != $depr) {
        $file = substr_count($file, '/') > 1 ? substr_replace($file, $depr, strrpos($file, '/'), 1) : str_replace('/', $depr, $file);
    }
    return $baseUrl . ($theme ? $theme . '/' : '') . $file . C('TMPL_TEMPLATE_SUFFIX');
}

/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * I('id',0); 获取id参数 自动判断get或者post
 * I('post.name','','htmlspecialchars'); 获取$_POST['name']
 * I('get.'); 获取$_GET
 * </code>
 *
 * @param string $name 变量的名称 支持指定类型
 * @param mixed $default 不存在的时候默认值
 * @param mixed $filter 参数过滤方法
 * @param mixed $datas 要获取的额外数据源
 * @return mixed
 */
function I($name, $default = '', $filter = null, $datas = null)
{
    static $_PUT = null;
    if (strpos($name, '/')) {
        // 指定修饰符
        list($name, $type) = explode('/', $name, 2);
    } elseif (C('VAR_AUTO_STRING')) {
        // 默认强制转换为字符串
        $type = 's';
    }
    if (strpos($name, '.')) {
        // 指定参数来源
        list($method, $name) = explode('.', $name, 2);
    } else {
        // 默认为自动判断
        $method = 'param';
    }
    switch (strtolower($method)) {
        case 'g':
        case 'get':
            $input = &$_GET;
            break;
        case 'p':
        case 'post':
            $input = &$_POST;
            if(!$input) {
                // 根据请求头格式判断是字符串提交还是json提交
                $input = file_get_contents('php://input');
                if($input) {
                    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
                    if(strpos($contentType, 'application/json') !== false && strpos($input, '{') !== false) {
                        $json = json_decode($input, true);
                        if($json) {
                            $input = $json;
                        } else {
                            // TODO: 要不要解析字符串？
                        }
                    } else {
                        parse_str($input, $input);
                    }
                }
            }
            break;
        case 'put':
            if (is_null($_PUT)) {
                parse_str(file_get_contents('php://input'), $_PUT);
            }
            $input = $_PUT;
            break;
        case 'param':
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
            switch ($method) {
                case 'POST':
                    $input = I('post.');
                    break;
                case 'PUT':
                    if (is_null($_PUT)) {
                        parse_str(file_get_contents('php://input'), $_PUT);
                    }
                    $input = $_PUT;
                    break;
                default:
                    $input = $_GET;
            }
            break;
        case 'path':
            $input = array();
            if (!empty($_SERVER['PATH_INFO'])) {
                $depr = C('URL_PATHINFO_DEPR');
                $input = explode($depr, trim($_SERVER['PATH_INFO'], $depr));
            }
            break;
        case 'r':
        case 'request':
            $input = &$_REQUEST;
            break;
        case 's':
        case 'session':
            $input = &$_SESSION;
            break;
        case 'c':
        case 'cookie':
            $input = &$_COOKIE;
            break;
        case 'server':
            $input = &$_SERVER;
            break;
        case 'globals':
            $input = $GLOBALS;
            break;
        case 'data':
            $input = &$datas;
            break;
        default:
            return null;
    }
    if ('' == $name) {
        // 获取全部变量
        $data = $input;
        $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
        if ($filters) {
            if (is_string($filters)) {
                $filters = explode(',', $filters);
            }
            foreach ($filters as $filter) {
                $data = array_map_recursive($filter, $data); // 参数过滤
            }
        }
    } elseif (isset($input[$name])) {
        // 取值操作
        $data = $input[$name];
        $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
        if ($filters) {
            if (is_string($filters)) {
                if (0 === strpos($filters, '/')) {
                    if (1 !== preg_match($filters, (string)$data)) {
                        // 支持正则验证
                        return isset($default) ? $default : null;
                    }
                } else {
                    $filters = explode(',', $filters);
                }
            } elseif (is_int($filters)) {
                $filters = array($filters);
            }

            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    $filter = trim($filter);
                    if (function_exists($filter)) {
                        $data = is_array($data) ? array_map_recursive($filter, $data) : $filter($data); // 参数过滤
                    } else {
                        $data = filter_var($data, is_int($filter) ? $filter : filter_id($filter));
                        if (false === $data) {
                            return isset($default) ? $default : null;
                        }
                    }
                }
            }
        }
        if (!empty($type)) {
            switch (strtolower($type)) {
                case 'a':    // 数组
                    $data = (array)$data;
                    break;
                case 'd':    // 数字
                    $data = (int)$data;
                    break;
                case 'f':    // 浮点
                    $data = (float)$data;
                    break;
                case 'b':    // 布尔
                    $data = (bool)$data;
                    break;
                case 's': // 字符串
                default:
                    $data = (string)$data;
            }
        }
    } else {
        // 变量默认值
        $data = isset($default) ? $default : null;
    }
    is_array($data) && array_walk_recursive($data, 'think_filter');
    return $data;
}

function array_map_recursive($filter, $data)
{
    $result = array();
    foreach ($data as $key => $val) {
        $result[$key] = is_array($val)
            ? array_map_recursive($filter, $val)
            : call_user_func($filter, $val);
    }
    return $result;
}

/**
 * 设置和获取统计数据
 * 使用方法:
 * <code>
 * N('db',1); // 记录数据库操作次数
 * N('read',1); // 记录读取次数
 * echo N('db'); // 获取当前页面数据库的所有操作次数
 * echo N('read'); // 获取当前页面读取次数
 * </code>
 *
 * @param string  $key  标识位置
 * @param integer $step 步进值
 * @param boolean $save 是否保存结果
 *
 * @return mixed
 * @throws \Think\Exception
 */
function N($key, $step = 0, $save = false)
{
    static $_num = array();
    if (!isset($_num[$key])) {
        $_num[$key] = (false !== $save) ? S('N_' . $key) : 0;
    }
    if (empty($step)) {
        return $_num[$key];
    } else {
        $_num[$key] = $_num[$key] + (int)$step;
    }
    if (false !== $save) {
        // 保存结果
        S('N_' . $key, $_num[$key], $save);
    }
    return null;
}

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 *
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function parse_name($name, $type = 0)
{
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

/**
 * 优化的require_once
 *
 * @param string $filename 文件地址
 * @return boolean
 */
function require_cache($filename)
{
    static $_importFiles = array();
    if (!isset($_importFiles[$filename])) {
        if (file_exists_case($filename)) {
            require $filename;
            $_importFiles[$filename] = true;
        } else {
            $_importFiles[$filename] = false;
        }
    }
    return $_importFiles[$filename];
}

/**
 * 区分大小写的文件存在判断
 *
 * @param string $filename 文件地址
 * @return boolean
 */
function file_exists_case($filename)
{
    if (is_file($filename)) {
        if (IS_WIN && APP_DEBUG) {
            if (basename(realpath($filename)) != basename($filename)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

/**
 * 导入所需的类库 同java的Import 本函数有缓存功能
 *
 * @param string $class 类库命名空间字符串
 * @param string $baseUrl 起始路径
 * @param string $ext 导入的文件扩展名
 * @return boolean
 */
function import($class, $baseUrl = '', $ext = '.php')
{
    static $_file = array();
    $class = str_replace(array('.', '#'), array('/', '.'), $class);
    if (isset($_file[$class . $baseUrl])) {
        return true;
    } else {
        $_file[$class . $baseUrl] = true;
    }

    $class_strut = explode('/', $class);
    if (empty($baseUrl)) {
        if ('@' == $class_strut[0] || MODULE_NAME == $class_strut[0]) {
            //加载当前模块的类库
            $baseUrl = MODULE_PATH;
            $class = substr_replace($class, '', 0, strlen($class_strut[0]) + 1);
        } elseif ('Common' == $class_strut[0]) {
            //加载公共模块的类库
            $baseUrl = COMMON_PATH;
            $class = substr($class, 7);
        } else {
            // 加载其他模块的类库
            $baseUrl = APP_PATH;
        }
    }
    if (substr($baseUrl, -1) != '/') {
        $baseUrl .= '/';
    }

    $classfile = $baseUrl . $class . $ext;
    if (!class_exists(basename($class), false)) {
        // 如果类不存在 则导入类库文件
        return require_cache($classfile);
    }
    return null;
}

/**
 * 基于命名空间方式导入函数库
 * load('@.Util.Array')
 *
 * @param string $name 函数库命名空间字符串
 * @param string $baseUrl 起始路径
 * @param string $ext 导入的文件扩展名
 * @return void
 */
function load($name, $baseUrl = '', $ext = '.php')
{
    $name = str_replace(array('.', '#'), array('/', '.'), $name);
    if (empty($baseUrl)) {
        if (0 === strpos($name, '@/')) {
            //加载当前模块函数库
            $baseUrl = MODULE_PATH . 'Common/';
            $name = substr($name, 2);
        } else {
            //加载其他模块函数库
            $array = explode('/', $name);
            $baseUrl = APP_PATH . array_shift($array) . '/Common/';
            $name = implode('/', $array);
        }
    }
    if (substr($baseUrl, -1) != '/') {
        $baseUrl .= '/';
    }

    require_cache($baseUrl . $name . $ext);
}

/**
 * 实例化模型类 格式 [资源://][模块/]模型
 *
 * @param string $name  资源地址
 * @param string $layer 模型层名称
 *
 * @return Think\Model
 * @throws \Think\Exception\DbException
 */
function D($name = '', $layer = '')
{
    if (empty($name)) {
        return new Think\Model();
    }

    static $_model = array();
    $layer = $layer ?: C('DEFAULT_M_LAYER');
    if (isset($_model[$name . $layer])) {
        return $_model[$name . $layer];
    }

    $class = parse_res_name($name, $layer);
    if (class_exists($class)) {
        $model = new $class(basename($name));
    } elseif (false === strpos($name, '/')) {
        // 自动加载公共模块下面的模型
        $class = '\\Common\\' . $layer . '\\' . $name . $layer;
        $model = class_exists($class) ? new $class($name) : new Think\Model($name);
    } else {
        $model = new Think\Model(basename($name));
    }
    $_model[$name . $layer] = $model;
    return $model;
}

/**
 * 实例化一个没有模型文件的Model
 *
 * @param string $name Model名称 支持指定基础模型 例如 MongoModel:User
 * @param string $tablePrefix 表前缀
 * @param mixed $connection 数据库连接信息
 * @return Think\Model
 */
function M($name = '', $tablePrefix = '', $connection = '')
{
    static $_model = array();
    if (strpos($name, ':')) {
        list($class, $name) = explode(':', $name);
    } else {
        $class = 'Think\\Model';
    }
    $guid = (is_array($connection) ? implode('', $connection) : $connection) . $tablePrefix . $name . '_' . $class;
    if (!isset($_model[$guid])) {
        $_model[$guid] = new $class($name, $tablePrefix, $connection);
    }

    return $_model[$guid];
}

/**
 * 解析资源地址并导入类库文件
 * 例如 module/controller addon://module/behavior
 *
 * @param string $name 资源地址 格式：[扩展://][模块/]资源名
 * @param string $layer 分层名称
 * @param integer $level 控制器层次
 * @return string
 */
function parse_res_name($name, $layer, $level = 1)
{
    if (strpos($name, '://')) {
        // 指定扩展资源
        list($extend, $name) = explode('://', $name);
    } else {
        $extend = '';
    }
    if (strpos($name, '/') && substr_count($name, '/') >= $level) {
        // 指定模块
        list($module, $name) = explode('/', $name, 2);
    } else {
        $module = defined('MODULE_NAME') ? MODULE_NAME : '';
    }
    $array = explode('/', $name);
    $class = $module . '\\' . $layer;
    foreach ($array as $name) {
        $class .= '\\' . parse_name($name, 1);
    }
    // 导入资源类库
    if ($extend) {
        // 扩展资源
        $class = $extend . '\\' . $class;
    }
    return $class . $layer;
}

/**
 * 用于实例化访问控制器
 *
 * @param string $name 控制器名
 * @param string $path 控制器命名空间（路径）
 * @return Think\Controller|false
 */
function controller($name, $path = '')
{
    $layer = C('DEFAULT_C_LAYER');
    $class = ($path ? basename(ADDON_PATH) . '\\' . $path : MODULE_NAME) . '\\' . $layer;
    $array = explode('/', $name);
    foreach ($array as $name) {
        $class .= '\\' . parse_name($name, 1);
    }
    $class .= C('CONTROLLER_SUFFIX');
    if (class_exists($class)) {
        return new $class();
    } else {
        return false;
    }
}

/**
 * 实例化多层控制器 格式：[资源://][模块/]控制器
 *
 * @param string $name 资源地址
 * @param string $layer 控制层名称
 * @param integer $level 控制器层次
 * @return Think\Controller|false
 */
function A($name, $layer = '', $level = 0)
{
    static $_action = array();
    $layer = $layer ?: C('DEFAULT_C_LAYER');
    $level = $level ?: 1;
    if (isset($_action[$name . $layer])) {
        return $_action[$name . $layer];
    }

    $class = parse_res_name($name, $layer, $level);
    if (class_exists($class)) {
        $action = new $class();
        $_action[$name . $layer] = $action;
        return $action;
    } else {
        return false;
    }
}

/**
 * 远程调用控制器的操作方法 URL 参数格式 [资源://][模块/]控制器/操作
 *
 * @param string $url 调用地址
 * @param string|array $vars 调用参数 支持字符串和数组
 * @param string $layer 要调用的控制层名称
 * @return mixed
 */
function R($url, $vars = array(), $layer = '')
{
    $info = pathinfo($url);
    $action = $info['basename'];
    $module = $info['dirname'];
    $class = A($module, $layer);
    if ($class) {
        if (is_string($vars)) {
            parse_str($vars, $vars);
        }
        return call_user_func_array(array(&$class, $action . C('ACTION_SUFFIX')), $vars);
    } else {
        return false;
    }
}

/**
 * 处理钩子
 *
 * @param string $hook 钩子名称
 * @param mixed $params 传入参数
 * @return void
 */
function hook($hook, $params = null)
{
    return \Think\Hook::listen($hook, $params);
}

/**
 * 执行某个行为
 *
 * @param string $name 行为名称
 * @param string $tag 标签名称（行为类无需传入）
 * @param Mixed $params 传入的参数
 * @return mixed
 */
function B($name, $tag = '', &$params = null)
{
    if ('' == $tag) {
        $name .= 'Behavior';
    }
    return \Think\Hook::exec($name, $tag, $params);
}

/**
 * 去除代码中的空白和注释
 * @param string $content 代码内容
 * @return string
 */
function strip_whitespace($content)
{
    $stripStr = '';
    //分析php源码
    $tokens = token_get_all($content);
    $last_space = false;
    for ($i = 0, $j = count($tokens); $i < $j; $i++) {
        if (is_string($tokens[$i])) {
            $last_space = false;
            $stripStr .= $tokens[$i];
        } else {
            switch ($tokens[$i][0]) {
                //过滤各种PHP注释
                case T_COMMENT:
                case T_DOC_COMMENT:
                    break;
                    //过滤空格
                case T_WHITESPACE:
                    if (!$last_space) {
                        $stripStr .= ' ';
                        $last_space = true;
                    }
                    break;
                case T_START_HEREDOC:
                    $stripStr .= "<<<THINK\n";
                    break;
                case T_END_HEREDOC:
                    $stripStr .= "THINK;\n";
                    for ($k = $i + 1; $k < $j; $k++) {
                        if (is_string($tokens[$k]) && ';' == $tokens[$k]) {
                            $i = $k;
                            break;
                        } elseif (T_CLOSE_TAG == $tokens[$k][0]) {
                            break;
                        }
                    }
                    break;
                default:
                    $last_space = false;
                    $stripStr .= $tokens[$i][1];
            }
        }
    }
    return $stripStr;
}

/**
 * 浏览器友好的变量输出
 *
 * @param mixed $var 变量
 * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
 * @param string $label 标签 默认为空
 * @param boolean $strict 是否严谨 默认为true
 *
 * @return bool|string|null
 */
function dump($var, $echo = true, $label = null, $strict = true)
{
    //YAR中不能有信息输出，这里屏蔽一下。
    if (IS_YAR) {
        return false;
    }
    $label = (null === $label) ? '' : rtrim($label) . ' ';
    if (!$strict) {
        if (ini_get('html_errors')) {
            $output = print_r($var, true);
            if (!IS_CLI) {
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        } else {
            $output = $label . print_r($var, true);
        }
    } else {
        if (IS_CLI) {
            $output = var_export($var, true) . PHP_EOL;
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
                $output = '<div style=\'font-size:12px;line-height:14px;text-align:left;color:#000;background-color:#fff;\'>' .
                    '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>' .
                    '</div>';
            }
        }
    }
    if ($echo) {
        echo($output);
        return null;
    } else {
        return $output;
    }
}


/**
 * 设置当前页面的布局
 *
 * @param string|false $layout 布局名称 为false的时候表示关闭布局
 * @return void
 */
function layout($layout)
{
    if (false !== $layout) {
        // 开启布局
        C('LAYOUT_ON', true);
        if (is_string($layout)) {
            // 设置新的布局模板
            C('LAYOUT_NAME', $layout);
        }
    } else {
        // 临时关闭布局
        C('LAYOUT_ON', false);
    }
}

/**
 * URL组装 支持不同URL模式
 * @param string $url URL表达式，格式：'[模块/控制器/操作#锚点@域名]?参数1=值1&参数2=值2...'
 * @param string|array $vars 传入的参数，支持数组和字符串
 * @param string|boolean $suffix 伪静态后缀，默认为true表示获取配置值
 * @param boolean $domain 是否显示域名
 * @return string
 */
function U($url = '', $vars = '', $suffix = true, $domain = false)
{
    return \Think\Route::buildUrl($url, $vars, $suffix, $domain);
}

/**
 * 渲染输出Widget
 *
 * @param string $name Widget名称
 * @param array $data 传入的参数
 * @return void
 */
function W($name, $data = array())
{
    return R($name, $data, 'Widget');
}

/**
 * 判断是否SSL协议
 *
 * @return boolean
 */
function is_ssl()
{
    if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
        return true;
    } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    }
    return false;
}

/**
 * URL重定向
 *
 * @param string $url 重定向的URL地址
 * @param integer $time 重定向的等待时间（秒）
 * @param string $msg 重定向前的提示信息
 * @return void
 */
function redirect($url, $time = 0, $msg = '')
{
    //多行URL地址支持
    $url = str_replace(array("\n", "\r"), '', $url);
    if (empty($msg)) {
        $msg = "系统将在{$time}秒之后自动跳转到{$url}！";
    }

    if (!headers_sent()) {
        // redirect
        if (0 === $time) {
            header('Location: ' . $url);
        } else {
            header('refresh:' . $time . ';url=' . $url);
            echo($msg);
        }
        exit();
    } else {
        $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
        if (0 != $time) {
            $str .= $msg;
        }

        exit($str);
    }
}

/**
 * 缓存管理
 *
 * @param mixed $name    缓存名称，如果为数组表示进行缓存设置
 * @param mixed $value   缓存值
 * @param mixed $options 缓存参数
 * @return mixed
 * @throws \Think\Exception
 */
function S($name, $value = '', $options = null)
{
    static $cache = '';
    if (is_array($options)) {
        // 缓存操作的同时初始化
        $type = isset($options['type']) ? $options['type'] : '';
        $cache = Think\Cache::getInstance($type, $options);
    } elseif (is_array($name)) {
        // 缓存初始化
        $type = isset($name['type']) ? $name['type'] : '';
        $cache = Think\Cache::getInstance($type, $name);
        return $cache;
    } elseif (empty($cache)) {
        // 自动初始化
        $cache = Think\Cache::getInstance();
    }
    if ('' === $value) {
        // 获取缓存
        return $cache->get($name);
    } elseif (is_null($value)) {
        // 删除缓存
        return $cache->rm($name);
    } else {
        // 缓存数据
        if (is_array($options)) {
            $expire = isset($options['expire']) ? $options['expire'] : null;
        } else {
            $expire = is_numeric($options) ? $options : null;
        }
        return $cache->set($name, $value, $expire);
    }
}

/**
 * 快速文件数据读取和保存 针对简单类型数据 字符串、数组
 *
 * @param string $name 缓存名称
 * @param mixed $value 缓存值
 * @param string $path 缓存路径
 * @return mixed
 */
function F($name, $value = '', $path = DATA_PATH)
{
    static $_cache = array();

    // 安全修复：验证缓存名称格式，防止路径遍历
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name)) {
        error_log("Security Warning: Invalid cache name in F(): {$name}");
        return false;
    }

    $filename = $path . $name . '.php';
    if ('' !== $value) {
        if (is_null($value)) {
            // 删除缓存
            if (false !== strpos($name, '*')) {
                return false; // TODO
            } else {
                unset($_cache[$name]);
                return Think\Storage::unlink($filename, 'F');
            }
        } else {
            // 安全修复：使用 JSON 编码替代 serialize，防止对象注入
            $jsonData = json_encode($value, JSON_UNESCAPED_UNICODE);
            // 计算 HMAC 签名以防止数据篡改
            $hmacKey = C('DATA_CACHE_KEY') ?: 'default_cache_key';
            $signature = hash_hmac('sha256', $jsonData, $hmacKey);
            $dataToStore = $signature . '|' . $jsonData;
            Think\Storage::put($filename, $dataToStore, 'F');
            // 缓存数据
            $_cache[$name] = $value;
            return null;
        }
    }
    // 获取缓存数据
    if (isset($_cache[$name])) {
        return $_cache[$name];
    }

    if (Think\Storage::has($filename, 'F')) {
        $rawData = Think\Storage::read($filename, 'F');

        // 安全修复：验证签名并解析 JSON 数据
        $hmacKey = C('DATA_CACHE_KEY') ?: 'default_cache_key';
        $separatorPos = strpos($rawData, '|');

        if ($separatorPos === false) {
            // 旧格式数据，尝试 unserialize（向后兼容，但不推荐）
            error_log("Security Warning: Legacy cache format detected for {$name}, please regenerate");
            $value = @unserialize($rawData);
            if ($value === false && $rawData !== 'b:0;') {
                return false;
            }
        } else {
            $signature = substr($rawData, 0, $separatorPos);
            $jsonData = substr($rawData, $separatorPos + 1);

            // 验证 HMAC 签名
            $expectedSignature = hash_hmac('sha256', $jsonData, $hmacKey);
            if (!hash_equals($signature, $expectedSignature)) {
                error_log("Security Warning: Cache data signature verification failed for {$name}");
                return false;
            }

            // 解析 JSON 数据
            $value = json_decode($jsonData, true);

            // 检查 JSON 解析错误
            if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log("Security Warning: JSON decode failed for cache {$name}: " . json_last_error_msg());
                return false;
            }
        }

        $_cache[$name] = $value;
    } else {
        $value = false;
    }
    return $value;
}

/**
 * 根据PHP各种类型变量生成唯一标识号
 *
 * @param mixed $mix 变量
 * @return string
 */
function to_guid_string($mix)
{
    if (is_object($mix)) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

/**
 * XML编码
 *
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id 数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8')
{
    if (is_array($attr)) {
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr = trim($attr);
    $attr = empty($attr) ? '' : " {$attr}";
    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml .= "<{$root}{$attr}>";
    $xml .= data_to_xml($data, $item, $id);
    $xml .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 *
 * @param mixed $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id 数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item = 'item', $id = 'id')
{
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if (is_numeric($key)) {
            $id && $attr = " {$id}=\"{$key}\"";
            $key = $item;
        }
        $xml .= "<{$key}{$attr}>";
        $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml .= "</{$key}>";
    }
    return $xml;
}

/**
 * session管理函数
 *
 * @param string|array $name session名称 如果为数组则表示进行session设置
 * @param mixed $value session值
 * @return mixed
 */
function session($name = '', $value = '')
{
    $prefix = C('SESSION_PREFIX');
    if (is_array($name)) {
        // session初始化 在session_start 之前调用
        if (isset($name['prefix'])) {
            C('SESSION_PREFIX', $name['prefix']);
        }

        if (C('VAR_SESSION_ID') && isset($_REQUEST[C('VAR_SESSION_ID')])) {
            // 安全修复：禁用从请求参数设置 session ID，防止 Session Fixation 攻击
            // 如果确实需要此功能，请确保在受信任的环境中使用并验证 session ID 格式
            if (C('SESSION_ALLOW_REQUEST_ID') !== true) {
                error_log("Security Warning: Attempted to set session ID from request parameter. This is disabled by default to prevent Session Fixation attacks.");
            } else {
                $requestedId = $_REQUEST[C('VAR_SESSION_ID')];
                // 验证 session ID 格式（仅允许字母数字）
                if (preg_match('/^[a-zA-Z0-9,-]+$/', $requestedId)) {
                    session_id($requestedId);
                } else {
                    error_log("Security Warning: Invalid session ID format in request: {$requestedId}");
                }
            }
        } elseif (isset($name['id'])) {
            session_id($name['id']);
        }
        if (IS_CLI) {
            ini_set('session.auto_start', 0);
        }
        if (isset($name['name'])) {
            session_name($name['name']);
        }

        if (isset($name['path'])) {
            session_save_path($name['path']);
        }

        if (isset($name['domain'])) {
            ini_set('session.cookie_domain', $name['domain']);
        }

        if (isset($name['expire'])) {
            ini_set('session.gc_maxlifetime', $name['expire']);
            ini_set('session.cookie_lifetime', $name['expire']);
        }
        if (isset($name['use_trans_sid'])) {
            ini_set('session.use_trans_sid', $name['use_trans_sid'] ? 1 : 0);
        }

        if (isset($name['use_cookies'])) {
            ini_set('session.use_cookies', $name['use_cookies'] ? 1 : 0);
        }

        if (isset($name['cache_limiter'])) {
            session_cache_limiter($name['cache_limiter']);
        }

        if (isset($name['cache_expire'])) {
            session_cache_expire($name['cache_expire']);
        }

        if (isset($name['type'])) {
            C('SESSION_TYPE', $name['type']);
        }

        if (C('SESSION_TYPE')) {
            // 读取session驱动
            $type   = C('SESSION_TYPE');
            $class  = strpos($type, '\\') ? $type : 'Think\\Driver\\Session\\' . ucwords(strtolower($type));
            $handler = new $class();
            session_set_save_handler(
                array(&$handler, "open"),
                array(&$handler, "close"),
                array(&$handler, "read"),
                array(&$handler, "write"),
                array(&$handler, "destroy"),
                array(&$handler, "gc")
            );
        }
        // 启动session
        if (C('SESSION_AUTO_START')) {
            session_start();

            // 安全修复：Session Fixation 防护
            // 如果启用自动重新生成 session ID，在 session 启动后立即重新生成
            if (C('SESSION_AUTO_REGENERATE') && !isset($_SESSION['_session_regenerated'])) {
                session_regenerate_id(true);
                $_SESSION['_session_regenerated'] = true;
            }

            // 验证 session ID 格式
            $sessionId = session_id();
            if (!empty($sessionId) && !preg_match('/^[a-zA-Z0-9,-]+$/', $sessionId)) {
                error_log("Security Warning: Invalid session ID detected: {$sessionId}");
                session_destroy();
                // 生成新的安全 session ID
                session_start();
                session_regenerate_id(true);
            }
        }
    } elseif ('' === $value) {
        if ('' === $name) {
            // 获取全部的session
            return $prefix ? $_SESSION[$prefix] : $_SESSION;
        } elseif (0 === strpos($name, '[')) {
            // session 操作
            if ('[pause]' == $name) {
                // 暂停session
                session_write_close();
            } elseif ('[start]' == $name) {
                // 启动session
                session_start();
            } elseif ('[destroy]' == $name) {
                // 销毁session
                $_SESSION = array();
                session_unset();
                session_destroy();
            } elseif ('[regenerate]' == $name) {
                // 重新生成id
                session_regenerate_id();
            }
        } elseif (0 === strpos($name, '?')) {
            // 检查session
            $name = substr($name, 1);
            if (strpos($name, '.')) {
                // 支持数组
                list($name1, $name2) = explode('.', $name);
                return $prefix ? isset($_SESSION[$prefix][$name1][$name2]) : isset($_SESSION[$name1][$name2]);
            } else {
                return $prefix ? isset($_SESSION[$prefix][$name]) : isset($_SESSION[$name]);
            }
        } elseif (is_null($name)) {
            // 清空session
            if ($prefix) {
                unset($_SESSION[$prefix]);
            } else {
                $_SESSION = array();
            }
        } elseif ($prefix) {
            // 获取session
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                return isset($_SESSION[$prefix][$name1][$name2]) ? $_SESSION[$prefix][$name1][$name2] : null;
            } else {
                return isset($_SESSION[$prefix][$name]) ? $_SESSION[$prefix][$name] : null;
            }
        } else {
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                return isset($_SESSION[$name1][$name2]) ? $_SESSION[$name1][$name2] : null;
            } else {
                return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
            }
        }
    } elseif (is_null($value)) {
        // 删除session
        if (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            if ($prefix) {
                unset($_SESSION[$prefix][$name1][$name2]);
            } else {
                unset($_SESSION[$name1][$name2]);
            }
        } else {
            if ($prefix) {
                unset($_SESSION[$prefix][$name]);
            } else {
                unset($_SESSION[$name]);
            }
        }
    } else {
        // 设置session
        if (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            if ($prefix) {
                $_SESSION[$prefix][$name1][$name2] = $value;
            } else {
                $_SESSION[$name1][$name2] = $value;
            }
        } else {
            if ($prefix) {
                $_SESSION[$prefix][$name] = $value;
            } else {
                $_SESSION[$name] = $value;
            }
        }
    }
    return null;
}

/**
 * Cookie 设置、获取、删除
 *
 * @param string $name cookie名称
 * @param mixed $value cookie值
 * @param mixed $option cookie参数
 * @return mixed
 */
function cookie($name = '', $value = '', $option = null)
{
    // 默认设置
    $config = array(
        'prefix' => C('COOKIE_PREFIX'), // cookie 名称前缀
        'expire' => C('COOKIE_EXPIRE'), // cookie 保存时间
        'path' => C('COOKIE_PATH'), // cookie 保存路径
        'domain' => C('COOKIE_DOMAIN'), // cookie 有效域名
        'secure' => C('COOKIE_SECURE'), //  cookie 启用安全传输
        'httponly' => C('COOKIE_HTTPONLY'), // httponly设置
    );
    // 参数设置(会覆盖黙认设置)
    if (!is_null($option)) {
        if (is_numeric($option)) {
            $option = array('expire' => $option);
        } elseif (is_string($option)) {
            parse_str($option, $option);
        }

        $config = array_merge($config, array_change_key_case($option));
    }
    if (!empty($config['httponly'])) {
        ini_set("session.cookie_httponly", 1);
    }
    // 清除指定前缀的所有cookie
    if (is_null($name)) {
        if (empty($_COOKIE)) {
            return null;
        }

        // 要删除的cookie前缀，不指定则删除config设置的指定前缀
        $prefix = empty($value) ? $config['prefix'] : $value;
        if (!empty($prefix)) {
            // 如果前缀为空字符串将不作处理直接返回
            foreach ($_COOKIE as $key => $val) {
                if (0 === stripos($key, $prefix)) {
                    setcookie($key, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                    unset($_COOKIE[$key]);
                }
            }
        }
        return null;
    } elseif ('' === $name) {
        // 获取全部的cookie
        return $_COOKIE;
    }
    $name = $config['prefix'] . str_replace('.', '_', $name);
    if ('' === $value) {
        if (isset($_COOKIE[$name])) {
            $value = $_COOKIE[$name];
            if (0 === strpos($value, 'think:')) {
                $value = substr($value, 6);
                return array_map('urldecode', json_decode($value, true));
            } else {
                return $value;
            }
        } else {
            return null;
        }
    } else {
        if (is_null($value)) {
            setcookie($name, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {
            // 设置cookie
            if (is_array($value)) {
                $value = 'think:' . json_encode(array_map('urlencode', $value));
            }
            $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
            setcookie($name, $value, $expire, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            $_COOKIE[$name] = $value;
        }
    }
    return null;
}

/**
 * 加载动态扩展文件
 *
 * @param string $path 文件路径
 *
 * @return void
 * @throws \Think\Exception
 */
function load_ext_file($path)
{
    // 加载自定义外部文件
    if ($files = C('LOAD_EXT_FILE')) {
        $files = explode(',', $files);
        foreach ($files as $file) {
            $file = $path . 'Common/' . $file . '.php';
            if (is_file($file)) {
                include $file;
            }
        }
    }
    // 加载自定义的动态配置文件
    if ($configs = C('LOAD_EXT_CONFIG')) {
        if (is_string($configs)) {
            $configs = explode(',', $configs);
        }

        foreach ($configs as $key => $config) {
            $file = is_file($config) ? $config : $path . 'Conf/' . $config . '.php';
            if (is_file($file)) {
                is_numeric($key) ? C(load_Config($file)) : C($key, load_Config($file));
            }
        }
    }
}

/**
 * 获取客户端IP地址
 *
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0, $adv = false)
{
    $type = $type ? 1 : 0;
    static $ip = null;
    if (null !== $ip) {
        return $ip[$type];
    }

    if ($adv) {
        if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
            $ip = $_SERVER['HTTP_CDN_SRC_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_REAL_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }

            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 发送HTTP状态
 *
 * @param integer $code 状态码
 * @return void
 */
function send_http_status($code)
{
    static $_status = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily ',  // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );
    if (isset($_status[$code])) {
        header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
        // 确保FastCGI模式下正常
        header('Status:' . $code . ' ' . $_status[$code]);
    }
}

function think_filter(&$value)
{
    // TODO 其他安全过滤
    if (!$value) {
        return;
    }
    // 过滤查询特殊字符
    if (preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOTIN|NOT IN|IN|BIND)$/i', $value)) {
        $value .= ' ';
    }
}

// 不区分大小写的in_array实现
function in_array_case($value, $array)
{
    return in_array(strtolower($value), array_map('strtolower', $array));
}

/**
 * 显示任务进度
 * 批量处理任务时对执行时间进行预估并提示
 * @param int $total 本次要处理的数据总数
 * @param int $start 本次开始处理的时间戳
 * @param int $processed 当前处理掉的条目数
 * @param string $msg
 * @param int $begin 开始计时的数量
 */
function showTimeTask($total, $start, $processed, $msg = '', $begin = 0)
{
    static $_tasktime;
    $time = time() - $start;
    if (!$_tasktime) {
        $_tasktime = $time;
    }
    if ($_tasktime == $time) {
        return '';
    }
    $left = $total - $processed;
    if ($msg) {
        $msg .= ' ';
    }
    $len = strlen($total);
    $msg .= sprintf('共%' . $len . 'd条，已处理%' . $len . 'd条，还剩%' . $len . 'd条', $total, $processed, $left);

    if ($processed > $begin && $time > 0 && $processed < $total) {
        if ($processed >= $time) {
            $speed = ceil($processed / $time);
            $need = ceil($left / $speed);
            $unit = '条/秒';
        } else {
            $speed = ceil($time / $processed);
            $need = $left * $speed;
            $unit = '秒/条';
        }
        $msg .= '，耗时' . formatTimeToStr($time) . '，速度' . $speed . $unit . '，还需要：';
        $msg .= formatTimeToStr($need);
    }
    return $msg;
}

/**
 * 格式化时间
 * @param $need
 * @return string
 */
function formatTimeToStr($need)
{
    static $chunks = [
        array(31536000, '年'),
        array(2592000, '个月'),
        array(86400, '天'),
        array(3600, '小时'),
        array(60, '分钟'),
        array(1, '秒')
    ];
    $since         = '';
    for ($i = 0; $i < count($chunks); $i++) {
        if ($need >= $chunks[$i][0]) {
            $num = floor($need / $chunks[$i][0]);
            $since .= sprintf('%d' . $chunks[$i][1], $num);
            $need = (int) ($need - $chunks[$i][0] * $num);
        }
    }
    if (mb_substr($since, -1, 1) !== '钟') {
        $since = str_replace('钟', '', $since);
    }
    return $since;
}

/**
 * @param Exception $e
 * @return array
 */
function processTrace($e)
{
    $result = [];
    if (!$e instanceof Exception) {
        return $result;
    }
    $trace = $e->getTrace();
    $keys = ['file', 'line', 'function', 'class', 'type'];
    $first = [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'function' => '',
        'class' => '',
        'type' => ''
    ];
    if (method_exists($e, 'getfunc')) {
        $first['function'] = call_user_func([$e, 'getFunc']);
    }
    array_unshift($trace, $first);
    for ($i = 0, $count = count($trace); $i < $count; ++$i) {
        // $tmp = [];
        $tmp = $trace[$i];
        foreach($keys as $key) {
            if(!isset($tmp[$key])) $tmp[$key] = '';
        }
        $tmp['func'] = $tmp['class'] . $tmp['type'] . $tmp['function'];
        if ($tmp['file'] && (!APP_DEBUG || !IS_CLI)) {
        //    continue;
        }
        if ($tmp) {
            $result[] = $tmp;
        }
    }
    return $result;
}


/**
 * 获取环境变量值
 *
 * @access public
 *
 * @param string $name          环境变量名（支持二级 .号分割）
 * @param bool   $default_value 默认值
 *
 * @return mixed
 * @throws Exception
 */
function env($name, $default_value = false)
{
    static $_ENV;
    if (!$_ENV) {
        $envfile = ROOT_PATH . '/.env';
        if (file_exists($envfile)) {
            $obj = new \Think\Env();
            $obj->load($envfile);
            $_ENV = $obj->get();
        } else {
            throw new Exception($envfile . ' is not found');
        }
    }
    if (isset($_ENV[$name])) {
        return $_ENV[$name];
    }
    return getenv($name) ? getenv($name) : $default_value;
}

/**
 * 加密解密函数
 *
 * @param string $string 需要加密/解密的数据
 * @param string $operation 操作类型，DECODE表示解密，ENCODE表示加密
 * @param string $key 密钥
 * @param int $expiry 过期时间
 * @return string 加密/解密后的数据
 */
function think_authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
{
    $ckey_length = 4; //note 随机密钥长度 取值 0-32;
    //note 加入随机密钥，可以令密文无任何规律，即便是原文和密钥完全相同，加密结果也会每次不同，增大破解难度。
    //note 取值越大，密文变动规律越大，密文变化 = 16 的 $ckey_length 次方
    //note 当此值为 0 时，则不产生随机密钥

    $key  = md5($key); //  ? $key : UC_KEY
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey   = $keya . md5($keya . $keyc);
    $key_length = strlen($cryptkey);

    $string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    $string_length = strlen($string);

    $result = '';
    $box    = range(0, 255);

    $rndkey = array();
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp     = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a       = ($a + 1) % 256;
        $j       = ($j + $box[$a]) % 256;
        $tmp     = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation == 'DECODE') {
        if ((
            substr($result, 0, 10) == 0                                 //未设置失效时间
            ||
            substr($result, 0, 10) - time() > 0                         //尚未失效
            ||
            $key == C('DATA_AUTH_KEY')
        ) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc . str_replace('=', '', base64_encode($result));
    }
}

/**
 * 检测是否邮箱
 * @param string $str
 * @return bool
 */
function isEmail($str)
{
    $pattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/";
    return preg_match($pattern, $str);
}
