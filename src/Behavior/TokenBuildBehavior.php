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
 * 系统行为扩展：表单令牌生成
 */
class TokenBuildBehavior extends \Think\Behavior
{

    public function run(&$params)
    {
        if (C('TOKEN_ON')) {
            list($tokenName, $tokenKey, $tokenValue) = $this->getToken();
            $input_token                             = '<input type="hidden" name="' . $tokenName . '" value="' . $tokenKey . '_' . $tokenValue . '" />';
            $meta_token                              = '<meta name="' . $tokenName . '" content="' . $tokenKey . '_' . $tokenValue . '" />';
            if (strpos($params, '{__TOKEN__}')) {
                // 指定表单令牌隐藏域位置
                $params = str_replace('{__TOKEN__}', $input_token, $params);
            } elseif (preg_match('/<\/form(\s*)>/is', $params, $match)) {
                // 智能生成表单令牌隐藏域
                $params = str_replace($match[0], $input_token . $match[0], $params);
            }
            $params = str_ireplace('</head>', $meta_token . '</head>', $params);
        } else {
            $params = str_replace('{__TOKEN__}', '', $params);
        }
    }

    //获得token
    private function getToken()
    {
        $tokenName = C('TOKEN_NAME', null, '__hash__');
        $tokenType = C('TOKEN_TYPE', null, 'md5');
        if (!isset($_SESSION[$tokenName])) {
            $_SESSION[$tokenName] = array();
        }
        // 标识当前页面唯一性
        $tokenKey = md5($_SERVER['REQUEST_URI']);
        if (isset($_SESSION[$tokenName][$tokenKey])) {
            // 相同页面不重复生成session
            $tokenValue = $_SESSION[$tokenName][$tokenKey];
        } else {
            $tokenValue                      = is_callable($tokenType) ? $tokenType(microtime(true)) : md5(microtime(true));
            $_SESSION[$tokenName][$tokenKey] = $tokenValue;
            if (IS_AJAX && C('TOKEN_RESET', null, true)) {
                header($tokenName . ': ' . $tokenKey . '_' . $tokenValue);
            }
            //ajax需要获得这个header并替换页面中meta中的token值
        }
        return array($tokenName, $tokenKey, $tokenValue);
    }
}
