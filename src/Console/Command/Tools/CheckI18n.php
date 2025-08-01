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
use Think\Helper\ThinkPhpPrinter;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\PhpVersion;
use Think\Translate;

class CheckI18n extends Command
{

    protected function configure()
    {
        $this->setName('tools:checki18n')
            ->addOption('dir', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要处理的目录')
            ->addOption('recursive', 'R|r', Option::VALUE_NONE, '是否递归处理指定的目录，需要与--dir联合使用')
            ->addOption('exclude', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要排除的文件，需要和--dir联合使用')
            ->addOption('exclude-dir', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要排除的目录，需要和--dir联合使用')
            ->addOption('file', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要处理的文件')
            ->addOption('rewrite', null, Option::VALUE_NONE, '如果检测到需要更新语言包时是否回填原文件')
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
            ->setDescription('检查并处理指定目录/文件中的中文内容并更新语言包');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $dirs  = $input->getOption('dir');
        $files = $input->getOption('file');
        $rewrite = $input->getOption('rewrite');
        $excludeFiles = $input->getOption('exclude');
        $excludeFiles[] = 'Log.php';
        $excludeFiles[] = 'Str.php';
        $excludeFiles[] = 'Verify.php';
        $excludeFiles[] = 'ThinkPhpPrinter.php';
        $excludeFiles[] = 'Build.php';

        $excludeDirs  = $input->getOption('exclude-dir');
        $excludeDirs[] = 'Lang';
        $excludeDirs[] = 'Tpl';
        if ($dirs) {
            $output->writeln('要处理的目录：<info>' . implode(',', $dirs) . '</info>');
            $isRecursive = $input->getOption('recursive');
            if ($isRecursive) {
                $output->writeln('<comment>递归检索文件</comment>');
            }
            foreach ($dirs as $path) {
                $result = Storage::listFiles($path, '.php', $isRecursive, true, $excludeDirs, $excludeFiles);
                if ($result) {
                    $files = array_merge($files, $result);
                }
            }
        }
        asort($files);
        $files = array_unique($files);
        $files = array_values($files);
        if ($files) {
            global $lang;
            $oldlang = $lang = L();
            $factory = new ParserFactory();
            $parser = $factory->createForVersion(PhpVersion::fromString("7.4"));
            $traverser = new NodeTraverser();
            $mychecker = new MyChecker($output);
            $transObj = new Translate('google');
            // $transObj = '';
            $prettyPrinter = new ThinkPhpPrinter(['translator' => $transObj]);
            $total = count($files);
            $output->info('本次共需要处理 ' . $total . '个文件');
            $start     = time() - 10;
            $processed = 0;
            foreach ($files as $file) {
                $processed++;
                $file = str_replace(ROOT_PATH, './', $file);
                $output->writeln($processed . ' : ' . $file);
                $this->processFile($file);
                
                $code = file_get_contents($file);
                $ast = $parser->parse($code);

                $content = $prettyPrinter->prettyPrintFile($ast);
                // $output->writeln($content);

                $ast = $parser->parse($content);
                $output->writeln(str_repeat('=', 40));
                $output->writeln('检测一下替换后是否还有中文');
                $traverser->addVisitor($mychecker);
                $ast = $traverser->traverse($ast);
                if(count($lang) !== count($oldlang)) {
                    $output->warning('需要更新语言包');
                    foreach($lang as $k => $v) {
                        if(!isset($oldlang[$k])) {
                            $output->writeln($k . ' => ' . $v);
                        }
                    }
                    $lang = $oldlang;
                    if($rewrite) {
                        $output->writeln(str_repeat('=', 40));
                        $output->writeln('回填文件：' . $file);
                        $result = file_put_contents($file, $content);
                    }
                }
                $output->writeln(showTimeTask($total, $start, $processed));
            }
        }
        $output->writeln('<info>Succeed!</info>');
    }

    private function processFile($file)
    {
    }
}

class MyChecker extends NodeVisitorAbstract
{
    private $output;
    public function __construct($output) {
        $this->output = $output;
    }

    public function leaveNode(Node $node)
    {
        if ((
            $node instanceof PhpParser\Node\Scalar\String_
            || $node instanceof Node\Scalar\String_
            // || get_class($node) === 'PhpParser\Node\Scalar\String_'
            ) && preg_match('@(\p{Han}){1,}@u', $node->value)) {
            $startLine = $node->getLine();
            $this->output->writeln("<error>第[{$startLine}]行：</error>" . $node->value);
        }
    }

}

