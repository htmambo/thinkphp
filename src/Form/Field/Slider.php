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
 * Class Slider
 * @package HS\Html\Field
 *
 * @method Slider min(int $min) //滑块最小值
 * @method Slider max(int $max) //滑块最大值
 * @method Slider range(boolean $isrange)
 */
class Slider
{
    use Field;

    protected function _build($value = null)
    {
        $defaults = [
            'data-min' => 0,
            'data-max' => 100,
            'data-range' => false,
            'data-step' => 1,
            'data-showstep' => false
        ];
        $options = $this->options;
        $name = $this->name;
        $options['name'] = $name;
        foreach($defaults as $k => $v) {
            $k1 = substr($k, 5);
            if(isset($options[$k1])) {
                $defaults[$k] = $options[$k1];
                unset($options[$k1]);
            }
        }

        $options  = array_merge($defaults, $options);

        $data = [];
        foreach($options as $k => $v) {
            if(substr($k, 0, 5) == 'data-') {
                $k1 = substr($k, 5);
                $data[$k1] = $v;
                unset($options[$k]);
            }
        }
        if(is_null($value) && isset($options['value'])) {
            $value = $options['value'];
        }
        unset($options['value']);
        $data['value'] = $value;
        $data['elem'] = '#' . $this->getIdAttribute($name, $options);
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $opt = $options;
        $opt['name'] = $name;
        $opt['id'] = $name;
        return [
            'laymodule' => ['slider'],
            'content'      => '<div class="hs-slider" id="' . $this->getIdAttribute($name, $options) . '"></div>',
            'script'    => [
                'slider.render(' . $data . ');'
            ],
            'style' => [
            ]
        ];
    }
}