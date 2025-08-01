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

class Month extends Datetime
{
    protected function _build($value = null) {
        $default = [
            'data-type' => 'month',
            'data-format' => 'MM'
        ];
        $this->options = array_merge($default, $this->options);
        return parent::_build($value);
    }
}