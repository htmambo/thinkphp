<?php

declare(strict_types=1);

namespace Think\Migration\Command\Migrate;

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\TablePrefixAdapter;
use Think\Console\Input\Option;
use Think\Migration\Command;
use Think\Migration\Generator\TableGenerator;
use Think\Migration\Generator\ForeignKeyGenerator;
use Think\Console\Input;
use Think\Console\Output;

class Generator extends Command
{

    protected function configure()
    {
        // 指令配置
        $this->setName('migrate:generate')
            ->setDefinition($this->createDefinition())
            ->addOption('onlyshow', null, Option::VALUE_NONE, '是否只显示内容')
            ->addOption('table', null, Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, '要处理的数据表名')
            ->setDescription('生成现有数据表的migrate脚本，请注意表名中不能带有已经设置的前缀！');
    }

    protected function execute(Input $input, Output $output)
    {
        $onlyshow = $input->getOption('onlyshow');
        $genObj   = new TableGenerator();
        $sets     = $input->getOption('table');
        $tables   = [];
        if ($sets) {
            /**
             * Phinx的adapter并未向所有子程序、子类传递，所以这里提前给把要处理的数据取出来
             *
             * @var MysqlAdapter
             */
            $adapter = $this->getAdapter();

            $orig = $adapter->getOptions();
            foreach ($sets as $table) {
                $tableName = $table;
                $options   = $orig;
                if (strpos($table, '.')) {
                    list($dbName, $tableName) = explode('.', $table, 2);
                    if ($dbName !== $orig['name']) {
                        if (!$onlyshow) {
                            $output->warning('您指定了另外一个数据库，本次不再生成文件，只做展示！');
                            $onlyshow = true;
                        }
                        //如果指定的数据库，那么就认为是完整的表名！
                        $options['table_prefix'] = '';
                        $options['name']         = $dbName;
                    }
                }
                $tableDef = [
                    'name' => $tableName,
                    'adapter' => $adapter,
                    'options' => $options
                ];
                $tables[] = $tableDef;
            }
        }

        foreach ($tables as $table) {
            $content = $genObj->setOptions($table)->output($onlyshow);
            if ($onlyshow) {
                $output->writeln($content);
            }
            else {
                $output->info(sprintf('%s table migration file %s generated', $table['name'], str_replace(ROOT_PATH, '', $content)));
            }
        }
        //处理外键
        //foreach ($tables as $key => $table) {
        //    $tableForeign = (new ForeignKeyGenerator())->setOptions($table);
        //    if ($tableForeign->hasForeignKeys()) {
        //        file_put_contents($migrationsPath . ($key + 1) * 100 . date('YmdHis') . '_' . $table->getName() . '_foreign_keys.php',
        //            $tableForeign->output());
        //    }
        //}
    }
}

