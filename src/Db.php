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

use Think\Exception\DbException;
use Think\Exception\PDOException;

/**
 * ThinkPHP 数据库中间层实现类
 *
 * @method static mixed query(string $str, bool $fetchSql = false, bool $master = false)
 * @method static mixed execute(string $str, bool $fetchSql = false)
 * @method static string parseWhere($where)
 */
class Db
{

    /** @var array<string,object> 数据库连接实例 */
    private static $instance = [];

    /** @var object|null 当前数据库连接实例 */
    private static $_instance = null;

    /**
     * 取得数据库类实例
     *
     * @static
     * @access public
     * @param array|string $config 连接配置（数组）或 DSN 字符串
     * @return object 返回数据库驱动类
     * @throws DbException
     */
    public static function getInstance($config = []): object
    {
        $md5 = md5(serialize($config));
        if (!isset(self::$instance[$md5])) {
            // 解析连接参数 支持数组和字符串
            $options = self::parseConfig($config);
            if ($options === false) {
                throw new DbException(L('_NO_DB_DRIVER_'), $config);
            }
            // 兼容mysqli
            if ('mysqli' == $options['type']) {
                $options['type'] = 'mysql';
            }

            // 如果采用lite方式 仅支持原生SQL 包括query和execute方法
            $class = 'Think\\Driver\\Db\\' . ucwords(strtolower($options['type']));
            if (class_exists($class)) {
                self::$instance[$md5] = new $class($options);
            } else {
                // 类没有定义
                throw new DbException(L('_NO_DB_DRIVER_') . ': ' . $class);
            }
        }
        self::$_instance = self::$instance[$md5];
        return self::$_instance;
    }

    /**
     * 手动关闭所有已经打开的数据库连接
     *
     * @return void
     */
    public static function closeAll(): void
    {
        foreach (self::$instance as $k => $v) {
            if (method_exists($v, 'close')) {
                $v->close();
            }
            self::$instance[$k] = null;
        }
        self::$instance = [];
    }

    /**
     * 数据库连接参数解析
     *
     * @static
     * @access private
     * @param array|string $config 配置数组或 DSN 字符串
     * @return array|false 解析后的配置数组，失败返回 false
     */
    private static function parseConfig($config)
    {
        if (!empty($config)) {
            if (is_string($config)) {
                return self::parseDsn($config);
            }
            $config = array_change_key_case($config);
            $config = [
                'type'        => $config['db_type'] ?? null,
                'username'    => $config['db_user'] ?? null,
                'password'    => $config['db_pwd'] ?? null,
                'hostname'    => $config['db_host'] ?? null,
                'hostport'    => $config['db_port'] ?? null,
                'database'    => $config['db_name'] ?? null,
                'dsn'         => $config['db_dsn'] ?? null,
                'params'      => $config['db_params'] ?? null,
                'charset'     => $config['db_charset'] ?? 'utf8',
                'deploy'      => $config['db_deploy_type'] ?? 0,
                'rw_separate' => $config['db_rw_separate'] ?? false,
                'master_num'  => $config['db_master_num'] ?? 1,
                'slave_no'    => $config['db_slave_no'] ?? '',
                'debug'       => $config['db_debug'] ?? (defined('APP_DEBUG') ? APP_DEBUG : false),
                'lite'        => $config['db_lite'] ?? false,
            ];
        } else {
            $config = [
                'type'        => C('DB_TYPE'),
                'username'    => C('DB_USER'),
                'password'    => C('DB_PWD'),
                'hostname'    => C('DB_HOST'),
                'hostport'    => C('DB_PORT'),
                'database'    => C('DB_NAME'),
                'dsn'         => C('DB_DSN'),
                'params'      => C('DB_PARAMS'),
                'charset'     => C('DB_CHARSET'),
                'deploy'      => C('DB_DEPLOY_TYPE'),
                'rw_separate' => C('DB_RW_SEPARATE'),
                'master_num'  => C('DB_MASTER_NUM'),
                'slave_no'    => C('DB_SLAVE_NO'),
                'debug'       => C('DB_DEBUG', null, defined('APP_DEBUG') ? APP_DEBUG : false),
                'lite'        => C('DB_LITE'),
            ];
        }
        return $config;
    }

    /**
     * DSN解析
     *
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     *
     * @static
     * @access private
     * @param string $dsnStr DSN 字符串
     * @return array|false 解析后的配置数组，失败返回 false
     */
    private static function parseDsn(string $dsnStr)
    {
        if (empty($dsnStr)) {
            return false;
        }
        $info = parse_url($dsnStr);
        if (!$info) {
            return false;
        }
        $dsn = [
            'type'     => $info['scheme'] ?? '',
            'username' => $info['user'] ?? '',
            'password' => $info['pass'] ?? '',
            'hostname' => $info['host'] ?? '',
            'hostport' => $info['port'] ?? '',
            'database' => isset($info['path']) ? ltrim($info['path'], '/') : '',
            'charset'  => $info['fragment'] ?? 'utf8',
        ];

        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }
        return $dsn;
    }

    /**
     * 调用驱动类的方法
     *
     * @param string $method 方法名
     * @param array $params 参数数组
     * @return mixed
     * @throws DbException
     */
    public static function __callStatic(string $method, array $params)
    {
        if (!is_object(self::$_instance)) {
            self::getInstance();
        }
        return call_user_func_array([self::$_instance, $method], $params);
    }
}
