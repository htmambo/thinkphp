<?php

declare(strict_types=1);

namespace Think\Queue\Commands;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Output;
use Think\Console\Input\Option;
use Think\Container;

/**
 * Queue Worker 命令
 *
 * 处理队列任务
 *
 * @package Think\Queue\Commands
 */
class QueueWorkCommand extends Command
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

        $this->setName('queue:work')
            ->setDescription('处理队列任务 (queue:work)')
            ->addOption('connection', 'c', Option::VALUE_OPTIONAL, '队列连接名称', $defaultConnection)
            ->addOption('queue', 'Q', Option::VALUE_OPTIONAL, '队列名称（逗号分隔）', $defaultQueue)
            ->addOption('once', 'o', Option::VALUE_NONE, '只处理一个任务后退出')
            ->addOption('sleep', 's', Option::VALUE_OPTIONAL, '没有任务时休眠时间（秒）', 3)
            ->addOption('jobs', 'j', Option::VALUE_OPTIONAL, '处理多少个任务后退出（0不限制）', 0)
            ->addOption('max-time', 'm', Option::VALUE_OPTIONAL, '运行多少秒后退出（0不限制）', 0);
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

        // 获取队列管理器
        $queueManager = $container->make('queue');

        // 获取选项
        $connection = (string)$input->getOption('connection');
        $queueNames = (string)$input->getOption('queue');
        $sleep = (int)$input->getOption('sleep');
        $maxJobs = (int)$input->getOption('jobs');
        $maxTime = (int)$input->getOption('max-time');
        $once = (bool)$input->getOption('once');

        // 解析队列名称（支持逗号分隔）
        $queues = array_values(array_filter(array_map('trim', explode(',', $queueNames))));
        if ($queues === []) {
            $queues = ['default'];
        }

        $output->writeln('<info>Queue Worker 启动</info>');
        $output->writeln('连接: <comment>' . $connection . '</comment>');
        $output->writeln('队列: <comment>' . implode(',', $queues) . '</comment>');

        if ($once) {
            $output->writeln('模式: <info>单次运行</info>');
        } else {
            $output->writeln('模式: <info>持续运行</info>');
            $output->writeln('按 Ctrl+C 停止');
        }

        $output->writeln('');

        // 获取队列连接
        $queue = $queueManager->connection($connection);

        // 重启信号文件路径
        $runtimeRoot = defined('RUNTIME_PATH') ? RUNTIME_PATH : (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'thinkphp');
        $restartFile = realpath($runtimeRoot) ? (rtrim(realpath($runtimeRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR . 'restart') : null;
        $restartAt = $restartFile && is_file($restartFile) ? (int)@file_get_contents($restartFile) : 0;

        // Worker 统计
        $processed = 0;
        $startedAt = time();

        // Worker 主循环
        while (true) {
            // 检查重启信号
            if ($restartFile && is_file($restartFile)) {
                $currentRestartAt = (int)@file_get_contents($restartFile);
                if ($currentRestartAt > $restartAt) {
                    $output->writeln('<comment>检测到重启信号，Worker 退出以便重启</comment>');
                    break;
                }
            }

            // 从队列中取出任务
            $job = null;
            foreach ($queues as $q) {
                try {
                    $job = $queue->pop($q);
                    if ($job) {
                        break;
                    }
                } catch (\Exception $e) {
                    $output->writeln('<error>从队列取任务失败: ' . $e->getMessage() . '</error>');
                    // 继续尝试下一个队列
                }
            }

            if ($job) {
                // 处理任务
                try {
                    if ($job instanceof \Illuminate\Contracts\Queue\Job) {
                        $output->writeln('<comment>处理任务:</comment> ' . get_class($job));

                        // 执行任务
                        $job->fire();

                        // 任务成功完成，从队列中删除（如果尚未被删除或释放）
                        // 注意：job 可能已经通过 release() 被重新排队，所以不要强制删除
                        if (!$this->jobHasBeenDeletedOrReleased($job)) {
                            $job->delete();
                        }

                        $processed++;
                        $output->writeln('<info>任务完成</info>');
                    } else {
                        $output->writeln('<error>任务类型不支持: ' . get_class($job) . '</error>');
                    }
                } catch (\Throwable $e) {
                    $output->writeln('<error>任务执行异常: ' . $e->getMessage() . '</error>');

                    // 检查任务是否超过最大尝试次数
                    $maxTries = $this->getJobMaxTries($job);
                    $attempts = $job->attempts();

                    if ($attempts >= $maxTries) {
                        // 超过最大尝试次数，标记任务为失败并删除
                        $output->writeln('<error>任务失败，已达最大尝试次数 (' . $maxTries . ')</error>');

                        // 使用 Illuminate 的 fail() 方法处理失败
                        if (method_exists($job, 'fail')) {
                            $job->fail($e);
                        } else {
                            // 如果没有 fail 方法，手动处理
                            $job->delete();

                            // 记录到失败任务表（使用 QueueManager 的 failed job 处理）
                            try {
                                $queueManager = $container->get('queue');
                                if (method_exists($queueManager, 'failed')) {
                                    $queueManager->failed($job);
                                }
                            } catch (\Throwable $failException) {
                                $output->writeln('<comment>警告：无法记录失败任务: ' . $failException->getMessage() . '</comment>');
                            }
                        }
                    } else {
                        // 未超过最大尝试次数，释放任务以便稍后重试
                        $delay = $this->getJobBackoff($job, $attempts);
                        $output->writeln('<comment>任务将在 ' . $delay . ' 秒后重试 (尝试 ' . ($attempts + 1) . '/' . $maxTries . ')</comment>');

                        if (method_exists($job, 'release')) {
                            $job->release($delay);
                        } else {
                            // 如果没有 release 方法，直接删除
                            $job->delete();
                        }
                    }

                    // 如果是单次运行且任务失败，返回错误
                    if ($once) {
                        return 1;
                    }
                }

                // 检查是否只运行一次
                if ($once) {
                    break;
                }
            } else {
                // 没有任务
                if ($once) {
                    $output->writeln('<comment>队列为空，没有任务需要处理</comment>');
                    break;
                }

                // 休眠指定秒数
                if ($sleep > 0) {
                    sleep($sleep);
                }
            }

            // 检查最大任务数
            if ($maxJobs > 0 && $processed >= $maxJobs) {
                $output->writeln('<info>已处理 ' . $processed . ' 个任务，达到上限后退出</info>');
                break;
            }

            // 检查最大运行时间
            if ($maxTime > 0 && (time() - $startedAt) >= $maxTime) {
                $output->writeln('<info>已运行 ' . (time() - $startedAt) . ' 秒，达到上限后退出</info>');
                break;
            }
        }

        $output->writeln('<info>Worker 正常退出</info>');
        return 0;
    }

    /**
     * 检查任务是否已被删除或释放
     *
     * @param \Illuminate\Contracts\Queue\Job $job
     * @return bool
     */
    protected function jobHasBeenDeletedOrReleased($job): bool
    {
        // Illuminate Jobs 通常没有直接的 isDeleted/released 标记
        // 我们通过检查 job 是否有 `isDeleted` 或 `isReleased` 方法来判断
        if (method_exists($job, 'isDeleted') && $job->isDeleted()) {
            return true;
        }

        if (method_exists($job, 'isReleased') && $job->isReleased()) {
            return true;
        }

        return false;
    }

    /**
     * 获取任务的最大尝试次数
     *
     * @param \Illuminate\Contracts\Queue\Job $job
     * @return int
     */
    protected function getJobMaxTries($job): int
    {
        // 优先从 Job 的 payload 中获取
        if (method_exists($job, 'getMaxTries')) {
            return $job->getMaxTries() ?? 3;
        }

        // 尝试从 payload 解析
        if (method_exists($job, 'payload')) {
            $payload = $job->payload();
            if (isset($payload['maxTries'])) {
                return (int)$payload['maxTries'];
            }
        }

        // 默认值为 3
        return 3;
    }

    /**
     * 获取任务的重试延迟时间
     *
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param int $attempts 当前尝试次数
     * @return int 延迟秒数
     */
    protected function getJobBackoff($job, int $attempts): int
    {
        // 优先从 Job 获取 backoff 配置
        if (method_exists($job, 'getBackoff')) {
            $backoff = $job->getBackoff();
            if ($backoff !== null) {
                if (is_array($backoff)) {
                    return $backoff[$attempts - 1] ?? $backoff[count($backoff) - 1];
                }
                return (int)$backoff;
            }
        }

        // 尝试从 payload 解析
        if (method_exists($job, 'payload')) {
            $payload = $job->payload();
            if (isset($payload['backoff'])) {
                $backoff = $payload['backoff'];
                if (is_array($backoff)) {
                    return $backoff[$attempts - 1] ?? $backoff[count($backoff) - 1];
                }
                return (int)$backoff;
            }
        }

        // 否则使用指数退避策略：2^(attempts-1) * 5，最多 90 秒
        return min(90, (int)pow(2, $attempts - 1) * 5);
    }
}
