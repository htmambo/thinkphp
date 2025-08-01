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

/**
 * ThinkPHP 简体中文语言包
 */
return [
    /* 核心语言变量 */
    '_MODULE_NOT_EXIST_'       => '无法加载模块',
    '_CONTROLLER_NOT_EXIST_'   => '无法加载控制器',
    '_ERROR_ACTION_'           => '非法操作',
    '_LANGUAGE_NOT_LOAD_'      => '无法加载语言包',
    '_TEMPLATE_NOT_EXIST_'     => '模板不存在',
    '_MODULE_'                 => '模块',
    '_ACTION_'                 => '操作',
    '_MODEL_NOT_EXIST_'        => '模型不存在或者没有定义',
    '_VALID_ACCESS_'           => '没有权限',
    '_XML_TAG_ERROR_'          => 'XML标签语法错误',
    '_DATA_TYPE_INVALID_'      => '非法数据对象！',
    '_OPERATION_WRONG_'        => '操作出现错误',
    '_NOT_LOAD_DB_'            => '无法加载数据库',
    '_NO_DB_DRIVER_'           => '无法加载数据库驱动',
    '_NOT_SUPPORT_DB_'         => '系统暂时不支持数据库',
    '_NO_DB_CONFIG_'           => '没有定义数据库配置',
    '_NOT_SUPPORT_'            => '系统不支持',
    '_CACHE_TYPE_INVALID_'     => '无法加载缓存类型',
    '_FILE_NOT_WRITABLE_'      => '目录（文件）不可写',
    '_METHOD_NOT_EXIST_'       => '方法不存在！',
    '_CLASS_NOT_EXIST_'        => '实例化一个不存在的类！',
    '_CLASS_CONFLICT_'         => '类名冲突',
    '_TEMPLATE_ERROR_'         => '模板引擎错误',
    '_CACHE_WRITE_ERROR_'      => '缓存文件写入失败！',
    '_TAGLIB_NOT_EXIST_'       => '标签库未定义',
    '_OPERATION_FAIL_'         => '操作失败！',
    '_OPERATION_SUCCESS_'      => '操作成功！',
    '_SELECT_NOT_EXIST_'       => '记录不存在！',
    '_EXPRESS_ERROR_'          => '表达式错误',
    '_TOKEN_ERROR_'            => '表单令牌错误',
    '_RECORD_HAS_UPDATE_'      => '记录已经更新',
    '_NOT_ALLOW_PHP_'          => '模板禁用PHP代码',
    '_PARAM_ERROR_'            => '参数错误或者未定义',
    '_ERROR_QUERY_EXPRESS_'    => '错误的查询条件',
    '_ACTION_COMMENT_ERROR_'   => '方法注释不存在',
    '_METHOD_ERROR_'           => '错误的请求方式',
    '_FAILED_GET_TABLE_STRUCT' => '表结构获取失败',

    // Upload.php
    'The specified parameter does not exist!' => '指定的参数不存在！',
    'No uploaded files!' => '没有上传的文件！',
    'Illegal image files!' => '非法图像文件！',
    'Unknown upload error!' => '未知上传错误！',
    'Temporary files have been lost!' => '临时文件已经丢失！',
    'Illegal upload of files!' => '非法上传文件！',
    'The uploaded file size does not match!' => '上传文件大小不符！',
    'Uploading file MIME type is not allowed!' => '上传文件MIME类型不允许！',
    'Uploading file suffix is not allowed' => '上传文件后缀不允许',
    'The uploaded file exceeds the value limit of the upload_max_filesize option in php.ini!' => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值！',
    'The uploaded file size exceeds the value specified by the MAX_FILE_SIZE option in the HTML form!' => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值！',
    'Only partial files are uploaded!' => '文件只有部分被上传！',
    'No files have been uploaded!' => '没有文件被上传！',
    'Temporary folder not found!' => '找不到临时文件夹！',
    'File writing failed!' => '文件写入失败！',
    'File naming rules are wrong!' => '文件命名规则错误！',

    // Messager.php
    'Message push class {$type} does not exist' => '消息推送类 {$type} 不存在',

    // App.php
    'Multiple modules are not enabled but multiple modules are configured!' => '未开启多模块但是配置了多个模块！',
    'The configuration file is incorrect, and the prohibited module and the allowable module appear at the same time: {$var_0}' => '配置文件有误，禁止模块与允许模块中同时出现了：{$var_0}',

    // Auth.php
    'Cannot be added repeatedly' => '不能重复添加',
    'Please specify the UID to check' => '请指定要检查的UID',
    'Please specify the extension to check' => '请指定要检查的扩展标识',
    'Please specify the group ID to check' => '请指定要检查的组ID',

    // Image.php
    'Unsupported image processing library types' => '不支持的图片处理库类型',

    // IpLocation.php
    'Invalid IP address: {$var_0}' => '无效的IP地址：{$var_0}',
    'Invalid query' => '查询无效',

    // Model.php
    'System error, please wait and try again' => '系统错误，请稍候再试',

    // Translate.php
    'The translation engine does not exist!' => '翻译引擎不存在！',

    
];
