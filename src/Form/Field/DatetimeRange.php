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
 * Class DatetimeRange
 * @package HS\Html\Field
 *
 * @method DatetimeRange splitchr(string $chr)     //分隔字符
 */
class DatetimeRange extends Datetime
{
    protected function _build($value = null) {
        if(isset($this->options['splitchr'])) {
            $this->options['data-range'] = $this->options['splitchr'];
            unset($this->options['splitchr']);
        } else {
            $this->options['data-range'] = true;
        }
        return parent::_build($value);
    }
}