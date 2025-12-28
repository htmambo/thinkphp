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

namespace Think\Driver\Session;

use PDO;
use PDOException;

/**
 * 数据库方式Session驱动 (PDO实现)
 *
 * 安全改进：
 * - 使用 PDO 预处理语句，防止 SQL 注入
 * - 移除废弃的 mysql_* 函数
 * - 支持读写分离和主从配置
 * - 增强错误处理和日志记录
 *
 * 建表SQL：
 *    CREATE TABLE think_session (
 *      session_id varchar(255) NOT NULL,
 *      session_expire int(11) NOT NULL,
 *      session_data blob,
 *      UNIQUE KEY `session_id` (`session_id`),
 *      KEY `session_expire` (`session_expire`)
 *    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
class Db
{
    /**
     * Session有效时间（秒）
     * @var int
     */
    protected $lifeTime = 0;

    /**
     * session保存的数据库名（已引用）
     * @var string
     */
    protected $sessionTable = '';

    /**
     * PDO 连接句柄数组
     * @var array<int, PDO>
     */
    protected $hander = [];

    /**
     * 打开Session
     *
     * @param string $savePath Session保存路径
     * @param string $sessName Session名称
     * @return bool
     */
    public function open($savePath, $sessName): bool
    {
        // 获取 Session 有效期
        $this->lifeTime = C('SESSION_EXPIRE')
            ? (int)C('SESSION_EXPIRE')
            : (int)ini_get('session.gc_maxlifetime');

        // 安全处理表名（防止配置注入）
        $rawTable = C('SESSION_TABLE')
            ? (string)C('SESSION_TABLE')
            : (string)C('DB_PREFIX') . 'session';

        $this->sessionTable = $this->quoteTableName($rawTable);
        if ($this->sessionTable === '') {
            error_log('Session Db driver: Invalid table name');
            return false;
        }

        // 建立数据库连接
        return $this->connect();
    }

    /**
     * 关闭Session
     *
     * @return bool
     */
    public function close(): bool
    {
        // 执行垃圾回收
        $this->gc($this->lifeTime);

        // 清理连接
        $this->hander = [];

        return true;
    }

    /**
     * 读取Session数据
     *
     * @param string $sessID Session ID
     * @return string Session数据，失败返回空字符串
     */
    public function read($sessID): string
    {
        $pdo = $this->getReadConnection();

        if (!$pdo instanceof PDO) {
            return '';
        }

        // 安全验证：Session ID 格式检查
        if (!$this->validateSessionId($sessID)) {
            error_log("Security Warning: Invalid session ID format in read: {$sessID}");
            return '';
        }

        try {
            $sql = sprintf(
                'SELECT session_data AS data FROM %s WHERE session_id = :id AND session_expire > :now',
                $this->sessionTable
            );

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $sessID, PDO::PARAM_STR);
            $stmt->bindValue(':now', time(), PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) && isset($row['data'])
                ? (string)$row['data']
                : '';
        } catch (PDOException $e) {
            error_log('Session Db driver: read failed - ' . $e->getMessage());
            return '';
        }
    }

    /**
     * 写入Session数据
     *
     * @param string $sessID Session ID
     * @param string $sessData Session数据
     * @return bool 成功返回true，失败返回false
     */
    public function write($sessID, $sessData): bool
    {
        $pdo = $this->getWriteConnection();

        if (!$pdo instanceof PDO) {
            return false;
        }

        // 安全验证：Session ID 格式检查
        if (!$this->validateSessionId($sessID)) {
            error_log("Security Warning: Invalid session ID format in write: {$sessID}");
            return false;
        }

        $expire = time() + $this->lifeTime;

        try {
            // 使用 REPLACE INTO 实现 upsert 语义
            $sql = sprintf(
                'REPLACE INTO %s (session_id, session_expire, session_data) VALUES (:id, :expire, :data)',
                $this->sessionTable
            );

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $sessID, PDO::PARAM_STR);
            $stmt->bindValue(':expire', $expire, PDO::PARAM_INT);
            $stmt->bindValue(':data', $sessData, PDO::PARAM_LOB);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Session Db driver: write failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除Session
     *
     * @param string $sessID Session ID
     * @return bool 成功返回true，失败返回false
     */
    public function destroy($sessID): bool
    {
        $pdo = $this->getWriteConnection();

        if (!$pdo instanceof PDO) {
            return false;
        }

        // 安全验证：Session ID 格式检查
        if (!$this->validateSessionId($sessID)) {
            error_log("Security Warning: Invalid session ID format in destroy: {$sessID}");
            return false;
        }

        try {
            $sql = sprintf(
                'DELETE FROM %s WHERE session_id = :id',
                $this->sessionTable
            );

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $sessID, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Session Db driver: destroy failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Session垃圾回收
     *
     * @param int $sessMaxLifeTime 最大生存时间
     * @return int|false 清理的记录数，失败返回false
     */
    public function gc($sessMaxLifeTime)
    {
        $pdo = $this->getWriteConnection();

        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $sql = sprintf(
                'DELETE FROM %s WHERE session_expire < :now',
                $this->sessionTable
            );

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':now', time(), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Session Db driver: gc failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 建立数据库连接
     *
     * 支持分布式数据库配置和读写分离
     *
     * @return bool 成功返回true
     */
    protected function connect(): bool
    {
        // 解析数据库配置
        $config = $this->parseDbConfig();

        if (empty($config['hosts'])) {
            error_log('Session Db driver: No valid database configuration');
            return false;
        }

        try {
            // 根据部署类型选择连接策略
            $deployType = (int)C('DB_DEPLOY_TYPE', 0);

            if ($deployType === 1 && C('DB_RW_SEPARATE')) {
                // 读写分离模式
                return $this->connectWithRwSeparation($config);
            }

            // 普通模式（随机选择一个从库）
            $index = array_rand($config['hosts']);
            $hostConfig = $config['hosts'][$index];

            $pdo = $this->createPdo(
                $hostConfig['host'],
                $hostConfig['port'],
                $hostConfig['dbname'],
                $hostConfig['user'],
                $hostConfig['pass']
            );

            $this->hander[0] = $pdo;

            return true;
        } catch (PDOException $e) {
            error_log('Session Db driver: connect failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 读写分离模式连接
     *
     * @param array $config 数据库配置
     * @return bool 成功返回true
     */
    protected function connectWithRwSeparation(array $config): bool
    {
        $masterNum = max(1, (int)C('DB_MASTER_NUM', 1));
        $total = count($config['hosts']);

        if ($total < $masterNum) {
            error_log('Session Db driver: DB_MASTER_NUM exceeds total hosts');
            return false;
        }

        // 选择主库（写）
        $masterIndex = random_int(0, $masterNum - 1);
        $masterConfig = $config['hosts'][$masterIndex];

        $writePdo = $this->createPdo(
            $masterConfig['host'],
            $masterConfig['port'],
            $masterConfig['dbname'],
            $masterConfig['user'],
            $masterConfig['pass']
        );

        // 选择从库（读）
        $slaveIndex = $masterNum;

        // 如果指定了从库序号
        if (is_numeric(C('DB_SLAVE_NO'))) {
            $slaveIndex = min((int)C('DB_SLAVE_NO'), $total - 1);
        } else {
            // 随机选择从库
            $slaveIndex = random_int($masterNum, $total - 1);
        }

        $slaveConfig = $config['hosts'][$slaveIndex];

        $readPdo = $this->createPdo(
            $slaveConfig['host'],
            $slaveConfig['port'],
            $slaveConfig['dbname'],
            $slaveConfig['user'],
            $slaveConfig['pass']
        );

        // hander[0] 用于写，hander[1] 用于读
        $this->hander[0] = $writePdo;
        $this->hander[1] = $readPdo;

        return true;
    }

    /**
     * 创建PDO连接
     *
     * @param string $host 主机地址
     * @param string $port 端口
     * @param string $dbName 数据库名
     * @param string $username 用户名
     * @param string $password 密码
     * @return PDO
     * @throws PDOException
     */
    protected function createPdo(
        string $host,
        string $port,
        string $dbName,
        string $username,
        string $password
    ): PDO {
        $charset = C('DB_CHARSET') ? (string)C('DB_CHARSET') : 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $dbName,
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // 使用原生预处理
        ];

        // 持久连接
        if (C('DB_PCONNECT', false)) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        $pdo = new PDO($dsn, $username, $password, $options);

        // 设置 MySQL 初始化命令
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $pdo->setAttribute(
                PDO::MYSQL_ATTR_INIT_COMMAND,
                "SET NAMES {$charset}"
            );
        }

        return $pdo;
    }

    /**
     * 获取写连接（主库）
     *
     * @return PDO|null
     */
    protected function getWriteConnection(): ?PDO
    {
        return $this->hander[0] ?? null;
    }

    /**
     * 获取读连接（从库或主库）
     *
     * @return PDO|null
     */
    protected function getReadConnection(): ?PDO
    {
        // 如果有读写分离，使用从库
        if (isset($this->hander[1])) {
            return $this->hander[1];
        }

        // 否则使用唯一连接
        return $this->hander[0] ?? null;
    }

    /**
     * 解析数据库配置
     *
     * @return array 配置数组
     */
    protected function parseDbConfig(): array
    {
        $hosts = explode(',', (string)C('DB_HOST', ''));
        $ports = explode(',', (string)C('DB_PORT', '3306'));
        $names = explode(',', (string)C('DB_NAME', ''));
        $users = explode(',', (string)C('DB_USER', ''));
        $passs = explode(',', (string)C('DB_PWD', ''));

        $config = [];

        foreach ($hosts as $index => $host) {
            $host = trim($host);
            if ($host === '') {
                continue;
            }

            $config['hosts'][] = [
                'host'   => $host,
                'port'   => trim($ports[$index] ?? $ports[0] ?? '3306'),
                'dbname' => trim($names[$index] ?? $names[0] ?? ''),
                'user'   => trim($users[$index] ?? $users[0] ?? ''),
                'pass'   => trim($passs[$index] ?? $passs[0] ?? ''),
            ];
        }

        return $config;
    }

    /**
     * 验证 Session ID 格式
     *
     * 只允许字母、数字、连字符和逗号
     *
     * @param string $sessID Session ID
     * @return bool
     */
    protected function validateSessionId(string $sessID): bool
    {
        if ($sessID === '') {
            return false;
        }

        // Session ID 通常是字母数字和可能的连字符
        return preg_match('/^[a-zA-Z0-9,-]+$/', $sessID) === 1;
    }

    /**
     * 安全引用表名
     *
     * 支持 schema.table 格式，仅允许字母数字下划线
     *
     * @param string $table 原始表名
     * @return string 引用后的表名，失败返回空字符串
     */
    protected function quoteTableName(string $table): string
    {
        $table = trim($table);

        if ($table === '') {
            return '';
        }

        // 分割 schema 和 table
        $parts = explode('.', $table);
        $quoted = [];

        foreach ($parts as $part) {
            $part = trim($part);

            // 验证表名格式（仅允许字母数字下划线）
            if ($part === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $part)) {
                error_log("Security Warning: Invalid table name format: {$table}");
                return '';
            }

            $quoted[] = '`' . $part . '`';
        }

        return implode('.', $quoted);
    }
}
