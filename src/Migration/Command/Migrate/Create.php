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

namespace Think\Migration\Command\Migrate;

use InvalidArgumentException;
use RuntimeException;
use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Input\Argument as InputArgument;
use Think\Console\Input\Option;
use Think\Console\Output;
use Think\Migration\Creator;

class Create extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migrate:create')
            ->setDescription(L('Create a new migration'))
            ->addArgument('name', InputArgument::REQUIRED, L('What is the name of the migration?'))
            ->addOption('onlyshow', null, Option::VALUE_NONE, '是否只显示内容')
            ->setHelp(L('Creates a new database migration'));
    }

    /**
     * Create the new migration.
     *
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function execute(Input $input, Output $output)
    {
        $creator = new Creator();

        $className = $input->getArgument('name');

        $path = $creator->create($className);

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', realpath($path)));
    }

}
