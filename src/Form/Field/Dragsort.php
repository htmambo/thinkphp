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
 * Class Editor
 * @package HS\Html\Field
 *
 * @method Editor value(string $content)       //内容
 * @method Editor type(string $editorType)      //编辑器，默认为简易编辑器，可选wang
 */
class Dragsort
{
    use Field;

    protected function _build($value = null)
    {
        if(is_null($value)) {
            $value = $this->options['value'];
        }
        $editortype = 'layedit';
        if(isset($this->options['type'])){
            $editortype = $this->options['type'];
            unset($this->options['type']);
        }
        $this->options['style'] = 'display: none;';
        $id = $this->getIdAttribute($this->name, $this->options)?:$this->name;
        $result = [];
        $html = ['<ul id="' . $id . '_list" class="layui-transfer-data dragsort">'];
        $list = explode(',', $value);
        foreach($list as $item) {
            $html[] = '<li><div class="layui-select" lay-skin="primary" data-value="' . $item . '"><div>' . $item . '</div><i class="layui-icon layui-icon-delete"></i></div></li>';
        }
        $html[] = '</ul>';
        $html[] = '<input type="hidden" name="' . $this->name . '" id="' . $id . '_value" value="' . htmlspecialchars($value) . '" />';

        $result['laymodule'] = ['dragsort'];
        $result['css'] = [];
        $result['script'] = ['dragsort.render("' . $id . '");'];
        $result['content'] = implode('', $html);
        return $result;
    }
}