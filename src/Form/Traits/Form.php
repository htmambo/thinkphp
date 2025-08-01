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

use Think\Think;

/**
 * Class Form
 *
 * @property string action 表单提交地址，默认：空
 * @property boolean hasBorder 是否使用表格边框，默认：是
 * @property boolean inline 元素和标题是否在同一行，默认：是
 * @property string method 表单提交方式，GET/POST，默认：POST
 * @property string enctype 表单类型
 * @property string title 表单标题
 * @property array script 表单附加的JS代码
 * @property boolean tooltip 提示信息显示方式
 *
 * @method \Think\Form\Field\Text                   text($column, $label = '') 克林霉素
 * @method \Think\Form\Field\Checkbox               checkbox($column, $label = '')
 * @method \Think\Form\Field\Checkboxes             checkboxes($column, $label = '')
 * @method \Think\Form\Field\Select                 select($column, $label = '')
 * @method \Think\Form\Field\Switcher               switcher($column, $label = '')
 * @method \Think\Form\Field\Radio                  radio($column, $label = '')
 * @method \Think\Form\Field\Radios                 radios($column, $label = '')
 * @method \Think\Form\Field\Button                 button($name, $title = '')
 * @method \Think\Form\Field\Buttons                buttons($btns = [])
 * @method \Think\Form\Field\Textarea               textarea($column, $label = '')
 * @method \Think\Form\Field\Hidden                 hidden($column, $label = '')
 * @method \Think\Form\Field\Year                   year($column, $label = '')
 * @method \Think\Form\Field\Month                  month($column, $label = '')
 * @method \Think\Form\Field\Date                   date($column, $label = '')
 * @method \Think\Form\Field\Datetime               datetime($column, $label = '')
 * @method \Think\Form\Field\Slider                 slider($column, $label = '')
 * @method \Think\Form\Field\Rate                   rate($column, $label = '')
 * @method \Think\Form\Field\SelectResource         selectResource($column, $label = '')
 * @method \Think\Form\Field\DateTimeRange          datetimeRange($column, $label = '')
 * @method \Think\Form\Field\DateRange              dateRange($column, $label = '')
 * @method \Think\Form\Field\TimeRange              timeRange($column, $label = '')
 * @method \Think\Form\Field\Password               password($column, $label = '')
 * @method \Think\Form\Field\Time                   time($column, $label = '')
 * @method \Think\Form\Field\Editor                 editor($column, $label = '')
 * @method \Think\Form\Field\Tags                   tags($column, $label = '')
 *
 * @method \Think\Form\Field\File                   file($column, $label = '')
 * @method \Think\Form\Field\Files                  files($column, $label = '')
 * @method \Think\Form\Field\Image                  image($column, $label = '')
 * @method \Think\Form\Field\Images                 images($column, $label = '')
 *
 * @method \Think\Form\Field\Icon                   icon($column, $label = '')
 * @method \Think\Form\Field\Captcha                captcha()
 * @method \Think\Form\Field\Tree                   tree($column, $label = '')
 * @method \Think\Form\Field\Markdown               markdown($column, $label = '')
 */
trait Form
{
    /**
     * @var array
     */
    protected $data;

    /**
     * Input data.
     *
     * @var array
     */
    protected $options = [
        'method' => 'POST',
        'hasBorder' => true,
        'inline' => true,
        'tooltip' => false,
        'action' => '',
        'scripts' => [],
    ];

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * Form constructor.
     * @param array $data
     */
    public function __construct($data = [])
    {
        $this->data = $data ?: array();
    }

    /**
     * 使用内建模板输出页面
     * @return mixed
     */
    public function show()
    {
        /**
         * @var \Think\View;
         */
        $view = Think::instance('Think\View');
        $view->assign($this->render());
        $tplPath = dirname(dirname(__FILE__)) . '/View/';
        $view->assign('layuitpl', $tplPath . 'layui.html');
        $tpl = $tplPath . 'form.html';
        return $view->display($this->view ?: $tpl);
    }

    /**
     * 显示提交按钮
     */
    public function showSubmit()
    {
        $this->buttons(
            [
                [
                    'value' => '重置',
                    'type' => 'reset',
                    'class' => 'layui-btn layui-btn-danger'
                ],
                [
                    'value' => '提交',
                    'type' => 'submit',
                    'lay-submit' => $this->options['submit'],
                    'lay-filter' => $this->options['filter']
                ]
            ]
        );
    }

