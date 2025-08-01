<?php

namespace Think\Migration\Generator;

use Think\Helper\Str;

class TableGenerator extends AbstractGenerator
{

    /*
     * replace content
     *
     * @throws \Doctrine\DBAL\DBALException
     * @return array
     */
    protected function getReplaceContent(): array
    {
        $name   = str_replace(C('DB_PREFIX'), '', $this->table['name']);
        /**
         * 命名格式： 动作+名称+类型[+To+目标]，比如：
         *      创建一个名为user的数据表，则名称为：CreateUserTable，
         *      给user表添加一个名为username的字段：AddUsernameColumnToUserTable
         *      给user表添加一些字段：AddSomeColumnToUserTable
         */
        $classname   = 'Create' . ucfirst(Str::camel($name)) . 'Table';
        $header = [];
        $keys   = ['engine', 'collation', 'comment'];
        foreach ($keys as $k) {
            if (!empty($this->table[$k])) {
                $header[] = "'{$k}' => '{$this->table[$k]}'";
            }
        }
        $contentTpl  = <<<TPL
        \$table = \$this->table( '%s',
            [
              %s
            ]
        );
        \$table%s
              ->create();
TPL;

        $info  = implode(',' . $this->eof(), $header);
        $tmp = $this->getAutoIncrement();
        if($tmp) $info .= ',' . $this->eof() . $tmp;
        $tmp     = $this->getPrimaryKeys();
        if($tmp) $info .= ',' . $this->eof() . $tmp;

        $content = sprintf($contentTpl, $name, $info, rtrim($this->getMigrationContent()));
        return [$classname, ltrim($content)];
    }

    /**
     * 获取内容
     *
     * @return mixed|string
     */
    public function getMigrationContent()
    {
        $content = '';

        foreach ($this->columns as $column) {
            if ($column->getIdentity()) continue;
            $options  = $this->processColumnOptions($column);
            $fieldTxt = sprintf(
                "('%s', '%s', [%s])",
                $column->getName(),
                $column->getType(),
                $options
            );
            $content  .= $this->head() . $fieldTxt . $this->eof();
        }
        $content .= $this->parseIndexes();
        //$content .= $this->parseForeignKeys();
        return $content;
    }

    /**
     * @param $column Column
     * @return string
     */
    protected function processColumnOptions($column)
    {
        $options = '';
        if ($column->getLimit()) {
            $options .= sprintf("'limit' => %s,", $column->getLimit());
        }

        if ($column->getScale()) {
            $options .= "'scale' => {$column->getScale()},";
        }

        if ($column->getPrecision()) {
            $options .= "'precision' => {$column->getPrecision()},";
        }
        $isNull  = $column->isNull() ? 'true' : 'false';
        $options .= "'null' => {$isNull},";
        // signed
        $signed  = $column->getSigned() ? 'true' : 'false';
        $options .= "'signed' => {$signed},";
        //comment
        if ($column->getComment()) {
            $options .= sprintf("'comment' => '%s',", str_replace("'", "\\'", $column->getComment()));
        }
        return trim($options, ',');
    }


    /**
     * get autoincrement
     *
     * @return string
     */
    public function getAutoIncrement(): string
    {
        $autoIncrement = '';

        $autoField = $this->getAutoIncrementField();

        if ($autoField) {
            // list
            [$fieldName, $signed] = $autoField;
            $autoIncrement .= "'id' => '{$fieldName}'";
            if ($signed) {
                $autoIncrement .= ',' . $this->eof() . "'signed' => true";
            }
        }
        else {
            $autoIncrement .= "'id' => false";
        }

        return $autoIncrement;
    }

    /**
     * get primary keys
     *
     * @return string
     */
    public function getPrimaryKeys(): string
    {
        $primary = '';

        if (!isset($this->indexes['PRIMARY'])) {
            return $primary;
        }
        foreach ($this->indexes['PRIMARY']['columns'] as $column) {
            $primary .= "'{$column}',";
        }

        return $primary ? sprintf("'primary_key' => [%s]", trim($primary, ',')) : '';
    }


    /**
     *
     * @return string
     */
    public function parseIndexes(): string
    {
        $indexes = '';
        foreach ($this->indexes as $key => $index) {
            if ($key !== 'PRIMARY') {
                $indexes .= sprintf('->addIndex(%s)', $this->parseIndex($key, $index)) . $this->eof();
            }
        }

        return $indexes;
    }

    protected function parseIndex($keyname, $index): string
    {
        // column
        $_columns = '';
        foreach ($index['columns'] as $column) {
            $_columns .= "'{$column}',";
        }
        $options = '';
        // limit
        //$options .= count(array_filter($indexLengths)) ? $this->parseLimit($indexLengths, $columns) : '';
        // unique
        $options .= $index['unique'] ? "'unique' => true," : '';
        // alias name
        $options .= "'name' => '{$keyname}',";
        // type
        $options .= in_array('FULLTEXT', $index['types']) ? "'type' => 'fulltext'," : '';

        return sprintf('[%s], [%s]', trim($_columns, ','), trim($options, ','));
    }

    /**
     * get autoincrement field
     *
     * @return array|null
     */
    protected function getAutoIncrementField(): ?array
    {
        foreach ($this->columns as $column) {
            if ($column->getIdentity()) {
                return [$column->getName(), $column->getSigned()];
            }
        }

        return null;
    }

    protected function parseForeignKeys()
    {
        return (new ForeignKeyGenerator())->setOptions($this->options)->parseForeignKeys();
    }

}
