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

namespace Think\Console\Command\Tools;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Input\Option;
use Think\Console\Output;
use Think\Exception;
use Think\Storage;

class Comment extends Command
{
    private $header = '';

    protected function configure()
    {
        $this->setName('tools:comment')
            ->addOption('dir', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要处理的目录')
            ->addOption('recursive', 'R|r', Option::VALUE_NONE, '是否递归处理指定的目录，需要与--dir联合使用')
            ->addOption('exclude', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要排除的文件，需要和--dir联合使用')
            ->addOption('exclude-dir', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要排除的目录，需要和--dir联合使用')
            ->addOption('file', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要处理的文件')
            ->addOption('project', null, Option::VALUE_OPTIONAL, '项目名', '简易CMS')
            ->addOption('author', null, Option::VALUE_OPTIONAL, '作者', '果农')
            ->addOption('email', null, Option::VALUE_OPTIONAL, '邮箱', 'hoping@imzhp.com')
            ->addOption('url', null, Option::VALUE_OPTIONAL, '网址', 'https://www.imzhp.com')
            /**
             * recursive
             * Recursively search subdirectories listed.
             *
             * exclude
             * If specified, it excludes files matching the given filename pattern from the search.
             *
             * exclude-dir
             * If -R is specified, it excludes directories matching the given filename pattern from the search.
             */
            ->addOption('tplfile', null, Option::VALUE_OPTIONAL, '文件头模板文件')
            ->setDescription('自动生成php文件头。添加一些常用的如作者信息等');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $header = <<<str
<?php
// +-----------------------------------------------------------------------------------------------
// | {project}
// +-----------------------------------------------------------------------------------------------
// | [请手动修改文件描述]
// +-----------------------------------------------------------------------------------------------
// | Author: {author} <{email}> <{url}>
// +-----------------------------------------------------------------------------------------------
// | Version \$Id: {filename} 2 {filetime} {author} <{email}> \$
// +-----------------------------------------------------------------------------------------------
str;

        $tplfile = $input->getOption('tplfile');
        if ($tplfile) {
            if (!file_exists($tplfile)) {
                throw new Exception('文件 ' . $tplfile . ' 不存在', 404);
            }
            $header = file_get_contents($tplfile);
        }
        $set = [
            'project',
            'email',
            'author',
            'url'
        ];
        foreach ($set as $k) {
            $val    = $input->getOption($k);
            $header = str_replace('{' . $k . '}', $val, $header);
        }
        $this->header = trim($header);
        if (substr($this->header, 0, 2) !== '<?') {
            $this->header = '<?php' . PHP_EOL . $this->header;
        }
        $dirs  = $input->getOption('dir');
        $files = $input->getOption('file');
        if ($dirs) {
            $output->writeln('要处理的目录：<info>' . implode(',', $dirs) . '</info>');
            $isRecursive = $input->getOption('recursive');
            if ($isRecursive) {
                $output->writeln('<comment>递归检索文件</comment>');
            }
            $excludeFiles = $input->getOption('exclude');
            $excludeDirs  = $input->getOption('exclude-dir');
            foreach ($dirs as $path) {
                $result = Storage::listFiles($path, '.php', $isRecursive, true, $excludeDirs, $excludeFiles);
                if ($result) {
                    $files = array_merge($files, $result);
                }
            }
        }
//        foreach($files as &$file) {
//            $file = realpath($file);
//        }
        asort($files);
        $files = array_unique($files);
        $files = array_values($files);
        if ($files) {
            $total = count($files);
            $output->info('本次共需要处理 ' . $total . '个文件');
            $start     = time() - 10;
            $processed = 0;
            foreach ($files as $file) {
                $processed++;
                $file = str_replace(ROOT_PATH, './', $file);
//                $output->writeln($processed . ' : ' . $file);
                $this->processFile($file);
                $output->writeln(showTimeTask($total, $start, $processed));
            }
        }
        $output->writeln('<info>Succeed!</info>');
    }

    private function processFile($file)
    {
        $tmp     = $lines = file($file);
        $header  = trim($this->header);
        $header  = str_replace('{filename}', basename($file), $header);
        $header  = str_replace('{filetime}', date('Y-m-d H:i:s', filectime($file)), $header);
        $finded  = FALSE;
        $comment = false;
        foreach ($tmp as $k => $v) {
            $v = trim($v);
            if (!$v) {
                if ($comment) break;
                unset($lines[$k]);
                continue;
            }
            $str = trim(str_replace(array('<?php', '<?'), '', $v));
            if (!$str) {
                $finded = true;
                unset($lines[$k]);
                if ($comment) {
                    break;
                }
                continue;
            }
            $first = substr($str, 0, 2);
            if ($first == '//' || $first == '/*' || substr($first, 0, 1) == '*') {
                if (!$comment) {
                    $this->showVerboseInfo($file . ' 已经有注释信息了');
                }
                unset($lines[$k]);
                $comment = true;
            }
            else {
                if ($comment) {
                    $this->showVerboseInfo('原文注释在第 ' . $k . ' 行结束');
                }
                break;
            }
        }
        if ($finded) {
            foreach ($lines as $k => $v) {
                if (!trim($v)) unset($lines[$k]);
                else break;
            }
            array_unshift($lines, $header . PHP_EOL . PHP_EOL);
            $content = implode("", $lines);
            $old     = implode("", $tmp);
            if (preg_replace('/\s*\R+\s*/u', '', $old) == preg_replace('/\s*\R+\s*/u', '', $content)) {
                $this->output->writeln('<comment>文件内容未变化</comment>');
            }
            else {
                file_put_contents($file, $content);
            }
        }
        else {
            $this->output->error('File:' . $file . ' Content is wrong!', 'error');
        }
    }
}
