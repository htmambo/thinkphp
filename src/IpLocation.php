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
namespace Think;

use Think\Exception\ErrorException;
use Think\Exception\FuncNotFoundException;
/**
 * Ip地理位置查询
 */
class IpLocation
{
    /**
     * 连接缓存
     * @access public
     * @param string $type 缓存类型
     * @param array $options  配置数组
     * @return object
     * @throws Exception
     */
    public function connect($type = '', $options = array())
    {
        if (empty($type)) {
            $type = C('IP_LOCATION_TYPE', null, 'ipip');
        }
        $class = strpos($type, '\\') ? $type : 'Think\Driver\IpLocation\\' . ucwords(strtolower($type));
        if (class_exists($class)) {
            $obj = new $class($options);
        } else {
            E(L('Ip Location Type {$type} is invalid', ['type' => $type]));
        }
        return $obj;
    }
    /**
     * 取得缓存类实例
     * @static
     * @access public
     * @param string $type
     * @param array $options
     * @return mixed
     * @throws Exception
     */
    public static function getInstance($type = '', $options = array())
    {
        static $_instance = array();
        $guid = $type . to_guid_string($options);
        if (!isset($_instance[$guid])) {
            $obj = new IpLocation();
            $_instance[$guid] = $obj->connect($type, $options);
        }
        return $_instance[$guid];
    }
    /**
     * @throws Exception
     */
    public static function find($ip, $type = '')
    {
        $iplong = ip2long($ip);
        if (!$iplong) {
            throw new \Think\Exception\BadRequestException(400, L('Invalid IP address: {$var_0}', ['var_0' => $ip]));
        }
        //调用缓存类型自己的方法
        $handler = self::getInstance($type);
        if (method_exists($handler, 'find')) {
            return call_user_func_array(array($handler, 'find'), [$ip]);
        } else {
            throw new \Think\Exception\BadRequestException(400, L('_ERROR_ACTION_'));
        }
    }
}