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

use Phinx\Migration\AbstractMigration;
use Think\Migration\Db\Table;

class Migrator extends AbstractMigration
{
    /**
     * 获取表操作实例
     *
     * @param string $tableName 表名
     * @param array $options 表选项
     * @return Table
     */
    public function table(string $tableName, array $options = []): Table
    {
        return new Table($tableName, $options, $this->getAdapter());
    }
}
