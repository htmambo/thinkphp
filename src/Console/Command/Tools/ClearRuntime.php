<?php

namespace Think\Console\Command\Tools;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Input\Option;
use Think\Console\Output;
use Think\Exception;
use Think\Storage;
use Think\Helper\ThinkPhpPrinter;
use Think\Translate;

class clearRuntime extends Command
{
    protected function configure(){
        $this->setName('tools:clearruntime')
            ->addOption('log', null, Option::VALUE_NONE, '是否清除日志')
            /**
             * dir
             * The directory to clear. Defaults to RUNTIME_PATH.
             * rescure
             * If specified, it will recursively delete all contents in the directory.
             * force
             * If specified, it will forcefully delete all contents without prompting.
             */
            ->setDescription('清理运行时缓存目录');
    }
    protected function execute(Input $input, Output $output){
        /**
         * 删除指定目录下的所有文件
         *
         * @param string $dir
         * @param bolean $rescure 是否递归删除
         */
        function deldir($dir, $rescure = true) {
            $dh = opendir($dir);
            while ( $file = readdir($dh) ) {
                if ($file !="." &&$file !="..") {
                    $fullpath = $dir ."/" .$file;
                    if (!is_dir($fullpath)) {
                        unlink($fullpath);
                    } else if ($rescure) {
                        deldir($fullpath);
                    }
                }
            }
        }
        $lists = array();
        $clearLog = $input->getOption('log');
        if ( $clearLog ) {
            $lists[] = 'Logs';
        }
        $lists [] = 'Cache';
        $lists [] = 'Temp';
        $lists [] = 'Data';
        $files = array('common~runtime.php');
        foreach ( $lists as $path ) {
            $this->output->writeln('正在清理运行时目录：' .$path);
            deldir(RUNTIME_PATH . $dir .'/' .$path);
        }
        foreach ( $files as $k ) {
            $this->output->writeln('正在清理运行时文件：' .$k);
            unlink($dir .'/' .$k);
        }

        $output->writeln("<info>已清理目录 {$dir}</info>");
    }
}