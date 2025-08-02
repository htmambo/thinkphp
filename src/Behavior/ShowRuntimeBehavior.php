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
namespace Think\Behavior;

/**
 * 系统行为扩展：运行时间信息显示
 */
class ShowRuntimeBehavior extends \Think\Behavior
{

    // 行为扩展的执行入口必须是run
    public function run(&$params)
    {
        if(!$params) return;
        if (C('SHOW_RUN_TIME')) {
            if (false !== strpos($params, '{__NORUNTIME__}')) {
                $params = str_replace('{__NORUNTIME__}', '', $params);
            } else if($params) {
                $runtime = $this->showTime();
                if (strpos($params, '{__RUNTIME__}')) {
                    $params = str_replace('{__RUNTIME__}', $runtime, $params);
                } else {
                    $params .= $runtime;
                }
            } else {
                echo $this->showTime();
            }
        } else {
            $params = str_replace(array('{__NORUNTIME__}', '{__RUNTIME__}'), '', $params);
        }
    }

    /**
     * 显示运行时间、数据库操作、缓存次数、内存使用信息
     * @access private
     * @return string
     */
    private function showTime()
    {
        // 显示运行时间
        G('beginTime', $GLOBALS['_beginTime']);
        G('viewEndTime');
        $showTime = 'Process: ' . G('beginTime', 'viewEndTime') . 's ';
        if (C('SHOW_ADV_TIME') || APP_DEBUG) {
            // 显示详细运行时间
            $showTime .= '( Load:' . G('beginTime', 'loadTime') . 's Init:' . G('loadTime', 'initTime') . 's Exec:' . G('initTime', 'viewStartTime') . 's Template:' . G('viewStartTime', 'viewEndTime') . 's )';
        }
        if (C('SHOW_DB_TIMES') || APP_DEBUG) {
            // 显示数据库操作次数
            $showTime .= ' | DB :' . N('db_query') . ' queries ' . N('db_write') . ' writes ';
        }
        if (C('SHOW_CACHE_TIMES') || APP_DEBUG) {
            // 显示缓存读写次数
            $showTime .= ' | Cache :' . N('cache_read') . ' gets ' . N('cache_write') . ' writes ';
        }
        if (MEMORY_LIMIT_ON && C('SHOW_USE_MEM') || APP_DEBUG) {
            // 显示内存开销
            $showTime .= ' | UseMem:' . number_format((memory_get_usage() - $GLOBALS['_startUseMems']) / 1024) . ' kb';
        }
        if (C('SHOW_LOAD_FILE') || APP_DEBUG) {
            $showTime .= ' | LoadFile:' . count(get_included_files());
        }
        if (C('SHOW_FUN_TIMES') || APP_DEBUG) {
            $fun = get_defined_functions();
            $showTime .= ' | CallFun:' . count($fun['user']) . ',' . count($fun['internal']);
        }
        return $showTime;
    }
}
