<?php

namespace Think\Migration\Generator;

use Phinx\Db\Table\Column;
use Think\Helper\Str;
use Think\Migration\Creator;

/**
 * @property array columns
 * @property array table
 * @property array indexes
 */
abstract class AbstractGenerator
{
    protected $options;
    /**
     * @var array
     */
    protected $table;

    /**
     * @var Column[]
     */
    protected $columns;

    /**
     * @var array
     */
    protected $indexes;

    /**
     * @var array
     */
    protected $foreign_keys;

    /**
     * @return array
     */
    abstract protected function getReplaceContent(): array;

    /**
     * 获取内容
     *
     * @return string
     */
    abstract public function getMigrationContent();


    /**
     *
     * @return string
     */
    protected function head(): string
    {
        return '->addColumn';
    }

    /**
     * output
     *
     * @return mixed
     */
    public function output($onlyshow = false)
    {
        $creator = new Creator();
        list($classname, $content) = $this->getReplaceContent();
        return $creator->create(
            $classname, $content, $onlyshow
        );
    }
    /**
     * set options
     *
     * @param $value
     * @return $this
     */
    public function setOptions($value): self
    {
        $this->options = $value;
        $table = $value['name'];
        $options = $value['options'];
        $adapter = $value['adapter'];
        if($options) {
            $adapter->setOptions($options);
        }

        $this->columns = $adapter->getColumns($table);
        $this->indexes = $adapter->getIndexes($table);
        $this->foreign_keys = $adapter->getForeignKeys($table);
        $tableDef = $adapter->describeTable($table);
        $tableDef = array_change_key_case($tableDef, CASE_LOWER);
        // 数据库名称
        $tableDef['dbname'] = $options['name'];
        // 表名
        $tableDef['name'] = $table;
        foreach($tableDef as $k => $v) {
            if(is_numeric($k)) {
                unset($tableDef[$k]);
            } else if(is_string($v) && Str::startsWith($k, 'table_')) {
                $value[substr($k,  6)] = $v;
                unset($tableDef[$k]);
            }
        }
        $this->table   = $tableDef;
        return $this;
    }

    /**
     * eof
     *
     * @return string
     */
    protected function eof(): string
    {
        return "\r\n              ";
    }}
