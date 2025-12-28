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

namespace Think\Console\Command;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\input\Option;
use Think\Console\Output;
use Think\Facade\App;
use Think\Facade\Cache;

class Clear extends Command
{
    protected function configure()
    {
        // 指令配置
        $this
            ->setName('clear')
            ->addOption('path', 'p', Option::VALUE_OPTIONAL, '要清除的路径')
            ->addOption('cache', 'c', Option::VALUE_NONE, '删除缓存文件')
            ->addOption('log', 'l', Option::VALUE_NONE, '删除日志文件')
            ->addOption('temp', 't', Option::VALUE_NONE, '删除临时文件')
            ->addOption('data', 'd', Option::VALUE_NONE, '删除数据文件')
            ->addOption('field', 'f', Option::VALUE_NONE, '删除数据表结构缓存文件')
            ->addOption('dir', 'r', Option::VALUE_NONE, '删除空目录')
            ->setDescription('清除系统生成的Runtime中的所有内容，默认只清除Runtime中的文件，不递归处理');
    }

    protected function execute(Input $input, Output $output)
    {
        $root  = C('RUNTIME_PATH');
        $paths = [];
        if ($input->getOption('cache')) {
            $paths[] = 'Cache';;
        }
        if ($input->getOption('log')) {
            $paths[] = 'Logs';
        }
        if ($input->getOption('temp')) {
            $paths[] = 'Temp';
        }
        if ($input->getOption('data')) {
            $paths[] = 'Data';
            $paths[] = 'Data/_fields';
        }
        else if ($input->getOption('field')) {
            $paths[] = 'Data/_fields';
        }

        if (!$paths) {
            $paths[] = $input->getOption('path');
        }

        $rmdir = (bool)$input->getOption('dir');
        $hasError = false;
        foreach ($paths as $path) {
            $fullPath = rtrim($root . $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!$this->clear($fullPath, $rmdir)) {
                $hasError = true;
            }
        }
        if ($hasError) {
            $output->writeln("<error>部分文件清除失败</error>");
            return 1;
        }
        $output->writeln("<info>清除成功</info>");
        return 0;
    }

    /**
     * 清除指定路径下的文件
     *
     * @param string $path 要清除的路径
     * @param bool $rmdir 是否删除空目录
     * @return bool 是否成功
     */
    protected function clear($path, $rmdir)
    {
        // 获取 Runtime 根目录的真实路径
        $runtimeRoot = realpath(C('RUNTIME_PATH'));
        if ($runtimeRoot === false) {
            $this->output->error('Runtime 路径无效，无法执行清理');
            return false;
        }

        // 获取目标路径的真实路径
        $targetPath = realpath($path);
        if ($targetPath === false) {
            // 路径不存在，跳过
            if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                $this->output->writeln('跳过不存在的路径 <comment>' . $path . '</comment>');
            }
            return true;
        }

        // 规范化路径并验证是否在 Runtime 目录下
        $runtimeRoot = rtrim($runtimeRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $targetPath  = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (strpos($targetPath, $runtimeRoot) !== 0) {
            $this->output->error('非法路径：仅允许清理 Runtime 目录下的文件');
            $this->output->writeln('尝试访问的路径: <comment>' . $targetPath . '</comment>');
            $this->output->writeln('允许的根路径: <info>' . $runtimeRoot . '</info>');
            return false;
        }

        // 使用验证后的路径
        $path = $targetPath;
        $this->output->writeln('清除 <comment>' . $path . '</comment> 下的所有文件');
        $files = is_dir($path) ? scandir($path) : [];

        foreach ($files as $file) {
            $filePath = $path . $file;

            // 跳过 . 和 ..
            if ('.' === $file || '..' === $file) {
                continue;
            }

            // 检查并跳过符号链接，防止删除 Runtime 目录外的文件
            if (is_link($filePath)) {
                if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                    $this->output->writeln('跳过符号链接 <comment>' . $filePath . '</comment>');
                }
                continue;
            }

            // 处理目录
            if (is_dir($filePath)) {
                // 递归验证子目录路径
                $realSubPath = realpath($filePath);
                if ($realSubPath && strpos($realSubPath, $runtimeRoot) !== 0) {
                    $this->output->error('发现非法子目录，跳过: <comment>' . $filePath . '</comment>');
                    continue;
                }

                // 删除目录下的文件
                array_map('unlink', glob($filePath . DIRECTORY_SEPARATOR . '*.*'));

                if ($rmdir) {
                    if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                        $this->output->writeln('尝试清除 <comment>' . $filePath . '</comment> 如果它为空的话');
                    }
                    rmdir($filePath);
                }
            }
            // 处理文件（排除 .gitignore）
            elseif ('.gitignore' !== $file && is_file($filePath)) {
                if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                    $this->output->writeln('删除 <comment>' . $filePath . '</comment>');
                }
                unlink($filePath);
            }
        }

        return true;
    }
}
