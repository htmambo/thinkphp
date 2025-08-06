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
 * Class Checkboxes
 * @package HS\Html\Field
 *
 * @method Checkboxes checked(boolean $ischecked) 是否选中
 * @method Checkboxes value(anything $value) 选时传回的值
 * @method Checkboxes disabled(boolean $isdisabled) 只显示，不允许操作
 * @method Checkboxes title(string $title) 单选项标题
 */
class Checkboxes
{
    use Field;

    protected function _build($value = null) {
        $list = $this->parseExtra();
        $options = $this->options;
        if(is_null($value) && $options['value']) {
            $value = $options['value'];
            unset($options['value']);
        }
        $name = $this->name;
        $html = [];
        $value = is_null($value) ? key($list) : $value;
        $value = is_array($value) ? $value : explode(',', $value);
        foreach ($list as $k => $v) {
            $opt = [
                'id' => "{$name}-{$k}",
                'name' => $name . '[]',
                'title' => $v
            ];
            if(in_array($k, $value)) {
                $opt['checked'] = true;
            }
            $html[] = $this->input('checkbox', $k, $opt);
        }
        return '<div class="hs-checkboxes">' . implode('', $html) . '</div>';
    }
}