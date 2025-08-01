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

use Think\Exception\ClassNotFoundException;

/**
 * 消息推送类
 *
 * @static array send(string $content, string $subject = '', array $data = [], string $recipient = null, ...$params)
 */
class Messager
{

    /**
     * 操作句柄
     * @var string
     * @access protected
     */
    protected $handler;

    /**
     * 缓存连接参数
     * @var integer
     * @access protected
     */
    protected $options = array();

    /**
     * 连接缓存
     * @access public
     * @param string $type   消息推送类型
     * @param array $options 配置数组
     * @return object
     * @throws Exception
     */
    public function connect($type = '', $options = array())
    {
        if (empty($type)) {
            $type = C('MESSAGE_SEND_TYPE');
        }

        $class = strpos($type, '\\') ? $type : 'Think\\Driver\\Messager\\' . $type;
        if (class_exists($class)) {
            return new $class($options);
        } else {
            throw new ClassNotFoundException(L('Message push class {$type} does not exist', ['type' => $type]));
        }
    }

    /**
     * 取得缓存类实例
     * @static
     * @access public
     * @param string $type
     * @param array $options
     * @return Cache
     * @throws Exception
     */
    public static function getInstance($type = '', $options = array())
    {
        static $_instance = array();
        $guid             = $type . to_guid_string($options);
        if (!isset($_instance[$guid])) {
            $obj              = new Messager();
            $_instance[$guid] = $obj->connect($type, $options);
        }
        return $_instance[$guid];
    }

    /**
     * @param $method
     * @param $params
     *
     * @return array
     * @throws \Exception
     */
    public static function __callStatic($method, $params)
    {
        $result = [];

        foreach (C('SEND_MESSAGE') as $conf) {
            if ($conf['enable'] !== 1) {
                $result[$conf['class']] = 'ignore';
                continue;
            }

            $serviceInstance = self::getInstance($conf['class'], $conf);

            if ($serviceInstance->$method(...$params)) {
                $result[$conf['class']] = 'success';
            } else {
                $result[$conf['class']] = 'fail';
            }
        }

        return $result;
    }
}
