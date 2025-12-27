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
 * ThinkPHP路由解析类
 */
class Route
{
    public const ROUTER_ARG_NAME     = 0;                //静态变量值，这个就当做是路由的关键标识了
    public const ROUTER_ARG_VARIABLE = 1;                //变量
    public const ROUTER_ARG_OPTION   = 2;                //可选变量

    public const ROUTER_CACHE_KEY = 'url_route_rules[MODULE_NAME]';

    /**
     * 验证重定向URL是否安全（仅允许相对路径，拒绝 CRLF 注入）
     *
     * @param string $url
     * @return bool
     */
    private static function isValidRedirectUrl($url)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        // CRLF 防护：避免 header 注入
        if (strpos($url, "\r") !== false || strpos($url, "\n") !== false) {
            return false;
        }

        // 仅允许以 / 开头的相对路径，拒绝 // 或 /\（协议相对或歧义路径）
        if (strpos($url, '/') !== 0) {
            return false;
        }
        if (strpos($url, '//') === 0) {
            return false;
        }
        if (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\')) {
            return false;
        }

        return true;
    }

    /**
     * 路由检测
     *
     * @param array $paths path_info数组
     *
     * @return boolean
     */
    public static function check($paths = array())
    {
        $rules = self::ruleCache();
        if (!empty($paths)) {
            $regx = implode('/', $paths);
        } else {
            $depr = C('URL_PATHINFO_DEPR');
            $regx = preg_replace('/\.' . __EXT__ . '$/i', '', trim($_SERVER['PATH_INFO'], $depr));
            if (!$regx) {
                return false;
            }
            // 分隔符替换 确保路由定义使用统一的分隔符
            if ('/' != $depr) {
                $regx = str_replace($depr, '/', $regx);
            }
        }
        // 静态路由检查
        if (isset($rules[0][$regx])) {
            $route                = $rules[0][$regx];
            $_SERVER['PATH_INFO'] = $route[0];
            $args                 = array_pop($route);
            if (!empty($route[1])) {
                $args = array_merge($args, $route[1]);
            }
            $_GET = array_merge($args, $_GET);
            return true;
        }
        // 动态路由检查
        if (!empty($rules[1])) {
            foreach ($rules[1] as $rule => $route) {
                $args = array_pop($route);
                if (isset($route[2])) {
                    // 路由参数检查
                    if (!self::checkOption($route[2], __EXT__)) {
                        continue;
                    }
                }
                $matches = self::checkUrlMatch($rule, $args, $regx);
                if ($matches !== false) {
                    if ($route[0] instanceof \Closure) {
                        // 执行闭包
                        $result = self::invoke($route[0], $matches);
                        // 如果返回布尔值 则继续执行
                        return is_bool($result) ? $result : exit;
                    } else {
                        // 存在动态变量
                        if (strpos($route[0], ':')) {
                            $matches  = array_values($matches);
                            $route[0] = preg_replace_callback(
                                '/:(\d+)/',
                                function ($match) use (&$matches) {
                                    return $matches[$match[1] - 1];
                                },
                                $route[0]
                            );
                        }
                        // 路由参数关联$matches
                        if ('/' == substr($rule, 0, 1)) {
                            $rule_params = array();
                            if (isset($route[1]) && is_array($route[1])) {
                                foreach ($route[1] as $param_key => $param) {
                                    list($param_name, $param_value) = explode('=', $param, 2);
                                    if (!is_null($param_value)) {
                                        if (preg_match('/^:(\d*)$/', $param_value, $match_index)) {
                                            $match_index = $match_index[1] - 1;
                                            $param_value = $matches[$match_index];
                                        }
                                        $rule_params[$param_name] = $param_value;
                                        unset($route[1][$param_key]);
                                    }
                                }
                            }
                            $route[1] = $rule_params;
                        }
                        // 重定向
                        if ('/' == substr($route[0], 0, 1)) {
                            if (!self::isValidRedirectUrl($route[0])) {
                                throw new \Exception('Invalid redirect URL');
                            }
                            header("Location: $route[0]", true, $route[1]);
                            exit;
                        } else {
                            $depr = C('URL_PATHINFO_DEPR');
                            if ('/' != $depr) {
                                $route[0] = str_replace('/', $depr, $route[0]);
                            }
                            //有可能已经判断过MODULE了
                            $info = explode($depr, $route[0]);
                            if (count($info) > 2) {
                                if (defined('MODULE_NAME') && $info[0] == MODULE_NAME) {
                                    array_shift($info);
                                    $route[0] = implode($depr, $info);
                                }
                            }
                            $_SERVER['PATH_INFO'] = $route[0];
                            if (!empty($route[1])) {
                                $_GET = array_merge($route[1], $_GET);
                            }
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * 路由反向解析
     *
     * @param string      $path   控制器/方法
     * @param array       $vars   url参数
     * @param string      $depr   分隔符
     * @param string|true $suffix url后缀
     *
     * @return array|false
     */
    /**
     * 路由反向解析
     *
     * @param string      $path   控制器/方法
     * @param array       $vars   url参数
     * @param string      $depr   分隔符
     * @param string|true $suffix url后缀
     *
     * @return array|false
     */
    public static function reverse($path, &$vars, $depr = '/', $suffix = true)
    {
        static $_rules = null;
        if (is_null($_rules)) {
            $_rules = self::buildReverseRules(self::ruleCache());
        }

        $info = self::parseReverseUrl($path);

        // 解析参数
        self::parseReverseParams($vars, $info);

        // 从路径中解析 模块、控制器、方法
        $path = isset($info['path']) ? ltrim($info['path'], '/') : '';
        $param = explode('/', $path);
        $urls  = self::processMCA($param);

        //检测路由映射表
        $rMap = C('ROUTER_MAP');
        $_url = implode('/', $urls);
        if ($rMap && isset($rMap[$_url])) {
            $_url = explode('/', $rMap[$_url]);
            $urls = self::processMCA($_url);
        }

        $info['MODULE']     = $urls[0];
        $info['CONTROLLER'] = $urls[1];
        $info['ACTION']     = $urls[2];
        $info['path']       = '';

        if (!defined('BIND_MODULE') || BIND_MODULE != $info['MODULE']) {
            $info['path'] = $info['MODULE'] . $depr;
        }

        $info['path'] .= $info['CONTROLLER'] . $depr . $info['ACTION'];
        $path         = strtolower(implode('/', $urls));
        unset($urls);

        if ($param) {
            for ($i = 0; $i < count($param); $i++) {
                $vars[$param[$i]] = $param[++$i];
            }
        }
        unset($param);

        // 静态路由
        if (isset($_rules[0][$path])) {
            foreach ($_rules[0][$path] as $rule => $route) {
                $args = array_pop($route);
                if (count($vars) == count($args) && !empty($vars) && !array_diff($vars, $args)) {
                    $info['path'] = str_replace('/', $depr, $rule);
                    return $info;
                }
            }
        }

        // 动态路由
        if (isset($_rules[1][$path])) {
            return self::matchReverseDynamic($_rules[1][$path], $vars, $info, $depr, $suffix);
        }

        //没有合适的路由
        return $info;
    }

    /**
     * 构建反向路由规则
     *
     * @param array $rules
     * @return array
     */
    private static function buildReverseRules(array $rules): array
    {
        $_rules = [];
        if ($rules) {
            foreach ($rules as $i => $rules2) {
                foreach ($rules2 as $rule => $route) {
                    if (is_array($route) && is_string($route[0]) && '/' != substr($route[0], 0, 1)) {
                        $key                     = strtolower($route[0]);
                        $key                     = preg_replace('@\\[a-z]@U', '', $key);
                        $_rules[$i][$key][$rule] = $route;
                    }
                }
            }
        }
        return $_rules;
    }

    /**
     * 解析反向路由URL
     *
     * @param string $path
     * @return array
     */
    private static function parseReverseUrl(string $path): array
    {
        $host = '';
        if (strpos($path, '@') !== false) {
            list($path, $host) = explode('@', $path, 2);
        }
        if ($host) {
            // 检测是否有 scheme，如果没有则补上
            if (false === strpos($host, '://')) {
                $host = (is_ssl() ? 'https://' : 'http://') . $host;
            }
            $host = trim($host, '/') . '/';
            $path = $host . $path;
            $host = '';
        }
        $info = parse_url($path);

        if (isset($info['host']) && $info['host'] && !isset($info['scheme'])) {
            $info['path'] = $info['host'] . $info['path'];
            unset($info['host']);
        }
        $query = $host = '';
        $url   = !empty($info['path']) ? $info['path'] : ACTION_NAME;
        if (isset($info['fragment'])) { // 解析锚点
            if (false !== strpos($info['fragment'], '@')) { // 解析域名
                list($info['fragment'], $host) = explode('@', $info['fragment'], 2);
            }
            if (false !== strpos($info['fragment'], '?')) { // 解析参数
                list($info['fragment'], $query) = explode('?', $info['fragment'], 2);
            }
        } elseif (false !== strpos($url, '@')) { // 解析域名
            list($url, $host) = explode('@', $info['path'], 2);
        }

        if ($query) {
            $info['query'] = $query . '&' . $info['query'];
        }

        // 解析子域名
        if ($host) {     //已经在请求中加入了域名
            if (strtoupper($host) === $host) {      //输入的是全大写字母则从配置文件中去查找
                $info['host'] = C($host);
            } else {
                $tmp  = parse_url($host);
                $info = array_merge($info, $tmp);
            }
        }
        return $info;
    }

    /**
     * 解析反向路由参数
     *
     * @param mixed $vars
     * @param array $info
     */
    private static function parseReverseParams(&$vars, array $info)
    {
        if (is_string($vars)) { // aaa=1&bbb=2 转换成数组
            parse_str($vars, $vars);
        } elseif (!is_array($vars)) {
            $vars = [];
        }
        if (isset($info['query']) && $info['query']) { // 解析地址里面参数 合并到vars
            parse_str($info['query'], $params);
            $vars = array_merge($params, $vars);
        }
    }

    /**
     * 匹配反向动态路由
     *
     * @param array $rules
     * @param array $vars
     * @param array $info
     * @param string $depr
     * @param mixed $suffix
     * @return array
     */
    private static function matchReverseDynamic(array $rules, array &$vars, array $info, string $depr, $suffix): array
    {
        foreach ($rules as $rule => $route) {
            $args  = array_pop($route);
            $array = [];
            if (isset($route[2])) {
                // 路由参数检查
                if (!self::checkOption($route[2], $suffix)) {
                    continue;
                }
            }
            if ('/' != substr($rule, 0, 1)) {
                // 规则路由
                $flag = true;
                foreach ($args as $key => $val) {
                    if ($val[0] == self::ROUTER_ARG_NAME) {
                        // 静态变量值，这个就当做是路由的关键标识了
                        $array[$key] = $key;
                        continue;
                    }
                    if (isset($vars[$key])) {
                        // 是否有过滤条件
                        if (!empty($val[2])) {
                            if ($val[2] == 'int') {
                                // 是否为数字
                                if (!is_numeric($vars[$key]) || !preg_match('/^\d*$/', $vars[$key])) {
                                    $flag = false;
                                    break;
                                }
                            } else {
                                // 排除的名称
                                if (in_array($vars[$key], $val[2])) {
                                    $flag = false;
                                    break;
                                }
                            }
                        }
                        $array[$key] = $vars[$key];
                    } elseif ($val[0] == self::ROUTER_ARG_VARIABLE) {
                        // 如果是必选项
                        $flag = false;
                        break;
                    }
                }
                // 匹配成功
                if ($flag) {
                    //TODO 暂时先这样处理一下
                    if (isset($route[1]) && is_array($route[1]) && $route[1]) {
                        foreach ($route[1] as $k => $v) {
                            if (isset($vars[$k]) && $vars[$k] == $v) {
                                unset($vars[$k]);
                            }
                        }
                    }
                    foreach (array_keys($array) as $key) {
                        $array[$key] = urlencode($array[$key]);
                        $info[$key]  = $array[$key];
                        unset($vars[$key]);
                    }
                    $info['path'] = implode($depr, $array);
                    return $info;
                }
            } else {
                // 正则路由
                $keys      = !empty($args) ? array_keys($args) : array_keys($vars);
                $temp_vars = $vars;
                $str       = preg_replace_callback('/\(.*?\)/', function ($match) use (&$temp_vars, &$keys) {
                    $k      = array_shift($keys);
                    $re_var = '';
                    if (isset($temp_vars[$k])) {
                        $re_var = $temp_vars[$k];
                        unset($temp_vars[$k]);
                    }
                    return urlencode($re_var);
                }, $rule);
                $str       = substr($str, 1, -1);
                $str       = rtrim(ltrim($str, '^'), '$');
                $str       = str_replace('\\', '', $str);
                if (preg_match($rule, $str, $matches)) {
                    // 匹配成功
                    $vars         = $temp_vars;
                    $info['path'] = str_replace('/', $depr, $str);
                    return $info;
                }
            }
        }
        return $info;
    }

    /**
     * 规则路由定义方法：
     *
     * 方式1：路由到内部地址（字符串）    '[控制器/操作]?额外参数1=值1&额外参数2=值2...'
     * 方式2：路由到内部地址（数组）参数采用字符串方式    array('[控制器/操作]','额外参数1=值1&额外参数2=值2...')
     * 方式3：路由到内部地址（数组）参数采用数组方式    array('[控制器/操作]',array('额外参数1'=>'值1','额外参数2'=>'值2'...)[,路由参数])
     * 方式4：路由到外部地址（字符串）301重定向    '外部地址'
     * 方式5：路由到外部地址（数组）可以指定重定向代码    array('外部地址','重定向代码'[,路由参数])
     * 方式6：闭包函数    function($name){ echo 'Hello,'.$name;}
     *
     * 路由规则中 :开头 表示动态变量
     * 外部地址中可以用动态变量 采用 :1 :2 的方式，比如：
     * 'news/:month/:day/:id'=>array('News/read?cate=1','status=1'),
     * 'new/:id'=>array('/new.php?id=:1',301), 重定向
     *
     * 正则路由定义方法：
     * '/new\/(\d+)\/(\d+)/'=>array('News/read?id=:1&page=:2&cate=1','status=1'),
     * '/new\/(\d+)/'=>array('/new.php?id=:1&page=:2&status=1','301'), 重定向
     */

    /**
     * 读取规则缓存
     *
     * @param boolean $update 是否更新
     *
     * @return array
     */
    public static function ruleCache($update = false)
    {
        $result = [];
        $module = defined('MODULE_NAME') ? '_' . MODULE_NAME : '';
        $cacheKey = str_replace('[MODULE_NAME]', $module, self::ROUTER_CACHE_KEY);

        if (APP_DEBUG || $update || !$result = S($cacheKey)) {
            // 静态路由
            $result[0] = C('URL_MAP_RULES');
            if (!empty($result[0])) {
                foreach ($result[0] as $rule => $route) {
                    if (!is_array($route)) {
                        $route = [$route];
                    }
                    if (strpos($route[0], '?')) {
                        // 分离参数
                        list($route[0], $args) = explode('?', $route[0], 2);
                        parse_str($args, $args);
                    } else {
                        $args = [];
                    }
                    if (!empty($route[1]) && is_string($route[1])) {
                        // 额外参数
                        parse_str($route[1], $route[1]);
                    }
                    $route[]          = $args;
                    $result[0][$rule] = $route;
                }
            }

            // 动态路由
            $result[1] = C('URL_ROUTE_RULES');
            if (!$result[1]) {
                $result[1] = [];
            }

            $dynamicRules = self::rule();
            if ($dynamicRules) {
                foreach ($dynamicRules as $rule => $route) {
                    $result[1][$rule] = $route;
                }
                C('URL_ROUTE_RULES', $result[1]);
            }

            if (!empty($result[1])) {
                // 为了安全，需要以key长度按倒序排序
                uksort($result[1], function ($a, $b) {
                    return strlen($b) - strlen($a);
                });

                foreach ($result[1] as $rule => $route) {
                    if (!is_array($route)) {
                        $route = [$route];
                    } elseif (is_numeric($rule)) {
                        // 支持 array('rule','adddress',...) 定义路由
                        $rule = array_shift($route);
                    }

                    if (!empty($route)) {
                        $args = [];
                        if (is_string($route[0])) {
                            if (0 === strpos($route[0], '/') || 0 === strpos($route[0], 'http')) {
                                // 重定向
                                if (!isset($route[1])) {
                                    $route[1] = 301;
                                }
                            } else {
                                if (defined('MODULE_NAME') && strpos($route[0], MODULE_NAME) !== 0) {
                                    $route[0] = MODULE_NAME . '/' . $route[0];
                                }
                                if (!empty($route[1]) && is_string($route[1])) {
                                    // 额外参数
                                    parse_str($route[1], $route[1]);
                                }
                                if (strpos($route[0], '?')) {
                                    // 分离参数
                                    list($route[0], $params) = explode('?', $route[0], 2);
                                    if (!empty($params)) {
                                        foreach (explode('&', $params) as $key => $val) {
                                            if (0 === strpos($val, ':')) {
                                                // 动态参数
                                                $val        = substr($val, 1);
                                                $args[$key] = strpos($val, '|') ? explode('|', $val, 2) : [$val];
                                            } else {
                                                $route[1][$key] = $val;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if ('/' != substr($rule, 0, 1)) {
                            // 规则路由
                            foreach (explode('/', rtrim($rule, '$')) as $item) {
                                $filter = $fun = '';
                                $type   = self::ROUTER_ARG_NAME;
                                if (0 === strpos($item, '[:')) {
                                    // 可选变量
                                    $type = self::ROUTER_ARG_OPTION;
                                    $item = substr($item, 1, -1);
                                }
                                if (0 === strpos($item, ':')) {
                                    // 动态变量获取
                                    $type = $type ?: self::ROUTER_ARG_VARIABLE;
                                    if ($pos = strpos($item, '|')) {
                                        // 支持函数过滤
                                        $fun  = substr($item, $pos + 1);
                                        $item = substr($item, 1, $pos - 1);
                                    }
                                    if ($pos = strpos($item, '^')) {
                                        // 排除项
                                        $filter = explode('-', substr($item, $pos + 1));
                                        $item   = substr($item, 1, $pos - 1);
                                    } elseif (strpos($item, '\\')) {
                                        // \d表示限制为数字
                                        if ('d' == substr($item, -1)) {
                                            $filter = 'int';
                                        }
                                        $item = substr($item, 1, -2);
                                    } else {
                                        $item = substr($item, 1);
                                    }
                                }
                                $args[$item] = [$type, $fun, $filter];
                            }
                        }
                        $route[]          = $args;
                        $result[1][$rule] = $route;
                    } else {
                        unset($result[1][$rule]);
                    }
                }
            }
            S($cacheKey, $result);
        }
        return $result;
    }

    /**
     * 路由参数检测
     *
     * @param array       $options 路由参数
     * @param string|true $suffix  URL后缀
     *
     * @return boolean
     */
    private static function checkOption($options, $suffix = true)
    {
        // URL后缀检测
        if (isset($options['ext'])) {
            if ($suffix) {
                $suffix = $suffix === true ? C('URL_HTML_SUFFIX') : $suffix;
                if (is_string($suffix)) {
                    $suffix = explode('|', $suffix);
                }
            }
            if (!in_array($options['ext'], $suffix)) {
                return false;
            }
        }
        if (isset($options['method']) && REQUEST_METHOD != strtoupper($options['method'])) {
            // 请求类型检测
            return false;
        }
        // 自定义检测
        if (!empty($options['callback']) && is_callable($options['callback'])) {
            if (false === call_user_func($options['callback'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检测URL和路由规则是否匹配
     *
     * @param string $rule 路由规则
     * @param array  $args 路由动态变量
     * @param string $regx URL地址
     *
     * @return array|false
     */
    private static function checkUrlMatch(&$rule, &$args, &$regx)
    {
        $params = [];
        if ('/' == substr($rule, 0, 1)) {
            // 正则路由
            if (preg_match($rule, $regx, $matches)) {
                if ($args) { // 存在动态变量
                    foreach ($args as $key => $val) {
                        $params[$key] = isset($val[1]) ? $val[1]($matches[$val[0]]) : $matches[$val[0]];
                    }
                    $regx = substr_replace($regx, '', 0, strlen($matches[0]));
                }
                array_shift($matches);
                return $matches;
            } else {
                return false;
            }
        } else {
            $paths = explode('/', $regx);
            // $结尾则要求完整匹配
            if ('$' == substr($rule, -1) && count($args) != count($paths)) {
                return false;
            }
            foreach ($args as $key => $val) {
                $var = array_shift($paths) ?: '';
                if ($val[0] == self::ROUTER_ARG_NAME) {
                    // 静态变量
                    if (0 !== strcasecmp($key, $var)) {
                        return false;
                    }
                } else {
                    if (isset($val[2])) {
                        // 设置了过滤条件
                        if ($val[2] == 'int') {
                            // 如果值不为整数
                            if (!preg_match('/^\d*$/', $var)) {
                                return false;
                            }
                        } else {
                            // 如果值在排除的名单里
                            if (is_array($val[2]) && in_array($var, $val[2])) {
                                return false;
                            }
                        }
                    }
                    if (!empty($var) || $var != '0') {
                        $params[$key] = !empty($val[1]) ? $val[1]($var) : $var;
                    } elseif ($val[0] == self::ROUTER_ARG_VARIABLE) {
                        // 不是可选的
                        return false;
                    }
                }
            }
            $matches = $params;
            $regx    = implode('/', $paths);
        }
        // 解析剩余的URL参数
        if ($regx) {
            preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$params) {
                $params[strtolower($match[1])] = strip_tags($match[2]);
            }, $regx);
        }
        $_GET = array_merge($params, $_GET);

        // 成功匹配后返回URL中的动态变量数组
        return $matches;
    }

    /**
     * 执行闭包方法 支持参数调用
     *
     * @param function $closure 闭包函数
     * @param array    $var     传给闭包的参数
     *
     * @return boolean
     */
    private static function invoke($closure, $var = [])
    {
        $reflect = new \ReflectionFunction($closure);
        $params  = $reflect->getParameters();
        $args    = [];
        foreach ($params as $i => $param) {
            $name = $param->getName();
            if (isset($var[$name])) {
                $args[] = $var[$name];
            } elseif (isset($var[$i])) {
                $args[] = $var[$i];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
        }
        return $reflect->invokeArgs($args);
    }


    /**
     * 生成URL
     *
     * @param string      $path   控制器/方法
     * @param array       $vars   url参数
     * @param string|true $suffix url后缀
     * @param bool        $domain
     *
     * @return string
     */
    public static function buildUrl($path, $vars, $suffix = true, $domain = false)
    {
        $varPath = C('VAR_PATHINFO');
        $urlCase = C('URL_CASE_INSENSITIVE');
        $urlMode = C('URL_MODEL');
        $depr    = C('URL_PATHINFO_DEPR');

        $info = self::reverse($path, $vars, $depr, $suffix);

        self::processHost($info, $domain, $urlMode);

        $url = self::getBaseUrl($info, $urlMode, $varPath);

        if (URL_COMMON == $urlMode) {
            $url .= self::buildCommonPath($info);
        } else {
            $url .= $info['path'];
            $url = self::appendSuffix($url, $suffix, $urlMode);
        }

        if ($urlCase) {
            $url = strtolower($url);
        }

        $url = self::appendQuery($url, $vars);

        if (isset($info['fragment'])) {
            $url .= '#' . $info['fragment'];
        }

        return (isset($info['host']) ? $info['host'] : '') . $url;
    }

    /**
     * 处理域名
     *
     * @param array $info
     * @param bool $domain
     * @param int $urlMode
     */
    private static function processHost(&$info, $domain, $urlMode)
    {
        if (isset($info['host']) && $info['host']) {
            $info['scheme'] = (isset($info['scheme']) && $info['scheme']) ? $info['scheme'] : 'http';
            $info['port']   = (isset($info['port']) && $info['port'] && $info['port'] != '80' && $info['port'] != '443') ? ':' . $info['port'] : '';
            $info['host']   = $info['scheme'] . '://' . $info['host'] . $info['port'] . '/';
        } elseif ($domain) {
            $info['host']   = $_SERVER['SERVER_NAME'];
            $info['port']   = $_SERVER['SERVER_PORT'];
            $info['port']   = (isset($info['port']) && $info['port'] && $info['port'] != '80' && $info['port'] != '443') ? ':' . $info['port'] : '';
            $info['scheme'] = $_SERVER['REQUEST_SCHEME'];
            if ($urlMode) {
                $tmp = '';
            } else {
                $tmp = $_SERVER['REQUEST_URI'];
                if ($pos = strpos($tmp, '?')) {
                    $tmp = substr($tmp, 0, $pos - 1);
                }
            }
            $info['host'] = $info['scheme'] . '://' . $info['host'] . $info['port'] . '/' . trim($tmp, '/') . '/';
        }
    }

    /**
     * 获取基础URL
     *
     * @param array $info
     * @param int $urlMode
     * @param string $varPath
     * @return string
     */
    private static function getBaseUrl($info, $urlMode, $varPath)
    {
        if (isset($info['host']) && $info['host']) {
            $url = basename(_PHP_FILE_);
        } else {
            $url = _PHP_FILE_;
        }

        if (URL_PATHINFO == $urlMode) {
            $url .= '/';
        } elseif (URL_REWRITE == $urlMode) {
            $url = dirname($url);
            if ('/' == $url || '\\' == $url || '.' == $url) {
                if (!isset($info['host']) || !$info['host']) {
                    $url = '/';
                } else {
                    $url = '';
                }
            } else {
                $url .= '/';
            }
        } else {
            $url .= '?' . $varPath . '=';
        }
        return $url;
    }

    /**
     * 构建普通模式路径
     *
     * @param array $info
     * @return string
     */
    private static function buildCommonPath($info)
    {
        $path = '';
        if ($info['path'] && $info['path'] == $info['ACTION']) {
            $info['path'] = '';
        }
        if ($info['path']) {
            $path .= $info['path'];
        } else {
            if (defined('BIND_MODULE') && BIND_MODULE == $info['MODULE']) {
            } else {
                $path .= $info['MODULE'] . '/';
            }
            $path .= $info['CONTROLLER'] . '/' . $info['ACTION'];
        }
        return $path;
    }

    /**
     * 添加后缀
     *
     * @param string $url
     * @param mixed $suffix
     * @param int $urlMode
     * @return string
     */
    private static function appendSuffix($url, $suffix, $urlMode)
    {
        if ($suffix && ($urlMode == URL_PATHINFO || $urlMode == URL_REWRITE)) {
            $suffix = true === $suffix ? C('URL_HTML_SUFFIX') : $suffix;
            if ($pos = strpos($suffix, '|')) {
                $suffix = substr($suffix, 0, $pos);
            }
            if ($suffix && '/' != substr($url, -1)) {
                $url .= '.' . ltrim($suffix, '.');
            }
        }
        return $url;
    }

    /**
     * 添加查询参数
     *
     * @param string $url
     * @param mixed $vars
     * @return string
     */
    private static function appendQuery($url, $vars)
    {
        if (!empty($vars)) {
            $vars = http_build_query($vars);
            $url  .= false === strpos($url, '?') ? '?' : '&';
            $url  .= $vars;
        }
        return $url;
    }

    private static function processMCA(&$param)
    {
        if (count($param) == 1) {
            $urls         = [
                MODULE_NAME,
                CONTROLLER_NAME,
                $param[0] ?: ACTION_NAME
            ];
            $param        = false;
            $info['path'] = $urls[1] . '/' . $urls[2];
        } elseif (count($param) == 2) {
            $urls         = [
                MODULE_NAME,
                $param[0] ?: CONTROLLER_NAME,
                $param[1] ?: ACTION_NAME
            ];
            $param        = false;
            $info['path'] = $urls[1] . '/' . $urls[2];
        } else {
            $urls[] = array_shift($param);
            $urls[] = array_shift($param);
            $urls[] = array_shift($param);
        }
        if (!$urls[0]) {
            $urls[0] = MODULE_NAME;
        }
        if (!$urls[1]) {
            $urls[1] = CONTROLLER_NAME;
        }
        if (!$urls[2]) {
            $urls[2] = ACTION_NAME;
        }
        return $urls;
    }

    /**
     * 添加路由规则
     *
     * @param string $rule 路由规则
     * @param string $route 路由路径
     *
     * @return array
     */
    public static function rule($rule = '', $route = [])
    {
        static $dynamicRules = [];
        if ($rule) {
            $dynamicRules[$rule] = $route;
        }
        return $dynamicRules;
    }
}