    /**
     * 生成渲染用的HTML代码
     * @return array
     *  返回值格式：
     *  [
     *      'css' => [      //需要额外加载的CSS文件
     *          ...
     *      ],
     *      'style' => '',  //需要额外加载的样式信息
     *      'js' => [       //需要额外加载的JS文件
     *          ...
     *      ],
     *      'laymodule' => [//需要加载的LayUI模块
     *          ...
     *      ],
     *      'content' => '',//生成的HTML内容
     *      'script' => '', //额外的脚本内容
     *  ]
     */
    public function render()
    {
        $op = $this->options;
        $content = '';
        if (isset($op['title'])) {
            $content = '<fieldset class="layui-elem-field layui-field-title" style="margin-top: 30px;"><legend>' . $op['title'] . '</legend></fieldset>';
            unset($op['title']);
        }
        $content .= '<form ';
        $op['class'] = 'layui-form ';
        $op['class'] .= $this->options['hasBorder'] ? 'layui-form-pane' : '';
        $skip = ['hasBorder', 'inline', 'tooltip', 'scripts'];
        foreach ($op as $k => $v) {
            if (in_array($k, $skip)) {
                continue;
            }
            $content .= $k . '="' . $v . '" ';
        }
        $content .= '>';

        $data = [
            'content' => $content,
            'laymodule' => [],
            'js' => [],
            'script' => [],
            'css' => [],
            'style' => []
        ];
        if (isset($this->options['style'])) {
            $data['style'] = (array)$this->options['style'];
        }
        if ($this->options['hasBorder']) {
            $data['style'][] = '.layui-form-pane .layui-input, .layui-form-pane textarea {border-width:0 0 0 1px; z-index: 1;}';
            if ($this->options['inline']) {
                $data['style'][] = '.layui-form-pane .layui-input-inline .layui-input, .layui-form-pane .layui-input-inline textarea {border-width: 0 1px;}';
            } else {
                $data['style'][] = '.layui-form-pane .layui-input-inline .layui-input, .layui-form-pane .layui-input-inline textarea {border-width: 0 0 1px 0;}';
            }
            $data['style'][] = '.layui-form-pane .layui-form-item[pane] .layui-form-label{font-weight: bold;border-width: 0 0 1px;padding: 8px 0;}';
            $data['style'][] = '.layui-form-pane .layui-layedit{border-width: 0 0 0 1px;}';
            $data['style'][] = '.layui-form .hs-switcher, .layui-form .hs-tags, .layui-form .hs-checkboxes, .layui-form .hs-radios, .layui-form .hs-rate, .layui-form .hs-slider {border: 0px solid #e6e6e6;border-width: 0 0 0 1px;}';
            //            $data['style'][] = '.layui-form-mid.layui-word-aux{float: inherit;}';
            $data['style'][] = '.layui-form-pane .hs-radios .layui-form-radio{margin-top: 4px;}';
            $data['style'][] = '.layui-form-pane .hs-rate, .layui-form-pane .hs-tags {padding-left: 10px !important;}';
            $data['style'][] = '.layui-form-pane .hs-tags .layui-btn {margin: 6px; height: 24px; line-height: 24px;}';
        }
        $data['style'][] = '.layui-form .hs-slider {height: 4px;padding: 16px 12px;}';
        $data['style'][] = '.layui-form .hs-switcher, .layui-form .hs-checkboxes, .layui-form .hs-radios, .layui-form .hs-rate {left: 0;padding: 0px;margin:0px;min-height: 36px;}';
        if ($this->options['inline']) {
            //            $data['style'][] = '.layui-form-label {min-width: 110px;padding-left: 10px !important; padding-right: 10px !important; width: auto !important; position:relative !important;}.layui-input-inline, .layui-input-block {margin-left: auto !important; float: left;}';
            $data['style'][] = '.layui-form-pane .layui-form-item[pane] .layui-form-label{border-width: 0; line-height:23px;}';
        } else {
            $data['style'][] = '.layui-form-pane .layui-form-item[pane] .layui-form-label,.layui-form-label{padding: 8px 15px; width: 100% !important; text-align: left;position: relative;}';
            $data['style'][] = '.layui-form-pane .layui-form-item[pane] .layui-input-inline, .layui-form-pane .layui-input-block,.layui-input-block{margin-left: 0px; border-left: 0px; clear: both;width: 100%; border-left: 0; border-width: 1px;}';
            $data['style'][] = '.layui-form-pane .layui-input-inline .xm-select-title{left: -1px;}';
        }
        $data['style'][] = 'textarea.layui-input {height: auto;}';
        if (I('get.test')) {
            dump($this->options);
            dump($data['style']);
        }
        $keys    = ['laymodule', 'js', 'script', 'css', 'style'];
        foreach ($this->fields as $field) {
            $result = $field->render(null, $this->options);
            $data['content'] .= $result['content'];
            foreach ($keys as $k) {
                if ($result[$k]) {
                    $data[$k] = array_merge($data[$k], $result[$k]);
                }
                $data[$k] = array_unique($data[$k]);
            }
        }
        //
        //        $data['content'] .= call_user_func_array([Form\Builder::instance(), 'buttons'], [
        //            [
        //                'value' => '重置',
        //                'type'  => 'reset',
        //                'class' => 'layui-btn layui-btn-danger'
        //            ],
        //            [
        //                'value'      => '提交',
        //                'type'       => 'submit',
        //                'lay-submit' => $this->options['submit'],
        //                'lay-filter' => $this->options['filter']
        //            ]
        //        ]);
        $data['content'] .= '</form>';

        $script = '';
        $laymodule = [];
        $tmp = array_unique($data['laymodule']);
        foreach ($tmp as $v) {
            $laymodule[] = "'" . $v . "'";
            $script .= 'var ' . $v . ' = layui.' . $v . ';';
        }
        $script .= implode("\n", $data['script']);
        if ($this->options['scripts']) {
            $script .= implode("\n", $this->options['scripts']);
        }
        if ($laymodule) {
            $laymodule = ',' . implode(',', $laymodule);
        } else {
            $laymodule = '';
        }
        return [
            'content' => $data['content'],
            'css' => $data['css'],
            'js' => $data['js'],
            'style' => implode("\n", $data['style']),
            'script' => $script,
            'laymodule' => $laymodule
        ];
    }

