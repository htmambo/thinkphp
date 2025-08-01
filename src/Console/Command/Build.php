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
use Think\Build as AppBuild;

class Build extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('build')
            ->setDefinition([
                new Option('module', null, Option::VALUE_OPTIONAL, L('Module name'), C('DEFAULT_MODULE')),
            ])
            ->setDescription('Build Application Dirs');
    }

    protected function execute(Input $input, Output $output)
    {
        $module = parse_name($input->getOption('module'), 1);
        $output->writeln('build ' . $module);
        AppBuild::buildAppDir($module);
        $output->writeln(L("Build Application {$module} is Successed", ['module' => $module]));
        return;
    }
}
