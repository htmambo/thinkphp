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
 * Class Switcher
 * @package HS\Html\Field
 *
 * @method Switcher yes(string $yes) 开启时的文本
 * @method Switcher no(string $no)  关闭时的文本
 * @method Switcher value(anything $value) 开启时传回的值
 * @method Switcher checked(boolean $ischecked) 是否选中
 * @method Switcher disabled(boolean $isdisabled) 只显示，不允许操作
 */
class Switcher
{
    use Field;

    protected function _build($value = null) {
        $options = $this->options;
        if(!isset($options['name'])) {
            $options['name'] = $this->name;
        }
        if (isset($options['yes']) && isset($options['no'])) {
            $options['lay-text'] = $options['yes'] . '|' . $options['no'];
        }
        if (!isset($options['disabled']) || !$options['disabled']) {
            unset($options['disabled']);
        }
        unset($options['yes'], $options['no']);
        $options['lay-skin'] = 'switch';
        $attr = $this->attributes($options);
        return [
            'content' => "<div class='hs-switcher'><input type=\"checkbox\" lay-skin=\"switch\"{$attr}></div>",
        ];

    }
}