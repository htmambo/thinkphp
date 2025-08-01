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

namespace Think\Migration;

use InvalidArgumentException;
use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Adapter\TablePrefixAdapter;
use Think\Console\Input\Definition as InputDefinition;
use Think\Console\Input\Option as InputOption;

abstract class Command extends \Think\Console\Command
{

    /**
     * @return MysqlAdapter
     */
    public function getAdapter()
    {
        if (isset($this->adapter)) {
            return $this->adapter;
        }

        $options = $this->getDbConfig();

        $adapter = AdapterFactory::instance()->getAdapter($options['adapter'], $options);
        $adapter = AdapterFactory::instance()->getWrapper('timed', $adapter);

        if ($adapter->hasOption('table_prefix') || $adapter->hasOption('table_suffix')) {
            $adapter = AdapterFactory::instance()->getWrapper('prefix', $adapter);
        }
        $adapter->setOutput($this->output);
        $adapter->setInput($this->input);

        $this->adapter = $adapter;

        return $adapter;
    }

    /**
     * 获取数据库配置
     * @return array
     */
    protected function getDbConfig(): array
    {
        $config = [];
        foreach (C() as $k => $v) {
            if (substr($k, 0, 3) == 'DB_') {
                $k          = strtolower(substr($k, 3));
                $config[$k] = $v;
            }
        }
        $cfgMap      = [
            'adapter' => 'type',
            'host' => 'host',
            'port' => 'port',
            'user' => 'user',
            'pass' => 'pwd',
            'name' => 'name',
            'charset' => 'charset',
            'table_prefix' => 'prefix',
//            'deploy' => 'deploy_type'
        ];
        $dbConfig = [];
        foreach ($cfgMap as $k1 => $k2) {
            //懒得判断是否开启了主从配置
            $dbConfig[$k1] = explode(',', strval($config[$k2]) . ',')[0];
        }
        //参数中可能传递了新的数据库访问信息
        $optMap = [
            'adapter' => 'dbtype',
            'host' => 'dbhost',
            'port' => 'dbport',
            'name' => 'dbname',
            'user' => 'dbuser',
            'pass' => 'dbpwd',
            'charset' => 'dbcharset',
            'table_prefix' => 'table_prefix'
        ];
        foreach ($optMap as $k => $v) {
            if($this->input->hasOption($v) && $this->input->getOption($v)) {
                $dbConfig[$k] = $this->input->getOption($v);
            }
        }
        $err = [];
        foreach($dbConfig as $k => $v) {
            if($k === 'table_prefix') continue;
            if($k === 'charset' && !$v) {
                $dbConfig[$k] = 'utf8';
                continue;
            }
            if(!$v) {
                $err[] = $optMap[$k] . '未配置！';
            }
        }
        if($err) {
            $this->output->error($err);
            exit;
        }
        $table = $dbConfig['table_prefix'] . C('DB_MIGRATION_TABLE', null, 'migrations');
        $dbConfig['default_migration_table'] = $table;
        if(C('DB_NAME') && C('DB_NAME') !== $dbConfig['name']) {
            $this->output->warning('您指定了与本系统不同的数据库，系统将会自动在该数据库中检测并生成迁移用的配置表' . $table . '，如果您在执行生成文件以及迁移操作，请注意所产生的后果');
        }

        $dbConfig['version_order']           = 'creation';
        return $dbConfig;
    }

    protected function verifyMigrationDirectory(string $path)
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException(L('Migration directory "{$path}" does not exist', ['path' => $path]));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(L('Migration directory "{$path}" is not writable', ['path' => $path]));
        }
    }
    protected function createDefinition()
    {
        return new InputDefinition([
            new InputOption('dbtype', null, InputOption::VALUE_OPTIONAL, '要操作的数据库类型'),
            new InputOption('dbhost', null, InputOption::VALUE_OPTIONAL, '要操作的数据库服务器'),
            new InputOption('dbport', null, InputOption::VALUE_OPTIONAL, '要操作的数据库服务器商品'),
            new InputOption('dbuser', null, InputOption::VALUE_OPTIONAL, '要使用的数据库账号'),
            new InputOption('dbpwd', null, InputOption::VALUE_OPTIONAL, '要使用的数据库密码'),
            new InputOption('dbname', null, InputOption::VALUE_OPTIONAL, '要操作的数据库'),
            new InputOption('dbcharset', null, InputOption::VALUE_OPTIONAL, '要使用的数据库编码'),
            new InputOption('table_prefix', null, InputOption::VALUE_OPTIONAL, '表前缀'),
        ]);
    }
}
