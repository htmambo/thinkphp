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

use Think\Helper\DocParser;
use Think\Exception\ErrorException;
/**
 * ThinkPHP 应用程序类 执行应用过程管理
 */
class App
{
    /**
     * @var DocParser
     */
    static $parser = false;

    /**
     * 新架构 bootstrap 标记
     * @var bool
     */
    private static bool $bootstrapped = false;

    /**
     * 检测当前请求是否为AJAX请求
     *
     * @return bool 如果是AJAX请求返回true，否则返回false
     */
    private static function isAjaxRequest(): bool
    {
        // 1. 检查X-Requested-With头（最常用）
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH']);
            if ($requestedWith === 'xmlhttprequest') {
                return true;
            }
        }

        // 2. 检查Accept头（JSON请求通常也是AJAX）
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = strtolower($_SERVER['HTTP_ACCEPT']);
            if (strpos($accept, 'application/json') !== false) {
                return true;
            }
        }

        // 3. 检查AJAX提交参数（用于不支持header的环境）
        $ajaxParam = C('VAR_AJAX_SUBMIT');
        if ($ajaxParam) {
            if (!empty($_POST[$ajaxParam]) || !empty($_GET[$ajaxParam])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 应用程序初始化
     *
     * @access public
     * @return void
     * @throws Exception
     */
    public static function init()
    {
        // 使用重构后的AJAX检测方法
        define('IS_AJAX', self::isAjaxRequest());
        //检查一下
        $allow = C('MODULE_ALLOW_LIST');
        $deny = (array) C('MODULE_DENY_LIST');
        //必须禁止的几个模块
        $mustDenys = ['Common', 'Command', 'Runtime'];
        $diff = array_diff($mustDenys, $deny);
        if ($diff) {
            $deny = array_merge($deny, $diff);
            C('MODULE_DENY_LIST', $deny);
        }
        if ($allow && count($allow) > 1 && !C('MULTI_MODULE')) {
            throw new \Think\Exception\BadRequestException(502, L('Multiple modules are not enabled but multiple modules are configured!'));
        }
        if ($allow) {
            $repeat = array_intersect($allow, $deny);
            if ($repeat) {
                throw new \Think\Exception\BadRequestException(502, L('The configuration file is incorrect, and the prohibited module and the allowable module appear at the same time: {$var_0}', ['var_0' => implode(',', $repeat)]));
            }
        }
        // 日志目录转换为绝对路径 默认情况下存储到公共模块下面
        C('LOG_PATH', realpath(LOG_PATH) . '/Common/');
        // 定义当前请求的系统常量
        define('NOW_TIME', $_SERVER['REQUEST_TIME']);
        define('REQUEST_METHOD', isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
        define('IS_GET', REQUEST_METHOD === 'GET');
        define('IS_POST', REQUEST_METHOD === 'POST');
        define('IS_PUT', REQUEST_METHOD === 'PUT');
        define('IS_DELETE', REQUEST_METHOD === 'DELETE');
        if (IS_CLI && basename(_PHP_FILE_) === 'think') {
            Console::init(true);
            exit;
        }
        // URL调度
        Hook::listen('url_dispatch');
        Dispatcher::dispatch();
        if (C('REQUEST_VARS_FILTER')) {
            // 全局安全过滤
            array_walk_recursive($_GET, 'think_filter');
            array_walk_recursive($_POST, 'think_filter');
            array_walk_recursive($_REQUEST, 'think_filter');
        }
        // URL调度结束标签
        Hook::listen('url_dispatch_end');
        // TMPL_EXCEPTION_FILE 改为绝对地址
        C('TMPL_EXCEPTION_FILE', realpath(C('TMPL_EXCEPTION_FILE')));

        // 自动注册中间件
        self::registerMiddlewares();
    }

    /**
     * 自动注册所有中间件
     *
     * 按照优先级顺序注册中间件：
     * 1. app_begin: 维护模式 → 跨域 → 限流 → 请求日志
     * 2. action_begin: 请求日志 → 认证 → 权限 → CSRF → 响应格式化
     * 3. app_end: 请求日志（结束） → 性能监控
     *
     * @access private
     * @return void
     */
    private static function registerMiddlewares(): void
    {
        // ============================================
        // Phase 1: app_begin (请求开始前，最高优先级)
        // ============================================

        // 1.1 维护模式中间件（最高优先级）
        if (C('MAINTENANCE_ON', false)) {
            Hook::add('app_begin', 'Think\\Middleware\\MaintenanceMiddleware');
        }

        // 1.2 跨域中间件
        if (C('CORS_ON', false)) {
            Hook::add('app_begin', 'Think\\Middleware\\CorsMiddleware');
        }

        // 1.3 限流中间件
        if (C('RATE_LIMIT_ON', false)) {
            Hook::add('app_begin', 'Think\\Middleware\\RateLimitMiddleware');
        }

        // 1.4 请求日志中间件（开始）
        if (C('REQUEST_LOG_ON', false)) {
            Hook::add('app_begin', 'Think\\Middleware\\RequestLoggingMiddleware');
        }

        // ============================================
        // Phase 2: action_begin (控制器执行前)
        // ============================================

        // 2.1 认证中间件
        if (C('AUTH_ON', false)) {
            Hook::add('action_begin', 'Think\\Middleware\\AuthMiddleware');
        }

        // 2.2 权限验证中间件
        if (C('PERMISSION_ON', false)) {
            Hook::add('action_begin', 'Think\\Middleware\\PermissionMiddleware');
        }

        // 2.3 CSRF 中间件
        if (C('CSRF_ON', false)) {
            Hook::add('action_begin', 'Think\\Middleware\\CsrfMiddleware');
        }

        // ============================================
        // Phase 3: app_end (请求结束后）
        // ============================================

        // 3.1 响应格式化中间件
        if (C('API_FORMAT_ON', false)) {
            Hook::add('app_end', 'Think\\Middleware\\ResponseMiddleware');
        }
    }
    /**
     * 执行应用程序
     *
     * @access public
     * @return void
     * @throws Exception
     * @throws \ReflectionException
     * @throws \Exception
     */
    public static function exec()
    {
        if (!preg_match('/^[A-Za-z](\/|\w)*$/', CONTROLLER_NAME)) {
            // 安全检测
            $module = false;
        } else {
            //创建控制器实例
            $module = controller(CONTROLLER_NAME, CONTROLLER_PATH);
        }
        if (!$module) {
            // 是否定义Empty控制器
            $module = A('Empty');
            if (!$module) {
                throw new Exception(L('_CONTROLLER_NOT_EXIST_') . ':' . MODULE_NAME . '/' . CONTROLLER_NAME, 404);
            }
        }
        // 获取当前操作名 支持动态路由
        if (!isset($action)) {
            $action = ACTION_NAME . C('ACTION_SUFFIX');
        }
        try {
            self::invokeAction($module, $action);
        } catch (\ReflectionException $e) {
            // 方法调用发生异常后 引导到__call方法处理
            $method = new \ReflectionMethod($module, '__call');
            $method->invokeArgs($module, array($action, ''));
        }
    }
    /**
     * 解析硬节点属性
     *
     * @param \ReflectionClass $module
     * @param \ReflectionMethod $method
     * @return array
     * @throws ErrorException
     */
    private static function parseActionComment($module, $method)
    {
        if (!static::$parser) {
            static::$parser = new DocParser();
        }
        $file = rtrim(APP_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, get_class($module)) . '.php';
        $line = $method->getStartLine();
        $comment = $method->getDocComment();
        $comment = trim($comment);
        $result = static::$parser->parse($comment);
        if (CHECK_ACTION_COMMENT === 2 && (!$result || !$result['description'])) {
            throw new \Think\Exception\ErrorException(E_ERROR, L('_ACTION_COMMENT_ERROR_'), $file, $line);
        }
        if (isset($result['method']) && $result['method'] && is_string($result['method'])) {
            $result['method'] = strtoupper($result['method']);
            $reqMethod = REQUEST_METHOD ?: 'GET';
            if ($reqMethod !== $result['method']) {
                throw new \Think\Exception\BadRequestException(502, L('_METHOD_ERROR_'));
            }
        } else {
            unset($result['method']);
        }
        return $result;
    }
    /**
     * @throws \ReflectionException
     * @throws Exception
     */
    public static function invokeAction($module, $action)
    {
        if (!preg_match('/^[A-Za-z_](\w)*$/', $action)) {
            // 非法操作
            throw new \Think\Exception\NotFoundException(L('_METHOD_NOT_EXIST_'));
        }
        //执行当前操作
        $method = new \ReflectionMethod($module, $action);
        $line = $method->getStartLine();
        $class = new \ReflectionClass($module);
        $file = rtrim(APP_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class->getName()) . '.php';
        if ($method->isPublic() && !$method->isStatic()) {
            if (CHECK_ACTION_COMMENT) {
                self::parseActionComment($module, $method);
            }
            // 前置操作
            if ($class->hasMethod('_before_' . $action)) {
                $before = $class->getMethod('_before_' . $action);
                if ($before->isPublic()) {
                    $before->invoke($module);
                }
            }
            // URL参数绑定检测
            if ($method->getNumberOfParameters() > 0 && C('URL_PARAMS_BIND')) {
                if (!isset($_SERVER['REQUEST_METHOD'])) {
                    $_SERVER['REQUEST_METHOD'] = 'GET';
                }
                switch ($_SERVER['REQUEST_METHOD']) {
                    case 'POST':
                        $vars = array_merge($_GET, $_POST);
                        break;
                    case 'PUT':
                        $putData = [];
                        parse_str(file_get_contents('php://input'), $putData);
                        $vars = $putData;
                        break;
                    default:
                        $vars = $_GET;
                }
                $args = [];
                $params = $method->getParameters();
                $paramsBindType = C('URL_PARAMS_BIND_TYPE');
                foreach ($params as $param) {
                    $name = $param->getName();
                    if (1 == $paramsBindType && !empty($vars)) {
                        $args[] = array_shift($vars);
                    } elseif (0 == $paramsBindType && isset($vars[$name])) {
                        $args[] = $vars[$name];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        throw new \Think\Exception\ForbiddenException(L('_PARAM_ERROR_') . ': ' . $name);
                    }
                }
                // 开启绑定参数过滤机制
                if (C('URL_PARAMS_FILTER')) {
                    $filters = C('URL_PARAMS_FILTER_TYPE') ?: C('DEFAULT_FILTER');
                    if ($filters) {
                        $filters = explode(',', $filters);
                        foreach ($filters as $filter) {
                            $args = array_map_recursive($filter, $args);
                            // 参数过滤
                        }
                    }
                }
                array_walk_recursive($args, 'think_filter');
                $method->invokeArgs($module, $args);
            } else {
                $method->invoke($module);
            }
            // 后置操作
            if ($class->hasMethod('_after_' . $action)) {
                $after = $class->getMethod('_after_' . $action);
                if ($after->isPublic()) {
                    $after->invoke($module);
                }
            }
        } else {
            // 操作方法不是Public 抛出异常
            throw new \Think\Exception\ForbiddenException(L('_PARAM_ERROR_'));
        }
    }

    /**
     * Bootstrap 新架构组件
     *
     * 初始化 Request/Response 对象，加载 Service Providers
     *
     * 设计理念
     * - 不改变 ThinkPHP 3 的原有执行流程
     * - 仅把新架构组件"挂载"到容器，供新代码使用
     * - 完全向后兼容，旧代码不受影响
     *
     * @access private
     * @return void
     * @throws \Throwable
     */
    private static function bootstrap(): void
    {
        // 确保只 bootstrap 一次
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $container = Container::getInstance();

        // 注册容器自身
        $container->instance(Container::class, $container);

        // 注册 Request 对象（如果尚未注册）
        if (!$container->has(Request::class)) {
            $request = Request::createFromGlobals();
            $container->instance(Request::class, $request);
            $container->instance('request', $request);
        }

        // 注册 Response 对象（如果尚未注册）
        if (!$container->has(Response::class)) {
            $response = new Response();
            $container->instance(Response::class, $response);
            $container->instance('response', $response);
        }

        // 加载 Service Providers（从配置读取）
        $providers = (array)C('SERVICE_PROVIDERS', []);

        if ($providers !== []) {
            // 延迟加载，避免不必要的依赖
            if (!class_exists('Think\\Support\\ServiceProviderManager', false)) {
                // ServiceProviderManager 不存在，跳过
                return;
            }

            $manager = new \Think\Support\ServiceProviderManager($container);
            $container->instance(\Think\Support\ServiceProviderManager::class, $manager);

            try {
                $manager->register($providers);
                $manager->boot();
            } catch (\Throwable $e) {
                // Service Provider 加载失败不应导致整个应用崩溃
                // 仅在调试模式下抛出异常
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    throw $e;
                }

                // 记录错误日志
                if (function_exists('Think\\Log::record')) {
                    \Think\Log::record('Service Provider bootstrap failed: ' . $e->getMessage(), 'ERROR');
                }
            }
        }
    }

    /**
     * 运行应用实例 入口文件使用的快捷方法
     *
     * @access public
     * @return void
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function run()
    {
        // 加载动态应用公共文件和配置
        load_ext_file(COMMON_PATH);

        // Bootstrap 新架构组件（Request/Response/Service Providers）
        // 向后兼容：不改变原有执行流程，仅挂载新组件到容器
        self::bootstrap();

        // 应用初始化标签
        Hook::listen('app_init');
        App::init();
        // 应用开始标签
        Hook::listen('app_begin');
        // Session初始化
        if (!IS_CLI) {
            session(C('SESSION_OPTIONS'));
        }
        // 记录应用初始化时间
        G('initTime');
        App::exec();
        // 应用结束标签
        Hook::listen('app_end');
        return;
    }
}