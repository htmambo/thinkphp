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
 * Class Buttons
 * @package HS\Html\Field
 *
 */
class Buttons
{
    use Field;

    protected function _build($value = null) {
        $list = $this->parseExtra();
        $options = $this->options;
        $html = [];
        foreach ($list as $opt) {
            $html[] = $this->input('button', null, $opt);
        }
        return implode("\n", $html);
    }
}