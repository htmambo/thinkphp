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
 * Class Radios
 * @package HS\Html\Field
 *
 * @method Radios list(array $options) 选项列表
 */
class Radios
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
        $value = current($value);
        $other = [];
        foreach ($options as $k => $v) {
            if(substr($k, 0, 8) == 'data-lay') {
                $k = substr($k, 5);
                $other[$k] = $v;
            } else if (substr($k, 0, 5) == 'data-') {
                $other[$k] = $v;
            }
        }
        foreach ($list as $k => $v) {
            $opt = [
                'id' => "{$name}-{$k}",
                'name' => $name,
                'title' => $v,
            ];
            if($other) {
                $opt = array_merge($opt, $other);
            }
            if($k == $value) {
                $opt['checked'] = true;
            }
            $html[] = $this->input('radio', $k, $opt);
        }
        return '<div class="hs-radios">' . implode('', $html) . '</div>';
    }
}