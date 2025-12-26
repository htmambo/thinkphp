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

namespace Think\Migration\Db;

use Phinx\Db\Table\Index;

class Table extends \Phinx\Db\Table
{
    /**
     * 设置自增 ID 字段名
     *
     * @param string|int|bool $id ID 字段名，false 表示禁用自增 ID
     * @return $this
     */
    public function setId($id)
    {
        $this->options['id'] = $id;
        return $this;
    }

    /**
     * 设置主键
     *
     * @param string|array $key 主键字段名或字段名数组
     * @return $this
     */
    public function setPrimaryKey($key)
    {
        $this->options['primary_key'] = $key;
        return $this;
    }

    /**
     * 设置存储引擎
     *
     * @param string $engine 存储引擎名称（如：InnoDB、MyISAM）
     * @return $this
     */
    public function setEngine($engine)
    {
        $this->options['engine'] = $engine;
        return $this;
    }

    /**
     * 设置表注释
     *
     * @param string $comment 表注释内容
     * @return $this
     */
    public function setComment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 设置排序规则
     *
     * @param string $collation 排序规则（如：utf8mb4_unicode_ci）
     * @return $this
     */
    public function setCollation($collation)
    {
        $this->options['collation'] = $collation;
        return $this;
    }

    /**
     * 添加软删除列
     *
     * 创建一个 delete_time 时间戳列，用于实现软删除功能
     *
     * @return $this
     */
    public function addSoftDelete()
    {
        $this->addColumn(Column::timestamp('delete_time')->setNullable());
        return $this;
    }

    /**
     * 添加多态关联列
     *
     * 创建 {name}_id 和 {name}_type 两个列，并添加联合索引
     * 用于实现多态关联（如评论属于多种模型）
     *
     * @param string $name 多态关联名称
     * @param string|null $indexName 索引名称，为空则自动生成
     * @return $this
     */
    public function addMorphs($name, $indexName = null)
    {
        $this->addColumn(Column::unsignedInteger("{$name}_id"));
        $this->addColumn(Column::string("{$name}_type"));
        $this->addIndex(["{$name}_id", "{$name}_type"], ['name' => $indexName]);
        return $this;
    }

    /**
     * 添加可空多态关联列
     *
     * 与 addMorphs 类似，但创建的列允许为 NULL
     *
     * @param string $name 多态关联名称
     * @param string|null $indexName 索引名称，为空则自动生成
     * @return $this
     */
    public function addNullableMorphs($name, $indexName = null)
    {
        $this->addColumn(Column::unsignedInteger("{$name}_id")->setNullable());
        $this->addColumn(Column::string("{$name}_type")->setNullable());
        $this->addIndex(["{$name}_id", "{$name}_type"], ['name' => $indexName]);
        return $this;
    }
    //
    ///**
    // * @param string $createdAtColumnName
    // * @param string $updatedAtColumnName
    // * @return \Phinx\Db\Table|Table
    // */
    //public function addTimestamps($createdAtColumnName = 'create_time', $updatedAtColumnName = 'update_time')
    //{
    //    return parent::addTimestamps($createdAtColumnName, $updatedAtColumnName);
    //}

    /**
     * @param \Phinx\Db\Table\Column|string $columnName
     * @param null $type
     * @param array $options
     * @return \Phinx\Db\Table|Table
     */
    public function addColumn($columnName, $type = null, $options = [])
    {
        if ($columnName instanceof Column && $columnName->getUnique()) {
            $index = new Index();
            $index->setColumns([$columnName->getName()]);
            $index->setType(Index::UNIQUE);
            $this->addIndex($index);
        }
        return parent::addColumn($columnName, $type, $options);
    }

    /**
     * @param string $columnName
     * @param null $newColumnType
     * @param array $options
     * @return \Phinx\Db\Table|Table
     */
    public function changeColumn($columnName, $newColumnType = null, $options = [])
    {
        if ($columnName instanceof \Phinx\Db\Table\Column) {
            return parent::changeColumn($columnName->getName(), $columnName, $options);
        }
        return parent::changeColumn($columnName, $newColumnType, $options);
    }
}
