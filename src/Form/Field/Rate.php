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
 * Class Rate
 * @package HS\Html\Field
 *
 * @method Rate value(float $value) //评分的初始值
 * @method Rate half(boolean $half) //设定组件是否可以选择半星
 * @method Rate length(int $length) //评分最大值 评分组件中具体星星的个数。个数当然是整数啦，残缺的星星很可怜的，所以设置了小数点的组件我们会默认向下取整
 */
class Rate
{
    use Field;

    protected function _build($value = null)
    {
        $defaults = [
            'data-half' => false,
            'data-length' => 5
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
            'laymodule' => ['rate'],
            'content'      => '<div class="hs-rate" id="' . $this->getIdAttribute($name, $options) . '"></div>',
            'script'    => [
                'rate.render(' . $data . ');'
            ],
            'style' => [
                '.layui-rate li{margin: 0 !important;}.layui-form-pane .layui-form-item[pane] .layui-rate {padding: 4px 0px 0;}'
            ]
        ];
    }
}