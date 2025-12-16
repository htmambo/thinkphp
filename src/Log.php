<?php
namespace Think;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * 日志处理类 - 基于Monolog的日志管理
 */
class Log
{
    const EMERG  = 'EMERGENCY';
    const ALERT  = 'ALERT';
    const CRIT   = 'CRITICAL';
    const ERR    = 'ERROR';
    const WARN   = 'WARNING';
    const NOTICE = 'NOTICE';
    const INFO   = 'INFO';
    const DEBUG  = 'DEBUG';
    const SQL    = 'SQL';

    /**
     * @var Logger
     */
    public static $logger = null;

    /**
     * @var array
     */
    protected static $config = [];

    /**
     * 初始化日志记录器
     *
     * @param array $config 配置参数
     * @return void
     */
    public static function init($config = [])
    {
        if (self::$logger !== null) {
            return;
        }

        // 合并配置
        self::$config = array_merge([
            'log_path' => C('LOG_PATH'),
            'log_level' => C('LOG_LEVEL', null, 'EMERG,ALERT,CRIT,ERR'),
            'log_file_size' => C('LOG_FILE_SIZE', null, 2097152),
            'log_max_files' => C('LOG_MAX_FILES', null, 10),
            'channel_name' => 'ThinkPHP',
        ], $config);

        self::$logger = new Logger(self::$config['channel_name']);

        $logPath = self::$config['log_path'];

        // 确保日志目录存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // 添加文件处理器 - 使用RotatingFileHandler支持日志轮转
        $filename = $logPath . 'application';
        $maxFiles = (int)self::$config['log_max_files'];
        $handler = new RotatingFileHandler($filename . '.log', $maxFiles, Logger::DEBUG);
        
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        );
        $handler->setFormatter($formatter);
        self::$logger->pushHandler($handler);

        // 在非CLI环境下添加浏览器控制台处理器
        if (!IS_CLI) {
            $browserHandler = new BrowserConsoleHandler(Logger::DEBUG);
            self::$logger->pushHandler($browserHandler);
        }
    }

    /**
     * 记录日志 并根据配置的级别过滤
     *
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
     * @param string $message 日志信息
     * @param string $level 日志级别
     * @param string $type 日志记录方式(保留用于兼容性)
     * @param string $destination 写入目标(保留用于兼容性)
     * @param boolean $directSave 是否直接保存(保留用于兼容性)
     * @return void
     */
    public static function write($message, $level = self::ERR, $type = '', $destination = '', $directSave = false)
    {
        if (self::$logger === null) {
            self::init();
        }

        $level = strtoupper($level);
        $monologLevel = self::getMonologLevel($level);
        
        self::$logger->log($monologLevel, $message);
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
     * 获取日志记录器实例
     *
     * @return Logger
     */
    public static function getLogger()
    {
        if (self::$logger === null) {
            self::init();
        }
        return self::$logger;
    }

    /**
     * 魔术方法 支持调用日志级别作为方法
     *
     * @param string $name
     * @param array $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        if (!empty($arguments)) {
            self::write($arguments[0], $name);
        }
    }
}