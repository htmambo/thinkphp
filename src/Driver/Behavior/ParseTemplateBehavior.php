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
namespace Think\Driver\Behavior;

use Think\Storage;

/**
 * 系统行为扩展：模板解析
 */
class ParseTemplateBehavior extends \Think\Behavior
{

    // 行为扩展的执行入口必须是run
    public function run(&$params)
    {
        $engine          = C('TMPL_ENGINE_TYPE', null, 'Think');
        // TODO 这样可能会造成后续处理的概念混淆：有时候这里是内容，有时候这里是文件标识
        $_content        = empty($params['content']) ? $params['file'] : $params['content'];
        $params['prefix'] = !empty($params['prefix']) ? $params['prefix'] : C('TMPL_CACHE_PREFIX');
        $class = '';
        if ('Think' == $engine) {
            // 采用Think模板引擎，需要检查缓存
            if ((!empty($params['content']) && $this->checkContentCache($params['content'], $params['prefix']))
                || $this->checkCache($params['file'], $params['prefix'])) {
                // 缓存有效，载入模版缓存文件
                Storage::load(C('CACHE_PATH') . $params['prefix'] . md5($_content) . C('TMPL_CACHFILE_SUFFIX'), $params['var']);
                return;
            }
        }
        // 调用第三方模板引擎解析和输出
        if (strpos($engine, '\\')) {
            $class = $engine;
        } else {
            $class = 'Think\\Driver\\Template\\' . ucwords($engine);
        }
        if (class_exists($class)) {
            $tpl = new $class;
            $tpl->fetch($_content, $params['var'], $params['prefix']);
        } else {
            // 类没有定义
            E(L('_NOT_SUPPORT_') . ': ' . $class);
        }
    }

    /**
     * 检查缓存文件是否有效
     * 如果无效则需要重新编译
     * @access public
     * @param string $tmplTemplateFile  模板文件名
     * @return boolean
     */
    protected function checkCache($tmplTemplateFile, $prefix = '')
    {
        if (!C('TMPL_CACHE_ON')) // 优先对配置设定检测
        {
            return false;
        }

        $tmplCacheFile = C('CACHE_PATH') . $prefix . md5($tmplTemplateFile) . C('TMPL_CACHFILE_SUFFIX');
        if (!Storage::has($tmplCacheFile)) {
            return false;
        } elseif (filemtime($tmplTemplateFile) > Storage::get($tmplCacheFile, 'mtime')) {
            // 模板文件如果有更新则缓存需要更新
            return false;
        } elseif (C('TMPL_CACHE_TIME') != 0 && time() > Storage::get($tmplCacheFile, 'mtime') + C('TMPL_CACHE_TIME')) {
            // 缓存是否在有效期
            return false;
        }
        // 开启布局模板
        if (C('LAYOUT_ON')) {
            $layoutFile = THEME_PATH . C('LAYOUT_NAME') . C('TMPL_TEMPLATE_SUFFIX');
            if (filemtime($layoutFile) > Storage::get($tmplCacheFile, 'mtime')) {
                return false;
            }
        }
        // 缓存有效
        return true;
    }

    /**
     * 检查缓存内容是否有效
     * 如果无效则需要重新编译
     * @access public
     * @param string $tmplContent  模板内容
     * @return boolean
     */
    protected function checkContentCache($tmplContent, $prefix = '')
    {
        if (Storage::has(C('CACHE_PATH') . $prefix . md5($tmplContent) . C('TMPL_CACHFILE_SUFFIX'))) {
            return true;
        } else {
            return false;
        }
    }
}
