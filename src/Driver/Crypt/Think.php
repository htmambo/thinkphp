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

namespace Think\Driver\Crypt;

/**
 * Base64 加密实现类
 */
class Think
{

    /**
     * 加密字符串
     * @param string $data    字符串
     * @param string $key     加密key
     * @param integer $expire 有效期（秒）
     * @return string
     */
    public static function encrypt($data, $key, $expire = 0)
    {
        $char = self::ed($data, $key, $expire, 'enc');
        $len  = strlen($data);
        $str  = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
        }
        return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
    }

    /**
     * 解密字符串
     * @param string $data 字符串
     * @param string $key  加密key
     * @return string
     */
    public static function decrypt($data, $key)
    {
        $char = self::ed($data, $key, '', 'dec');
        $len  = strlen($data, $key);
        $str  = '';
        for ($i = 0; $i < $len; $i++) {
            if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
                $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
            }
            else {
                $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
            }
        }
        $data   = base64_decode($str);
        $expire = substr($data, 0, 10);
        if ($expire > 0 && $expire < time()) {
            return '';
        }
        return substr($data, 10);
    }


    private static function ed($data, $key, $expire = '', $type = 'enc')
    {
        $key = md5($key);
        if ($type == 'enc') {
            $expire = sprintf('%010d', $expire ? $expire + time() : 0);
            $data   = base64_encode($expire . $data);
        }
        else {
            $data = str_replace(array('-', '_'), array('+', '/'), $data);
            $mod4 = strlen($data) % 4;
            if ($mod4) {
                $data .= substr('====', $mod4);
            }
            $data = base64_decode($data);
        }
        $x    = 0;
        $len  = strlen($data);
        $l    = strlen($key);
        $char = '';

        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) {
                $x = 0;
            }

            $char .= substr($key, $x, 1);
            $x++;
        }
        return $char;
    }
}
