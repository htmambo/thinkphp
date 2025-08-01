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
 * ThinkPHP 普通模式定义
 */
return array(
    // 配置文件
    'config' => array(
        CORE_PATH . 'Helper/Conf/convention.php', // 系统惯例配置
        CONF_PATH . 'config.php', // 应用公共配置
    ),

    // 函数和类文件
    'core'   => array(
        COMMON_PATH . 'Common/function.php',
    ),
    // 行为扩展定义
    'tags'   => array(
        'app_init'        => array(
        ),
        'app_end'         => array(
            'Think\Driver\Behavior\ShowPageTraceBehavior', // 页面Trace显示
            'Think\Driver\Behavior\ShowRuntimeBehavior',   // 代码执行时间
        ),
        'view_parse'      => array(
            'Think\Driver\Behavior\ParseTemplateBehavior', // 模板解析 支持PHP、内置模板引擎和第三方模板引擎
        ),
        'template_filter' => array(
            'Think\Driver\Behavior\ContentReplaceBehavior', // 模板输出替换
        ),
    ),
);
