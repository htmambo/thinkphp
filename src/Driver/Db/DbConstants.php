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

namespace Think\Driver\Db;

/**
 * 数据库操作常量定义
 *
 * 用于集中管理数据库模块中的硬编码值，提高代码可维护性
 */
class DbConstants
{
    /**
     * 数据库错误代码
     */
    public const ERROR_DB_EXCEPTION = 10500;
    public const ERROR_QUERY_FAILED = 10501;
    public const ERROR_CONNECT_FAILED = 10502;
    public const ERROR_PARSE_FAILED = 10503;

    /**
     * 查询相关常量
     */
    public const DEFAULT_LIMIT = 1;
    public const DEFAULT_PAGE_SIZE = 20;
    public const DEFAULT_LIST_ROWS = 20;

    /**
     * 字段验证相关
     */
    public const MAX_FIELD_LENGTH = 100;
    public const MAX_SQL_LENGTH = 500;
    public const MAX_CONFIG_VALUE_LENGTH = 100;

    /**
     * 缓存相关
     */
    public const CACHE_FIELDS_VERSION = 1;

    /**
     * 事务相关
     */
    public const TRANSACTION_NOT_STARTED = 0;

    /**
     * SQL 模式
     */
    public const SQL_MODE_STRICT = 'STRICT_TRANS_TABLES';
    public const SQL_MODE_TRADITIONAL = 'TRADITIONAL';

    /**
     * 字符集相关
     */
    public const DEFAULT_CHARSET = 'utf8';
    public const DEFAULT_COLLATION = 'utf8_general_ci';

    /**
     * 连接相关
     */
    public const DEFAULT_PORT = 3306;
    public const CONNECTION_TIMEOUT = 30;
    public const READ_TIMEOUT = 30;

    /**
     * 正则表达式模式
     */
    public const PATTERN_IDENTIFIER = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    public const PATTERN_EMAIL = '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/';
    public const PATTERN_URL = '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(:\d+)?(?:[\/\?#][\/=\?%\-&~`@\[\]\':+!.\#\w]*)?$/';
    public const PATTERN_CURRENCY = '/^\d+(\.\d+)?$/';
    public const PATTERN_NUMBER = '/^\d+$/';
    public const PATTERN_ZIP = '/^\d{6}$/';
    public const PATTERN_INTEGER = '/^[-\+]?\d+$/';
    public const PATTERN_DOUBLE = '/^[-\+]?\d+(\.\d+)?$/';
    public const PATTERN_ENGLISH = '/^[A-Za-z]+$/';
    public const PATTERN_REQUIRE = '/\S+/';

    /**
     * 不应直接实例化此类
     */
    private function __construct()
    {
    }
}
