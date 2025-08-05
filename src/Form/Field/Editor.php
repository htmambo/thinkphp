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

/**
 * Class Editor
 * @package HS\Html\Field
 *
 * @method Editor value(string $content)       //内容
 * @method Editor mode(string $editorType)      //编辑器，默认为简易编辑器，可选wang
 */
class Editor extends Textarea
{
    protected function _build($value = null)
    {
        if(is_null($value)) {
            $value = $this->options['value'];
        }
        $editortype = 'layedit';
        if(isset($this->options['mode'])){
            $editortype = $this->options['mode'];
            unset($this->options['mode']);
        }
        $this->options['style'] = 'display: none;';
        $id = $this->getIdAttribute($this->name, $this->options)?:$this->name;
        $result = parent::_build($value);
        switch ($editortype) {
            case 'wang':
                if ($value && substr(trim($value), 0, 1) != '<') {
                    $value = '<p>' . $value . '</p>';
                }
                $result['laymodule'] = ['wangEditor'];
                $result['css'] = ['js/lay-module/wangEditor/wangEditor.css'];
                $result['script'] = ['var editor_' . $this->name . ' = new wangEditor(\'#' . $id . '_div\');var content_' . $id . ' = $("#' . $id . '");editor_' . $this->name . '.customConfig.onchange=function(html){content_' . $id . '.val(html);};editor_' . $this->name . '.create();'];
                $result['content'] .= '<div id="' . $id . '_div">' . $value . '</div>';
                break;
            default:
                $result['laymodule'][] = 'layedit';
                $result['script'][] = 'layedit.build("' . $id . '");';
                break;
        }
        return $result;
    }
}