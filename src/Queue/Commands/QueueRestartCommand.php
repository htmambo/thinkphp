<?php

declare(strict_types=1);

namespace Think\Queue\Commands;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Output;

/**
 * Queue Restart 命令
 *
 * 重启所有队列 Worker 进程
 *
 * @package Think\Queue\Commands
 */
class QueueRestartCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('queue:restart')
            ->setDescription('重启所有队列 Worker 进程 (queue:restart)');
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
        // 获取缓存目录
        $runtimeRoot = defined('RUNTIME_PATH') ? RUNTIME_PATH : (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'thinkphp');

        if (!is_dir($runtimeRoot) && !mkdir($runtimeRoot, 0755, true) && !is_dir($runtimeRoot)) {
            $output->writeln('<error>无法创建目录: ' . $runtimeRoot . '</error>');
            return 1;
        }

        $cacheDir = rtrim($runtimeRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR;

        // 创建缓存目录
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            $output->writeln('<error>无法创建目录: ' . $cacheDir . '</error>');
            return 1;
        }

        // 重启信号文件
        $restartFile = $cacheDir . 'restart';

        // 写入当前时间戳作为重启信号
        $result = file_put_contents($restartFile, (string)time());

        if ($result === false) {
            $output->writeln('<error>无法写入重启信号文件: ' . $restartFile . '</error>');
            return 1;
        }

        $output->writeln('<info>所有队列 Worker 将在完成任务后重启</info>');
        return 0;
    }
}
