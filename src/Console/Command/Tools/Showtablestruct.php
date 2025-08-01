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
use Think\Console\Output;
use Think\Console\Table;

class Showtablestruct extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('tools:showtablestruct');
        // 设置参数
        $this->addOption('table', null, Input\Option::VALUE_OPTIONAL|Input\Option::VALUE_IS_ARRAY, '表名')
            ->addOption('type', null, Input\Option::VALUE_OPTIONAL, '输出格式：box=表格，double=双线表格，sql=SQL语句，markdown=Markdown文本', 'box')
            ->setDescription('显示指定表名的结构');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $tables = $input->getOption('table');
        if (!$tables) {
            throw new \InvalidArgumentException('请输入要检测的表名');
        }

        $outtype = $input->getOption('type');
        $outtype = strtolower($outtype);
        // if(!in_array($outtype, ['table', 'sql', 'md'])) $outtype = 'table';
        foreach($tables as $table) {
            $this->showTableStruct($table, $outtype);
        }
    }

    private function showTableStruct($table, $outtype)
    {
        $model  = M($table);
        $table  = $model->getTableName();
        $sql    = 'SHOW CREATE TABLE ' . $table;
        $struct = $model->query($sql);
        if ($struct === false) {
            $this->output->error(['系统错误：' . $model->getError()]);
            return;
        }
        $tObj      = new Table();
        $match     = [];
        $tbcomment = '';
        if (preg_match('/ENGINE=.+? COMMENT=\'(.+?)\'/iU', $struct[0]['create table'], $match)) {
            $tbcomment = $match[1];
        }
        $this->output->writeln('表名：<info>' . $table . '</info>');
        if ($tbcomment) {
            $this->output->writeln('注释：<comment>' . $tbcomment . '</comment>');
        }
        if ($outtype == 'sql') {
            $this->output->writeln($struct[0]['create table']);
            return;
        }
        $indexes = $model->query('SHOW KEYS FROM ' . $table);
        $pri     = [];
        foreach ($indexes as $arr_keys) {
            if ($arr_keys['key_name'] == 'PRIMARY') {
                $pri[] = $arr_keys['column_name'];
            }
        }
        $fields = [];
        $keys   = $model->query('SHOW FULL COLUMNS FROM ' . $table);
        foreach ($keys as $arr_field) {
            $name          = $arr_field['field'];
            $type          = $arr_field['type'];
            $not_null      = $arr_field['null'];
            $default_value = $arr_field['default'];
            $comment       = $arr_field['comment'];
            if ($pri && in_array($name, $pri)) {
                $key_value = "主键";
            }
            else {
                $key_value = "";
            }
            if ($key_value != "") {
                $not_null = $key_value;
            }
            else {
                $not_null = (strtolower($not_null) == 'no') ? '否' : '是';
            }

            //type
            $type = preg_replace('@BINARY@', '', $type);
            $type = preg_replace('@ZEROFILL@', '', $type);
            $type = preg_replace('@UNSIGNED@', '', $type);
            $len  = '';
            if (preg_match('@\(([^)]+)\)@', $type, $match)) {
                $len  = $match[1];
                $pos  = strpos($type, $match[0]);
                $type = substr($type, 0, $pos);
            }

            $fields[$name] = [
                'name' => $name,
                'type' => $type,
                'len' => $len,
                'default_value' => $default_value,
                'not_null' => $not_null,
                'comment' => $comment
            ];
        }
        $header = [
            'name' => '名称',
            'type' => '类型',
            'len' => '长度',
            'default_value' => '默认值',
            'not_null' => '不能为空',
            'comment' => '注释',
        ];

        $tObj->setStyle($outtype);
        $tObj->setHeader($header, Table::ALIGN_CENTER);
        $tObj->setRows($fields);
        $this->output->writeln($tObj->render());

    }
}
