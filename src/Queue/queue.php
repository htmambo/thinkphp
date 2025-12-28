<?php

/**
 * Queue 队列配置文件
 *
 * 将此文件复制到您的应用配置目录（例如：Common/Conf/queue.php）
 * 并根据您的环境修改相应的配置项
 *
 * 使用方法：
 * 1. 复制此文件到您的应用配置目录
 * 2. 在主配置文件中引入：array_merge(include(__DIR__ . '/config.php'), include(__DIR__ . '/queue.php'))
 * 3. ���者直接在主配置文件中添加这些配置项
 */

return [
    // ========================================================================
    // 队列基本配置
    // ========================================================================

    /**
     * 默认队列驱动
     *
     * 可选值：sync, database, redis
     * - sync: 同步执行（用于开发和测试）
     * - database: 使用数据库存储队列
     * - redis: 使用 Redis 存储队列（推荐用于生产环境）
     */
    'QUEUE_DEFAULT' => 'sync',

    /**
     * 默认队列名称
     */
    'QUEUE_QUEUE' => 'default',

    /**
     * 任务失败后重试间隔（秒）
     */
    'QUEUE_RETRY_AFTER' => 90,

    // ========================================================================
    // 数据库队列配置
    // ========================================================================

    /**
     * 队列任务表名
     *
     * 需要创建以下表：
     *
     * CREATE TABLE `jobs` (
     *   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
     *   `queue` varchar(255) NOT NULL,
     *   `payload` longtext NOT NULL,
     *   `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
     *   `reserved_at` int(10) unsigned DEFAULT NULL,
     *   `available_at` int(10) unsigned NOT NULL,
     *   `created_at` int(10) unsigned NOT NULL,
     *   PRIMARY KEY (`id`),
     *   KEY `jobs_queue_index` (`queue`)
     * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
     */
    'QUEUE_TABLE' => 'jobs',

    /**
     * 失败任务表名
     *
     * CREATE TABLE `failed_jobs` (
     *   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
     *   `uuid` varchar(255) NOT NULL,
     *   `connection` varchar(255) NOT NULL,
     *   `queue` varchar(255) NOT NULL,
     *   `payload` longtext NOT NULL,
     *   `exception` longtext NOT NULL,
     *   `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
     *   PRIMARY KEY (`id`),
     *   UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
     * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
     */
    'QUEUE_FAILED_TABLE' => 'failed_jobs',

    /**
     * 失败任务驱动
     */
    'QUEUE_FAILED_DRIVER' => 'database',

    /**
     * 失败任务数据库连接
     */
    'QUEUE_FAILED_DATABASE' => 'default',

    // ========================================================================
    // 队列专用数据库连接配置（避免污染全局 DB_* 配置）
    // ========================================================================

    /**
     * 队列数据库连接名称（使用应用的数据库连接）
     *
     * 注意：这里配置的是连接名称，不是具体的连接参数
     * 具体的连接参数应该在应用的主数据库配置中设置
     */
    'QUEUE_DB_CONNECTION' => null,

    // ========================================================================
    // 队列专用 Redis 配置（避免污染全局 REDIS_* 配置）
    // ========================================================================

    /**
     * 队列 Redis 连接名称（使用应用的 Redis 连接）
     *
     * 注意：这里配置的是连接名称，不是具体的连接参数
     * 具体的连接参数应该在应用的主 Redis 配置中设置
     */
    'QUEUE_REDIS_CONNECTION' => 'default',

    // ========================================================================
    // Worker 进程配置（用于 queue:work 命令）
    // ========================================================================

    /**
     * Worker 每次处理的任务数量后重启（防止内存泄漏）
     * 0 表示不限制
     */
    'QUEUE_MAX_JOBS' => 0,

    /**
     * Worker 运行时间后重启（秒）
     * 0 表示不限制
     */
    'QUEUE_MAX_TIME' => 0,

    /**
     * Worker 休眠时间（秒），当没有任务时
     */
    'QUEUE_SLEEP' => 3,

    /**
     * 任务最大尝试次数
     */
    'QUEUE_TRIES' => 3,

    /**
     * 任务超时时间（秒）
     */
    'QUEUE_TIMEOUT' => 60,
];
