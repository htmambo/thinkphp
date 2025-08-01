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
 * Class Checkbox
 * @package HS\Html\Field
 *
 * @method Checkbox checked(boolean $ischecked) 是否选中
 * @method Checkbox value(anything $value) 选时传回的值
 * @method Checkbox disabled(boolean $isdisabled) 只显示，不允许操作
 */
class Checkbox
{
    use Field;

    protected function _build($value = null)
    {
        return '<div class="hs-checkboxes">' . $this->input('checkbox', $value) . '</div>';
    }
}