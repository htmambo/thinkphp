<?php
namespace Think;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;

/**
 * 日志处理类
 */
class Log
{
    // 日志级别 从上到下，由低到高
    const EMERG  = 'EMERGENCY'; // 严重错误: 导致系统崩溃无法使用
    const ALERT  = 'ALERT';     // 警戒性错误: 必须被立即修改的错误
    const CRIT   = 'CRITICAL';  // 临界值错误: 超过临界值的错误
    const ERR    = 'ERROR';     // 一般错误: 一般性错误
    const WARN   = 'WARNING';   // 警告性错误: 需要发出警告的错误
    const NOTICE = 'NOTICE';    // 通知: 程序可以运行但是还不够完美的错误
    const INFO   = 'INFO';      // 信息: 程序输出信息
    const DEBUG  = 'DEBUG';     // 调试: 调试信息
    const SQL    = 'SQL';       // SQL：SQL语句 注意只在调试模式开启时有效

    /**
     * @var Logger
     * Monolog 日志记录器实例
     */
    public static $logger = null;

    // 初始化
    public static function init($config = array())
    {
        if (self::$logger === null) {
            self::$logger = new Logger('ThinkPHP');

            $logPath = isset($config['log_path']) ? $config['log_path'] : C('LOG_PATH');
            $logFile = $logPath . date('y_m_d') . '.log';

            // 确保日志目录存在
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }
            self::$logger->pushHandler(new StreamHandler($logFile));
            self::$logger->pushHandler(new BrowserConsoleHandler());
        }
    }

    /**
     * 记录日志 并且会过滤未经设置的级别
     *
     * @static
     * @access public
     * @param string $message 日志信息
     * @param string $level 日志级别
     * @param boolean $record 是否强制记录
     * @return void
     */
    public static function record($message, $level = self::ERR, $record = false)
    {
        if ($record || false !== strpos(C('LOG_LEVEL'), $level)) {
            self::write($message, $level);
        }
    }

    /**
     * 日志直接写入
     *
     * @static
     * @access public
     * @param string $message 日志信息
     * @param string $level 日志级别
     * @param string $type 日志记录方式
     * @param string $destination 写入目标
     * @param boolean $directSave 是否直接保存
     * @return void
     */
    public static function write($message, $level = self::ERR, $type = '', $destination = '', $directSave = false)
    {
        if (self::$logger === null) {
            self::init();
        }

        $level = strtoupper($level);
        $monologLevel = self::getMonologLevel($level);
        
        if (!$directSave) {
            self::$logger->log($monologLevel, $message);
        } else {
            self::$logger->log($monologLevel, $message);
        }
    }

    /**
     * 将ThinkPHP日志级别转换为Monolog日志级别
     *
     * @param string $level
     * @return int
     */
    protected static function getMonologLevel($level)
    {
        $levels = Logger::getLevels();

        return $levels[$level] ?? Logger::INFO;
    }

    /**
     * 魔术方法 有不存在的静态方法时调用
     *
     * @param string $name
     * @param array $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        self::write($arguments[0], $name);
    }
}