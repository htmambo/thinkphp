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
 * Class Textarea
 * @package HS\Html\Field
 *
 * @method Textarea height(int $height);        //文本框高度
 */
class Textarea
{
    use Field;

    protected function _build($value = null) {
        $options = $this->options;
        if (!isset($options['name'])) {
            $options['name'] = $this->name;
        }
        if(is_null($value)) {
            if(isset($this->options['value'])) {
                $value = $this->options['value'];
                unset($this->options['value'], $options['value']);
            }
        }
        $options       = $this->setTextAreaSize($options);
        $options['id'] = $this->getIdAttribute($this->name, $options);
        $value         = (string)$this->getValueAttribute($this->name, $value);

        if(isset($options['style']) && !is_array($options['style'])) {
            $options['style'] = (array) $options['style'];
        }
        if($options['height']) {
            $options['style'][] = 'height:' . $options['height'] . 'px;';
        }
        if($options['style']) {
            $options['style'] = implode(';', $options['style']);
        }
        unset($options['size']);

        $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'layui-input') !== false ? '' : ' layui-input') : 'layui-textarea';
        $options          = $this->attributes($options);

        return [
            'content' => '<textarea' . $options . '>' . $this->escape($value) . '</textarea>',
        ];

    }

    /**
     * 设置默认的文本框行列数
     *
     * @param array $options
     * @return array
     */
    protected function setTextAreaSize($options)
    {
        if (isset($options['size'])) {
            return $this->setQuickTextAreaSize($options);
        }

        $cols = isset($options['cols'])?$options['cols']:100;
        $rows = isset($options['rows'])?$options['rows']:5;

        return array_merge($options, compact('cols', 'rows'));
    }

    /**
     * 根据size设置行数和列数
     *
     * @param array $options
     * @return array
     */
    protected function setQuickTextAreaSize($options)
    {
        $segments = explode('x', $options['size']);
        return array_merge($options, array('cols' => $segments[0], 'rows' => $segments[1]));
    }
}