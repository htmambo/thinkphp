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

namespace Think\Form\Field;
use Think\Form\Traits\Field;

/**
 * Class Radio
 * @package HS\Html\Field
 *
 * @method Radio checked(boolean $ischecked) 是否选中
 * @method Radio value(anything $value) 选时传回的值
 * @method Radio disabled(boolean $isdisabled) 只显示，不允许操作
 * @method Radio title(string $title) 单选项标题
 */
class Radio
{
    use Field;

    protected function _build($value = null)
    {
        return '<div class="hs-radios">' . $this->input('radio', $value) . '</div>';
    }
}