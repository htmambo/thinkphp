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
 * Class Select
 * @package HS\Html\Field
 *
 * @method Select list(array $options) 下拉框的选项列表
 * @method Select multiple(boolean $ismultiple) 是否允许多选
 */
class Select
{
    use Field;

    /**
     * 生成下拉列表框
     *
     * @param string $name
     * @param mixed $value
     * @param array $options
     * @return array
     */
    protected function _build($value = null)
    {
        $options = $this->options;
        $list = [];
        if (isset($options['list'])) {
            $list = $options['list'];
            unset($options['list']);
        }
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
//        $options['xm-select-search'] = true;

        $attr = $this->attributes($options);
        $list = implode('', $html);
        return [
            'laymodule' => [
                'formSelects',
            ],
            'css' => [
//                'js/lay-module/formSelects/formSelects-v4.css',
            ],
            'content' => "<select{$attr}>{$list}</select>",
            'script' => [
                'formSelects.render(\'#' . $this->getIdAttribute($this->name, $options) . '\');'
            ],
            'style' => [
            ]
        ];
    }

    /**
     * 根据传递的值生成option
     *
     * @param string $display
     * @param string $value
     * @param string $selected
     * @return string
     */
    private function getSelectOption($display, $value, $selected)
    {
        if (is_array($display)) {
            return $this->optionGroup($display, $value, $selected);
        }

        return $this->option($display, $value, $selected);
    }

    /**
     * 生成optionGroup
     *
     * @param array $list
     * @param string $label
     * @param string $selected
     * @return string
     */
    protected function optionGroup($list, $label, $selected)
    {
        $html = [];
        foreach ($list as $value => $display) {
            $html[] = $this->option($display, $value, $selected);
        }
        return '<optgroup label="' . $this->escape($label) . '">' . implode('', $html) . '</optgroup>';
    }

    /**
     * 生成option选项
     *
     * @param string $display
     * @param string $value
     * @param string $selected
     * @return string
     */
    protected function option($display, $value, $selected)
    {
        $selected = $this->getSelectedValue($value, $selected);

        $options = array('value' => $this->escape($value), 'selected' => $selected);

        return '<option' . $this->attributes($options) . '>' . $this->escape($display) . '</option>';
    }

    /**
     * 检测value是否选中
     *
     * @param string $value
     * @param string $selected
     * @return string
     */
    protected function getSelectedValue($value, $selected)
    {
        if (is_array($selected)) {
            return in_array($value, $selected) ? 'selected' : null;
        }

        return ((string)$value == (string)$selected) ? 'selected' : null;
    }

}