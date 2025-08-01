<?php

namespace Think\Migration\Generator;

use think\helper\Str;

class ForeignKeyGenerator extends AbstractGenerator
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
        $classname   = 'Create' . ucfirst(Str::camel($name)) . 'ForeignKey';
        $contentTpl  = <<<TPL
        \$table = \$this->table('%s');
        \$table%s
            ->save();
TPL;

        $content = sprintf($contentTpl, $name, rtrim($this->getMigrationContent()));
        return [$classname, ltrim($content)];
    }

    /**
     * 获取内容
     *
     * @return string
     */
    public function getMigrationContent()
    {
        return $this->parseForeignKeys();
    }

    public function getForeignKeys()
    {
        return !empty($this->foreign_keys) ?$this->foreign_keys: [];
    }

    /**
     * 是否有外键
     *
     * @return bool
     */
    public function hasForeignKeys()
    {
        return (bool)count($this->getForeignKeys());
    }

    /**
     * 解析外键
     *
     * @return string
     */
    public function parseForeignKeys()
    {
        $foreignKeys = $this->getForeignKeys();

        $s = '';

        foreach ($foreignKeys as $key => $foreignKeyConstraint) {
            list($delete, $update) = array_values($foreignKeyConstraint->getOptions());
            $s .= sprintf('->addForeignKey(%s, \'%s\', %s, [\'delete\' => \'%s\', \'update\' => \'%s\', \'constraint\' => \'%s\'])',
                var_export($foreignKeyConstraint->getLocalColumns(), true),
                $foreignKeyConstraint->getForeignTableName(),
                var_export($foreignKeyConstraint->getForeignColumns(), true),
                $delete ?: 'RESTRICT', $update ?: 'RESTRICT',
                $foreignKeyConstraint->getName()
            );
        }

        return $s;
    }
}
