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

namespace Think\Console\Command\Make;

use Think\Console\Command\Make;
use Think\Console\Input\Option;

class Controller extends Make
{
    protected $type = "Controller";

    protected function configure()
    {
        parent::configure();
        $this->setName('make:controller')
            ->addOption('plain', null, Option::VALUE_NONE, 'Generate an empty controller class.')
            ->setDescription('Create a new resource controller class');
    }

    protected function getStub()
    {
        $stubPath = __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR;

        if ($this->input->getOption('plain')) {
            return $stubPath . 'controller.plain.stub';
        }

        return $stubPath . 'controller.stub';
    }

    protected function getClassName($name)
    {
        return parent::getClassName($name) . (C('CONTROLLER_SUFFIX') ? ucfirst(C('CONTROLLER_SUFFIX')) : '');
    }

    protected function getNamespace($appNamespace, $module)
    {
        return parent::getNamespace($appNamespace, $module) . '\controller';
    }

}
