<?php

declare(strict_types=1);

namespace Think\Queue\Commands;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Output;
use Think\Console\Input\Option;
use Think\Container;

/**
 * Queue List 命令
 *
 * 查看队列中的任务列表
 *
 * @package Think\Queue\Commands
 */
class QueueListCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure()
    {
        // 从配置读取默认连接名称
        $defaultConnection = 'sync';
        if (function_exists('C')) {
            $defaultConnection = C('QUEUE_DEFAULT') ?: 'sync';
        }

        // 从配置读取默认队列名称
        $defaultQueue = 'default';
        if (function_exists('C')) {
            $defaultQueue = C('QUEUE_QUEUE') ?: 'default';
        }

        $this->setName('queue:list')
            ->setDescription('查看队列中的任务列表 (queue:list)')
            ->addOption('connection', 'c', Option::VALUE_OPTIONAL, '队列连接名称', $defaultConnection)
            ->addOption('queue', 'Q', Option::VALUE_OPTIONAL, '队列名称', $defaultQueue)
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '显示任务数量', 100);
    }

    /**
     * 执行命令
     *
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $container = Container::getInstance();

        // 检查 Queue 服务是否注册
        if (!$container->bound('queue')) {
            $output->writeln('<error>Queue 服务未注册！</error>');
            $output->writeln('请先注册 <comment>Think\\Queue\\QueueServiceProvider</comment>');
            return 1;
        }

        // 获取队列连接名称
        $connection = (string)$input->getOption('connection');
        $queue = (string)$input->getOption('queue');
        $limit = (int)$input->getOption('limit');

        // 获取队列实例
        $queueManager = $container->make('queue');
        $queueInstance = $queueManager->connection($connection);

        // 获取队列大小
        try {
            $size = $queueInstance->size($queue);
        } catch (\Exception $e) {
            $output->writeln('<error>获取队列大小失败: ' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('队列: <comment>' . $connection . ' > ' . $queue . '</comment>');
        $output->writeln('待处理任务数: <info>' . $size . '</info>');

        if ($size === 0) {
            $output->writeln('<comment>队列为空</comment>');
            return 0;
        }

        $output->writeln('');

        // 如果是 Redis 队列，可以显示任务详情
        if ($queueInstance instanceof \Illuminate\Queue\RedisQueue) {
            $output->writeln('<comment>注意: Redis 队列不支持详细列表，仅显示任务数量</comment>');
            return 0;
        }

        // 如果是数据库队列，可以显示任务详情
        if ($queueInstance instanceof \Illuminate\Queue\DatabaseQueue) {
            $output->writeln('<info>数据库队列详情:</info>');
            $output->writeln('');

            // 获取数据库连接
            $db = \Think\Db::getInstance();

            // 获取表名
            $table = $this->getQueueTableName();
            $jobs = $db->table($table)
                ->where('queue', $queueInstance->getQueue($queue))
                ->where('reserved_at', null)
                ->order('id ASC')
                ->limit($limit)
                ->select();

            if (empty($jobs)) {
                $output->writeln('<comment>没有待处理的任务</comment>');
                return 0;
            }

            // 显示任务列表
            $output->writeln(str_repeat('-', 100));
            $output->writeln(sprintf('%-8s %-20s %-15s %-15s %-40s',
                'ID',
                'Queue',
                'Attempts',
                'Created At',
                'Job'));
            $output->writeln(str_repeat('-', 100));

            foreach ($jobs as $job) {
                $payload = json_decode($job['payload'], true);
                $displayName = $payload['displayName'] ?? 'N/A';

                $output->writeln(sprintf('%-8s %-20s %-15s %-15s %-40s',
                    $job['id'],
                    $job['queue'],
                    $job['attempts'],
                    date('Y-m-d H:i:s', $job['created_at']),
                    $displayName
                ));
            }

            $output->writeln(str_repeat('-', 100));

            if (count($jobs) >= $limit) {
                $output->writeln('<comment>显示前 ' . $limit . ' 个任务，使用 -l 选项调整显示数量</comment>');
            }
        }

        return 0;
    }

    /**
     * 获取队列任务表名
     *
     * @return string
     */
    protected function getQueueTableName(): string
    {
        // 尝试从配置获取
        if (function_exists('C')) {
            $table = C('QUEUE_TABLE');
            if ($table !== null && $table !== '') {
                return $table;
            }
        }

        return 'jobs';
    }
}
