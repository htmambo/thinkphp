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
 * Class Tags
 * @package HS\Html\Field
 *
 * @method Tags tag(array $tag)       //标签
 */
class Tags
{
    use Field;

    protected function _build($value = null)
    {
        $options = $this->options;
        $name = $this->name;
        $options['name'] = $name;

        if (is_null($value) && isset($options['value'])) {
            $value = $options['value'];
        }
        unset($options['value']);
        if(is_array($value)) {
            $value = array_values($value);
        }
        $data['content'] = $value;
        $data['name'] = $name;
        $data['elem'] = '#' . $this->getIdAttribute($name, $options);
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $html = '<div class="hs-tags tags">
			<input type="text" name=""  id="' . $name . '" placeholder="回车生成标签" autocomplete="off" class="tag-input">
		</div>';
        return [
            'laymodule' => ['inputTags'],
            'content' => $html,
            'script' => [
                'inputTags.render(' . $data . ');'
            ],
            'style' => [
            ]
        ];
    }
}