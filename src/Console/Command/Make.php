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
use Think\Console\Input\Argument;
use Think\Console\Output;
use Think\Facade\App;
use Think\Facade\Config;
use Think\Facade\Env;

abstract class Make extends Command
{
    protected $type;

    abstract protected function getStub();

    protected function configure()
    {
        $this->addArgument('name', Argument::REQUIRED, "The name of the class");
    }

    protected function execute(Input $input, Output $output)
    {

        $name = trim($input->getArgument('name'));

        $classname = $this->getClassName($name);

        $pathname = $this->getPathName($classname);

        if (is_file($pathname)) {
            $output->writeln('<error>' . $this->type . ' already exists!</error>');
            return false;
        }

        if (!is_dir(dirname($pathname))) {
            mkdir(dirname($pathname), 0755, true);
        }

        file_put_contents($pathname, $this->buildClass($classname));

        $output->writeln('<info>' . $this->type . ' created successfully.</info>');

    }

    protected function buildClass($name)
    {
        $stub = file_get_contents($this->getStub());

        $namespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        $class = str_replace($namespace . '\\', '', $name);

        return str_replace(
            ['{%className%}', '{%actionSuffix%}', '{%namespace%}'],
            [$class, C('ACTION_SUFFIX'), $namespace],
            $stub
        );
    }

    protected function getPathName($name)
    {
        $root = rtrim(C('APP_PATH'), '/') . '/';
        $name = ltrim(str_replace('\\', '/', $name), '/');
        return $root . $name . '.php';
    }

    protected function getClassName($name)
    {
        $appNamespace = $this->getModuleName();

        if ($appNamespace && strpos($name, $appNamespace . '\\') !== false) {
            return $name;
        }
        $module = '';
        if (C('MULTI_MODULE')) {
            if (strpos($name, '/')) {
                list($module, $name) = explode('/', $name, 2);
                $module = ucwords($module);
                $name   = ucwords($name);
            }
            if ($appNamespace && $module) {
                $module = $appNamespace . '\\' . $module;
            }
            else if ($appNamespace) {
                $module = $appNamespace;
            }
        }

        if (strpos($name, '/') !== false) {
            $name = str_replace('/', '\\', $name);
        }

        return ($module ? $module . '\\' : '') . $name;
    }

    protected function getModuleName()
    {
        return '';
    }
}
