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
 * 消息推送门面类
 *
 * 提供统一的多渠道消息推送接口，支持企业微信、微信模板、Server酱、Bark、Telegram Bot 等多种推送方式。
 * 通过工厂模式创建驱动实例，通过单例模式管理实例，通过魔术方法实现批量推送。
 *
 * @package Think
 * @author  liu21st <liu21st@gmail.com>
 *
 * @static array send(string $content, string $subject = '', array $data = [], string $recipient = null, ...$params)
 */
class Messager
{

    /**
     * 操作句柄
     *
     * @var object|null
     * @access protected
     */
    protected $handler;

    /**
     * 缓存连接参数
     *
     * 用于存储不同配置的组合标识
     *
     * @var array
     * @access protected
     */
    protected $options = [];

    /**
     * 连接指定类型的消息推送驱动
     *
     * 使用工厂模式创建指定类型的驱动实例。如果未指定类型，则使用配置文件中的默认类型。
     *
     * @param string $type 消息推送驱动类型（如 'WeChat', 'ServerChan' 等）
     * @param array $options 驱动配置参数
     * @return object 驱动实例
     * @throws ClassNotFoundException 当驱动类不存在时抛出
     * @access public
     */
    public function connect(string $type = '', array $options = []): object
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
     * 获取驱动单例实例
     *
     * 使用单��模式管理驱动实例，相同类型和配置的驱动只会被创建一次。
     * 实例根据类型和配置的组合 GUID 进行缓存。
     *
     * @param string $type 消息推送驱动类型
     * @param array $options 驱动配置参数
     * @return object 驱动实例
     * @static
     * @access public
     */
    public static function getInstance(string $type = '', array $options = []): object
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
     * 静态魔术方法调用
     *
     * 调用所有已启用驱动的指定方法，实现批量推送功能。
     * 根据配置文件中的 SEND_MESSAGE 配置，遍历所有启用的驱动并执行指定方法。
     *
     * 返回数组格式：
     * ```php
     * [
     *     'WeChat' => 'success',  // 成功
     *     'ServerChan' => 'fail', // 失败
     *     'Bark' => 'ignore',     // 未启用
     * ]
     * ```
     *
     * @param string $method 要调用的方法名（通常是 'send'）
     * @param array $params 传递给方法的参数
     * @return array 各驱动的执行结果数组
     * @throws \Exception 当驱动实例化失败时抛出
     * @static
     */
    public static function __callStatic(string $method, array $params): array
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
