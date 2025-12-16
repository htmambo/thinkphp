<?php

namespace Demo;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Input\Option as InputOption;
use Think\Console\Output;
use Think\Console\Table;

class Env extends Command
{
    protected function configure()
    {
        $this->setName('demo:env')
            ->setDefinition([
                new InputOption('style', 's', InputOption::VALUE_OPTIONAL, 'Table style: default|box|markdown|borderless', 'box'),
            ])
            ->setDescription('Demo: show environment info in a table');
    }

    protected function execute(Input $input, Output $output)
    {
        $table = new Table();
        $table->setHeader(['Key', 'Value']);
        $table->setStyle((string)$input->getOption('style'));

        $table->setRows([
            ['THINK_VERSION', defined('THINK_VERSION') ? THINK_VERSION : ''],
            ['PHP_VERSION', PHP_VERSION],
            ['APP_PATH', defined('APP_PATH') ? APP_PATH : ''],
            ['RUNTIME_PATH', defined('RUNTIME_PATH') ? RUNTIME_PATH : ''],
            ['CONF_PATH', defined('CONF_PATH') ? CONF_PATH : ''],
        ]);

        $output->write($table->render());

        return 0;
    }
}
