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

class File
{
    use Field;

    public function __construct($name)
    {
        $this->name = $name;
        $this->options = [
            'class' => 'layui-btn layui-btn-normal'
        ];
    }

    protected function _xbuild($value = null) {
        $options = $this->options;
        $name = $this->name;
        $options['name'] = $name;
        $defaults = [
            'data-format' => "yyyy-MM-dd HH:mm:ss",
            'data-use-current' => "true",
            'readonly'         => 'true',
            'data-type' => 'datetime',
            'class' => 'layui-btn layui-btn-normal'
        ];
        $value    = is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
        $options  = array_merge($defaults, $options);

        $data = [];
        foreach($options as $k => $v) {
            if(substr($k, 0, 5) == 'data-') {
                $k1 = substr($k, 5);
                $data[$k1] = $v;
                unset($options[$k]);
            }
        }
        $data['elem'] = '#' . $this->getIdAttribute($name, $options);
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $opt = $options;
        $opt['name'] = $name;
        $opt['id'] = $name;
        return [
            'laymodule' => ['upload'],
            'content'      => $this->input('file', $value, $opt),
            'script'    => [
                'laydate.render(' . $data . ');'
            ]
        ];
    }
}


/**
 * layui.upload.render({ //允许上传的文件后缀
elem: '#test4'
,url: 'https://httpbin.org/post' //改成您自己的上传接口
,accept: 'file' //普通文件
,exts: 'zip|rar|7z' //只允许上传压缩文件
,done: function(res){
layer.msg('上传成功');
console.log(res)
}
});
 */