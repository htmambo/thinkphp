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
        foreach ($paths as $path) {
            $this->clear(rtrim($root . $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $rmdir);
        }
        $output->writeln("<info>清除成功</info>");
    }

    protected function clear($path, $rmdir)
    {
        $this->output->writeln('清除 <comment>' . $path . '</comment>下的所有文件');
        $files = is_dir($path) ? scandir($path) : [];

        foreach ($files as $file) {
            if ('.' != $file && '..' != $file && is_dir($path . $file)) {
                array_map('unlink', glob($path . $file . DIRECTORY_SEPARATOR . '*.*'));
                if ($rmdir) {
                    if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                        $this->output->writeln('尝试清除 <comment>' . $path . $file . '</comment> 如果它为空的话');
                    }
                    rmdir($path . $file);
                }
            }
            elseif ('.gitignore' != $file && is_file($path . $file)) {
                if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                    $this->output->writeln('删除 <comment>' . $path . $file . '</comment>');
                }
                unlink($path . $file);
            }
        }
    }
}
