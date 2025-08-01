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

namespace Think\Form\Traits;

use Think\Form\Field\Buttons;

/**
 * Trait Field
 * @package HS\Html\Traits
 *
 * @method Field value(any $value) 表单元素值
 * @method Field tips(string $tips) 表单元素提示信息
 * @method Field tooltip(string $tip) 点击提示信息
 * @method Field data(string $dataname, $value) 表单元素data-描述信息
 * @method Field required(boolean $isrequired) 是否必填
 * @method Field disabled(boolean $isdisabled) 只显示，不允许操作
 * @method Field list($list) 可选项列表
 * @method Field placeholder($placeholder) 输入框提示信息
 * @method Field class($class) 自定义样式名
 */
trait Field
{
    /**
     * 表单元素名
     * @var string
     */
    protected $name;
    /**
     * 表单元素标题
     * @var string
     */
    protected $label          = '';
    /**
     * 表单元素配置信息
     * @var array
     */
    protected $options        = [];
    private   $skipValueTypes = ['file', 'password', 'radio', 'checkbox', 'button'];

    /**
     * 生成HTML代码
     * @param null $value
     * @param array $styleOptions
     * @return array
     */
    public function render($value = null, $styleOptions = [])
    {
        $result  = call_user_func_array([$this, '_build'], ['value' => $value]);
        if(is_string($result)) {
            $result = [
                'content' => $result
            ];
        }
        $data    = [
            'content'   => '',
            'laymodule' => [],
            'js'        => [],
            'script'    => [],
            'css'       => [],
            'style'     => []
        ];
        if ($this instanceof Buttons) {
            $start = '<div';
        } else {
            $start = '<div class="layui-form-item" ' . ($styleOptions['hasBorder']?'pane':'');
        }
        if($this->options['divid']) {
            $start .= ' id="' . $this->options['divid'] . '"';
        }
        $start .= '>';
        $content = $start;
        if ($this->label) {
            $content .= $this->showLabel($this->name, $this->label);
            if(!$styleOptions['inline']) {
//                $content .= '</div>' . $start;
            }
        }
        $class = 'layui-input-block';
        if ($this->options['tips'] && !$styleOptions['tooltip']) {
            $class = 'layui-input-inline';
        }
        if(isset($this->options['class'])) {
            $class .= ' ' . $this->options['class'];
        }
        $content .= '<div class="' . $class . '">';
        $content .= $result['content'];
        unset($result['content']);
        $keys = ['laymodule', 'js', 'script', 'css', 'style'];
        foreach ($keys as $k) {
            if (isset($result[$k]) && $result[$k]) {
                $data[$k] = array_merge($data[$k], (array)$result[$k]);
            }
        }
        $content .= '</div>';
        if ($this->options['tips'] && !$styleOptions['tooltip']) {
            $content .= '<div class="layui-form-mid layui-word-aux">' . $this->options['tips'] . '</div>';
        }
        $content         .= '</div>';
        $data['content'] = $content;
        return $data;
    }

    protected function _build($value = null)
    {
        $tmp   = explode('\\', get_class($this));
        $class = strtolower(array_pop($tmp));
        return $this->input($class, $value);
    }

    /**
     * 生成文本框(按类型)
     *
     * @param string $type
     * @param string $value
     * @param array $options
     * @return string
     */
    protected function input($type, $value = null, $options = null)
    {
        if (is_null($options)) {
            $options = $this->options;
        }
        $name = $this->name;
        if (!isset($options['name'])) {
            $options['name'] = $name;
        }
        if (is_null($value) && isset($options['value'])) {
            $value = $options['value'];
            unset($options['value']);
        }
        $id = $this->getIdAttribute($name, $options);

        if ($type == 'button') {
            if (is_null($value)) {
                if ($options['value']) {
                    $value = $options['value'];
                } else {
                    $value = $this->label;
                }
            }
            if (!isset($options['type']) || !$options['type']) {
                $options['type'] = $this->name;
            }
            $this->label      = '';
            $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'layui-btn') !== false ? '' : ' layui-btn') : 'layui-btn';
            return '<button' . $this->attributes($options) . '>' . $value . '</button>';
        } else {
            if (!in_array($type, $this->skipValueTypes)) {
                $value            = $this->getValueAttribute($name, $value);
            }
            $options['class'] = isset($options['class']) ? $options['class'] . (stripos($options['class'], 'layui-') !== false ? '' : ' layui-input') : 'layui-input';
            $merge   = compact('type', 'value', 'id');
            $options = array_merge($options, $merge);
            return '<input' . $this->attributes($options) . '>';
        }
    }

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * 表单元素名称
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 表单元素标签
     * @param string $label
     * @return $this
     */
    public function label($label)
    {
        $this->label = $label;
        return $this;
    }

    public function __call($name, $arguments)
    {
        if ($name == 'data') {
            $this->options['data-' . $arguments[0]] = $arguments[1];
        } else {
            if (count($arguments) == 1) {
                $this->options[$name] = $arguments[0];
            } else {
                $this->options[$name][$arguments[0]] = $arguments[1];
            }
        }
        return $this;
    }

    /**
     * 获取ID属性值
     *
     * @param string $name
     * @param array $attributes
     * @return string
     */
    protected function getIdAttribute($name, $attributes)
    {
        if (array_key_exists('id', $attributes)) {
            return $attributes['id'];
        }
        return $name;
    }

    /**
     * 获取Value属性值
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    protected function getValueAttribute($name, $value = null)
    {
        if (is_null($name)) {
            return $value;
        }

        if (!is_null($value)) {
            return $value;
        }
    }

    /**
     * 数组转换成一个HTML属性字符串。
     *
     * @param array $attributes
     * @return string
     */
    protected function attributes($attributes)
    {
        $html = [];
        // 假设我们的keys 和 value 是相同的,
        // 拿HTML“required”属性来说,假设是['required']数组,
        // 会以 required="required" 拼接起来,而不是用数字keys去拼接
        foreach ((array)$attributes as $key => $value) {
            if($key === 'tips') continue;
            if (is_numeric($key)) {
                $key = $value;
            }
            $element = null;
            if (!is_null($value)) {
                if (is_array($value) || stripos($value, '"') !== false) {
                    $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                    $element = $key . "='" . $value . "'";
                } else if ($key !== 'checked' || $value) {
                    $element = $key . '="' . $value . '"';
                }
            }

            if (!is_null($element)) {
                $html[] = $element;
            }
        }
        return count($html) > 0 ? ' ' . implode(' ', $html) : '';
    }

    /**
     * 生成Label标签
     *
     * @param string $name
     * @param string $value
     * @param array $options
     * @return string
     */
    protected function showLabel($name, $value = null, $options = [])
    {
        $options = $this->attributes($options);
        $value   = $value ?: ucwords(str_replace('_', ' ', $name));
        $value   = $this->escape($value);
        return '<label class="layui-form-label" for="' . $name . '"' . $options . '>' . $value . '</label>';
    }

    /**
     * 获取转义编码后的值
     * @param string $value
     * @return string
     */
    protected function escape($value)
    {
        if (!$this->escapeHtml) {
            return $value;
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }

}