    /**
     * 生成 ThinkPHP 模板代码
     */
    public function showTplCode()
    {
        $result = $this->render();
        $tpl = [];
        $tpl[] = '<extend name="./Application/Common/View/layui.html"/>';

        $tpl[] = '<block name="body">';
        $tpl[] = $result['content'];
        $tpl[] = '</block>';
        if ($result['css']) {
            $tpl[] = '<block name="css">';
            foreach ($result['css'] as $css) {
                $tpl[] = '<link rel="stylesheet" href="__PUBLIC__/' . $css . '" media="all">';
            }
            $tpl[] = '</block>';
        }
        if ($result['style']) {
            $tpl[] = '<block name="style">';
            $tpl[] = $result['style'];
            $tpl[] = '</block>';
        }
        if ($result['script'] || $result['js'] || $result['laymodule']) {
            $tpl[] = '<block name="script">';
            if ($result['js']) {
                foreach ($result['js'] as $js) {
                    $tpl[] = '<script src="__PUBLIC__/{$file}" charset="utf-8"></script>';
                }
            }
            $tpl[] = '<script>';
            $tpl[] = 'layui.use([\'form\'';
            $tpl[] = $result['laymodule'];
            $tpl[] = '], function(){';
            $tpl[] = 'var $ = layui.jquery,form = layui.form;';
            if ($result['script']) {
                $tpl[] = $result['script'];
            }
            $tpl[] = '</script>';
            $tpl[] = '</block>';
        }
        echo '<script src="' . __ROOT__ . '/Public/js/layui-v2.6.8/layui.js" charset="utf-8"></script>
    <script src="' . __ROOT__ . '/Public/js/beautify/beautify.js"></script>
<script src="' . __ROOT__ . '/Public/js/beautify/beautify-css.js"></script>
<script src="' .  __ROOT__ . '/Public/js/beautify/beautify-html.js"></script>';
        echo '<textarea style="width: 100%; height: 100%">' . htmlentities(str_replace('><', ">\n<", implode("\n", $tpl))) . '</textarea>';
        echo '<script>
    layui.use([\'jquery\', \'layer\'], function () {
    var $ = layui.jquery;
    $(\'textarea\').val(html_beautify($(\'textarea\').val()));});
    </script>';

        exit;
    }

    /**
     * Generate a Field object and add to form builder if Field exists.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return Field
     * @throws \Think\Exception
     */
    public function __call($method, $arguments)
    {
        if ($className = static::findFieldClass($method)) {
            $field = $arguments[0];

            $element = new $className($field);
            if ($arguments[1]) {
                $element->label($arguments[1]);
            }
            if (is_string($field) && $this->data && isset($this->data[$field])) {
                $element->value($this->data[$field]);
            }

            $this->fields[] = &$element;

            return $element;
        }

        E("Field type [$method] does not exist.");

        return new Field\Nullable();
    }

    /**
     * Find field class.
     *
     * @param string $method
     *
     * @return bool|mixed
     */
    public static function findFieldClass($method)
    {
        $class = '';
        if (isset(static::$availableFields[$method])) {
            $class = static::$availableFields[$method];
        }
        if ($class && class_exists($class)) {
            return $class;
        }

        return false;
    }

    public function script($script)
    {
        $this->options['scripts'][] = $script;
    }
    /**
     * Getter.
     *
     * @param string $name
     *
     * @return array|mixed
     */
    public function __get($name)
    {
        return $this->options[$name];
    }

    /**
     * Setter.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        return $this->options[$name] = $value;
    }
}
