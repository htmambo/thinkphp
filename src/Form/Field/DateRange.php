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
 * Class DateRange
 * @package HS\Html\Field
 *
 * @method DateRange splitchr(string $chr)     //分隔字符
 */
class DateRange extends DatetimeRange
{
    protected function _build($value = null) {
        $this->options['data-type'] = 'date';
        $this->options['data-format'] = 'yyyy-MM-dd';
        return parent::_build($value);
    }
}