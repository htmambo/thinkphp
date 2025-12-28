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

namespace Think\Exception;

use Think\Exception;
use Think\Driver\Db\DbConstants;

/**
 * Database相关异常处理类
 */
class DbException extends Exception
{
    /**
     * DbException constructor.
     * @access public
     * @param  string    $message
     * @param  array     $config
     * @param  string    $sql
     * @param  string    $func
     * @param  integer   $code
     * @param  ?\Throwable $previous 前一个异常
     */
    public function __construct($message, array $config = [], $sql = '', $func = '', $code = DbConstants::ERROR_DB_EXCEPTION, ?\Throwable $previous = null)
    {
        // 处理调试信息
        foreach(['line', 'file', 'function'] as $k) {
            if(isset($config[$k])) {
                $this->$k = $config[$k];
                unset($config[$k]);
            }
        }
        $this->setFunc($func);

        // 处理 trace
        if(isset($config['trace'])) {
            $this->setData('trace', $config['trace']);
            unset($config['trace']);
        }

        // 过滤敏感配置信息后再存储
        if($config) {
            $safeConfig = $this->sanitizeConfig($config);
            // 只在非生产环境或调试模式下显示配置
            if ($this->shouldShowConfig()) {
                $this->setData('Database Config', $safeConfig);
            }
        }

        // 存储 SQL（可能包含敏感数据，需要小心处理）
        if($sql) {
            // 在非生产环境或调试模式下显示 SQL
            if ($this->shouldShowSql()) {
                $this->setData('SQL', [$this->sanitizeSql($sql)]);
            }
        }

        // 调用父类构造函数，确保异常链完整
        parent::__construct($message, $code, [], E_ERROR, false, $previous);
    }

    /**
     * 清理配置中的敏感信息
     *
     * @param array $config 原始配置
     * @return array 清理后的配置
     */
    private function sanitizeConfig(array $config): array
    {
        // 需要掩码的敏感字段
        $sensitiveKeys = [
            'password', 'passwd', 'pwd',
            'username', 'user', 'uid',
            'database', 'dbname', 'db',
            'host', 'hostname', 'server',
            'port',
            'dsn', 'connection',
        ];

        $sanitized = [];
        foreach ($config as $key => $value) {
            // 跳过非字符串配置（如布尔值、数组等）
            if (!is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            // 检查是否为敏感字段
            $lowerKey = strtolower($key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                // 对敏感字段进行掩码
                $sanitized[$key] = '********';
            } else {
                // 保留非敏感字段，但限制长度
                $sanitized[$key] = mb_substr($value, 0, DbConstants::MAX_CONFIG_VALUE_LENGTH);
                if (mb_strlen($value) > DbConstants::MAX_CONFIG_VALUE_LENGTH) {
                    $sanitized[$key] .= '... (truncated)';
                }
            }
        }

        return $sanitized;
    }

    /**
     * 清理 SQL 中的敏感信息
     *
     * @param string $sql SQL 语句
     * @return string 清理后的 SQL
     */
    private function sanitizeSql(string $sql): string
    {
        // 移除可能包含敏感数据的值（密码、令牌等）
        // 这是一个基本的实现，可以根据需要增强

        // 限制 SQL 长度
        $sql = mb_substr($sql, 0, DbConstants::MAX_SQL_LENGTH);
        if (mb_strlen($sql) >= DbConstants::MAX_SQL_LENGTH) {
            $sql .= '... (truncated)';
        }

        return $sql;
    }

    /**
     * 判断是否应该显示配置信息
     *
     * @return bool
     */
    private function shouldShowConfig(): bool
    {
        // 只在调试模式或非生产环境显示
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return true;
        }
        if (defined('APP_ENV') && APP_ENV !== 'production') {
            return true;
        }
        return false;
    }

    /**
     * 判断是否应该显示 SQL
     *
     * @return bool
     */
    private function shouldShowSql(): bool
    {
        // 只在调试模式或非生产环境显示
        return $this->shouldShowConfig();
    }

}
