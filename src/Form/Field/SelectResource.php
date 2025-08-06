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
 * Class SelectResource
 * @package HS\Html\Field
 *
 * @method SelectResource linkage(boolean $isLinkage)       //级联选择
 * @method SelectResource url(string $url)      //服务器数据接口
 * @method SelectResource keyName(string $keyName)  //选项提示文字所在的字段名
 * @method SelectResource keyVal(any $keyVal)  //选项值所在的字段名
 * @method SelectResource keyChildren(string $keyChildren)   //子项目所在的字段名
 * @method SelectResource dataName(string $dataName)    //数据包所在的字段名
 */
class SelectResource extends Select
{

    /**
     * 生成下拉列表框
     *
     * @param mixed $value
     * @return array
     */
    protected function _build($value = null)
    {
        $list = $this->parseExtra();
        $options = $this->options;
        if (is_null($value) && isset($options['value'])) {
            $value = $options['value'];
            unset($options['value']);
        }
        $value = $this->getValueAttribute($this->name, $value);

        $options['id'] = $this->getIdAttribute($this->name, $options)?:$this->name;

        if (!isset($options['name'])) {
            $options['name'] = $this->name;
        }
        if (!isset($options['xm-select'])) {
            $options['xm-select'] = $this->getIdAttribute($this->name, $options);
        }

        $html = [];
        foreach ($list as $val => $display) {
            $html[] = $this->getSelectOption($display, $val, $value);
        }
        $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'layui-input') !== false ? '' : ' layui-input') : 'layui-input';

        if (!isset($options['multiple']) || !$options['multiple']) {
            $options['xm-select-radio'] = "true";
        }
        unset($options['multiple']);
        $defaults = [
            'url' => '',
            'linkage' => false,
            'keyPid' => 'parentid',
            'keyName' => 'title',
            'keyVal' => 'id',
            'keyChildren' => 'children',
            'response' => [
                'statusCode' => 1,
                'statusName' => 'status',
                'msgName' => 'info',
                'dataName' => 'data'
            ]
        ];
        foreach($defaults as $k => $v) {
            if(isset($options[$k])) {
                if(is_array($defaults[$k])) {
                    $defaults[$k] = array_merge($defaults[$k], (array)$options[$k]);
                } else {
                    $defaults[$k] = $options[$k];
                }
                unset($options[$k]);
            }
        }
        $attr = $this->attributes($options);
        $list = implode('', $html);
        return [
            'laymodule' => [
                'formSelects',
            ],
            'css' => [
                'js/lay-module/formSelects/formSelects-v4.css',
            ],
            'content' => "<select{$attr}>{$list}</select>",
            'script' => [
                'formSelects.render(\'#' . $this->getIdAttribute($this->name, $options) . '\');',
                'formSelects.data("' . $this->getIdAttribute($this->name, $options) . '", "server", ' . json_encode($defaults, JSON_UNESCAPED_UNICODE) . ');',
            ],
        ];
    }
}