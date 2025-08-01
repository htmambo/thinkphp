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
namespace Think\Driver\Cache;

use Think\Cache;

/**
 * Redis缓存驱动
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 */
class Redis extends Cache
{
    /**
     *
     * @var \Redis;
     */
    protected $handler;

    /**
     * 架构函数
     * @param array $options 缓存参数
     * @throws \Think\Exception
     * @access public
     */
    public function __construct($options = array())
    {
        if (!extension_loaded('redis')) {
            E(L('_NOT_SUPPORT_') . ':redis');
        }
        $options = array_merge(array(
            'host' => C('REDIS_HOST') ?: '127.0.0.1',
            'port' => C('REDIS_PORT') ?: 6379,
            'password' => C('REDIS_PASSWORD') ?: '',
            'timeout' => C('DATA_CACHE_TIMEOUT') ?: false,
            'persistent' => false,
        ), $options);

        $this->options = $options;
        $this->options['expire'] = isset($options['expire']) ? $options['expire'] : C('DATA_CACHE_TIME');
        $this->options['prefix'] = isset($options['prefix']) ? $options['prefix'] : C('DATA_CACHE_PREFIX');
        $this->options['length'] = isset($options['length']) ? $options['length'] : 0;
        $func = $options['persistent'] ? 'pconnect' : 'connect';
        $this->handler = new \Redis;
        false === $options['timeout'] ?
            $this->handler->$func($options['host'], $options['port']) :
            $this->handler->$func($options['host'], $options['port'], $options['timeout']);
        if ('' != $options['password']) {
            $this->handler->auth($options['password']);
        }
        $this->handler->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
    }

    /**
     * 未自定义的方法，在调用未定义的方法之前，如果key有缓存前缀，请自行处理
     *
     * @param string $method
     * @param array $args
     * @return anytype
     */
    function __call($method, $args)
    {
        if (strtolower($method) == 'close') {
            $this->handler && $this->handler->close();
        } else {
            return call_user_func_array(array($this->handler, $method), $args);
        }
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name)
    {
        N('cache_read', 1);
        $value = $this->handler->get($this->options['prefix'] . $name);
        $jsonData = NULL;
        if (is_string($value)) {
            $jsonData = json_decode($value, true);
        }
        return (null === $jsonData) ? $value : $jsonData; //检测是否为JSON数据 true 返回JSON解析数组, false返回源数据
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @param integer $expire 有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        N('cache_write', 1);
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        $name = $this->options['prefix'] . $name;
        //对数组/对象数据进行缓存处理，保证数据完整性
        $value = (is_object($value) || is_array($value)) ? json_encode($value) : $value;
        if (is_int($expire) && $expire) {
            $result = $this->handler->setex($name, $expire, $value);
        } else {
            $result = $this->handler->set($name, $value);
        }
        if ($result && $this->options['length'] > 0) {
            // 记录缓存队列
            $this->queue($name);
        }
        return $result;
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $name = $this->options['prefix'] . $name;
        return $this->handler->delete($name);
    }

    /**
     * 返回所有(一个或多个)给定 key 的值。
     * 如果给定的 key 里面，有某个 key 不存在，那么这个 key 返回特殊值 nil 。因此，该命令永不失败。
     *
     * @param string $keyAry
     * @return array 一个包含所有给定 key 的值的列表。
     */
    public function mget($keyAry)
    {
        foreach ($keyAry as $key) {
            $nkey[] = $this->options['prefix'] . $key;
        }
        return $this->handler->mget($nkey);
    }

    /**
     * 同时设置一个或多个 key-value 对。
     * 如果某个给定 key 已经存在，那么 MSET 会用新值覆盖原来的旧值，如果这不是你所希望的效果，请考虑使用 MSETNX 命令：它只会在所有给定 key 都不存在的情况下进行设置操作。
     * MSET 是一个原子性(atomic)操作，所有给定 key 都会在同一时间内被设置，某些给定 key 被更新而另一些给定 key 没有改变的情况，不可能发生。
     *
     * @param string $key
     * @param type $Ary
     * @return string 总是返回 OK (因为 MSET 不可能失败)
     */
    public function mset($key, $Ary)
    {
        return $this->handler->mset($key, $Ary);
    }

    /**
     * 清除缓存
     * @access public
     * @return boolean
     */
    public function clear()
    {
        return $this->handler->flushDB();
    }

    /**
     * 同时将多个 field-value (域-值)对设置到哈希表 key 中。
     * 此命令会覆盖哈希表中已存在的域。
     * 如果 $key 不存在，一个空哈希表被创建并执行 HMSET 操作。
     * @param type $key
     * @param type $value
     * @return type
     * 如果命令执行成功，返回 OK 。
     * 当 $key 不是哈希表(hash)类型时，返回一个错误。
     */
    public function sethash($key, $value)
    {
        $key = $this->options['prefix'] . $key;
        $ret = $this->handler->hMset($key, $value);
        return $ret;
    }

    /**
     * 返回哈希表 $key 中，一个或多个给定域的值。
     * 如果给定的域不存在于哈希表，那么返回一个 nil 值。
     * 因为不存在的 key 被当作一个空哈希表来处理，所以对一个不存在的 key 进行 HMGET 操作将返回一个只带有 nil 值的表。
     * @param type $key
     * @param type $array
     * @return type
     * 一个包含多个给定域的关联值的表，表值的排列顺序和给定域参数的请求顺序一样。
     */
    public function gethash($key, $array)
    {
        $key = $this->options['prefix'] . $key;
        $ret = $this->handler->hMGet($key, $array);
        return $ret;
    }

    /**
     * 为哈希表 key 中的域 field 的值加上增量 increment 。
     * 增量也可以为负数，相当于对给定域进行减法操作。
     * 如果 key 不存在，一个新的哈希表被创建并执行 HINCRBY 命令。
     * 如果域 field 不存在，那么在执行命令前，域的值被初始化为 0 。
     * 对一个储存字符串值的域 field 执行 HINCRBY 命令将造成一个错误。
     * 本操作的值被限制在 64 位(bit)有符号数字表示之内。
     * @param type $key
     * @param type $field
     * @param type $value
     * @return type
     * 执行 HINCRBY 命令之后，哈希表 $key 中域 $field 的值。
     */
    public function hIncrBy($key, $field, $value)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->hIncrBy($key, $field, $value);
    }

    /**
     * 为有序集 $key 的成员 $member 的 $score 值加上增量 $increment 。
     * @param type $key
     * @param type $increment
     * @param type $member
     * @return type
     * $member 成员的新 $score 值，以字符串形式表示。
     */
    public function zIncrBy($key, $increment, $member)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->zIncrBy($key, $increment, $member);
    }

    /**
     * 将 $key 中储存的数字值增一。
     * @param type $key
     * @return type
     * 执行 INCR 命令之后 key 的值。
     */
    public function INCR($key)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->INCR($key);
    }

    /**
     * 为给定 $key 设置生存时间，当 $key 过期时(生存时间为 0 )，它会被自动删除。
     * @param type $key
     * @param type $time
     * @return type
     */
    public function expire($key, $time)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->expire($key, $time);
    }

    /**
     * 返回有序集 $key 中成员 $field 的排名。其中有序集成员按 score 值递减(从大到小)排序。
     * @param type $key
     * @param type $field
     * @return type
     * 排名以 0 为底，也就是说， score 值最大的成员排名为 0 。
     */
    public function ZREVRANK($key, $field)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->ZREVRANK($key, $field);
    }

    /**
     * 返回有序集 key 中，指定区间内的成员。
     * @param type $key
     * @param type $begin
     * @param type $end
     * @return type
     * 其中成员的位置按 score 值递减(从大到小)来排列。与zrange相反。
     */
    public function ZREVRANGE($key, $begin, $end)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->ZREVRANGE($key, $begin, $end);
    }

    /**
     * 返回哈希表 $key 中，所有的域和值。
     * @param type $key
     * @return type
     * 以列表形式返回哈希表的域和域的值。
     * 若 $key 不存在，返回空列表。
     */
    public function HGETALL($key)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->HGETALL($key);
    }

    /**
     * 返回哈希表 $key 中给定域 $field 的值。
     * @param type $key
     * @param type $field
     * @return type
     * 给定域的值。
     * 当给定域不存在或是给定 key 不存在时，返回 nil 。
     */
    public function hGet($key, $field)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->hGet($key, $field);
    }

    /**
     * 返回有序集 key 中，指定区间内的成员。
     *
     * @param string $key
     * @param int $start
     * @param int $stop 下标参数 start 和 stop 都以 0 为底，也就是说，以 0 表示有序集第一个成员，以 1 表示有序集第二个成员，以此类推。
     *                  你也可以使用负数下标，以 -1 表示最后一个成员， -2 表示倒数第二个成员，以此类推。
     *                  超出范围的下标并不会引起错误。
     * @return array
     */
    public function zRange($key, $start, $stop)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->zRange($key, $start, $stop);
    }

    /**
     * 返回有序集 $key 中，成员 $field 的 score 值。
     *
     * @param mixed $key
     * @param mixed $field
     */
    public function ZSCORE($key, $field = '')
    {
        if (!$field) {
            return false;
        }
        $key = $this->options['prefix'] . $key;
        return $this->handler->ZSCORE($key, $field);
    }

    /**
     * 标记一个事务块的开始。
     * 事务块内的多条命令会按照先后顺序被放进一个队列当中，最后由 EXEC 命令原子性(atomic)地执行。
     *
     * @return string 总是返回 OK 。
     */
    public function multi()
    {
        return $this->handler->multi();
    }

    /**
     * 将 $key 的值设为 $value ，当且仅当 $key 不存在。
     * 若给定的 $key 已经存在，则 SETNX 不做任何动作。
     * SETNX 是『SET if Not eXists』(如果不存在，则 SET)的简写。
     * @param type $key
     * @param type $value
     * @return int 成功：1，失败：0
     */
    public function setnx($key, $value)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->setnx($key, $value);
    }

    /**
     * 返回名称为key的zset的所有元素的个数
     *
     * @param mixed $key
     */
    public function zSize($key)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->zSize($key);
    }

    /**
     * 删除有序集合保存在key开始和结束的排序所有元素。无论是开始和结束都以0基础索引，其中0是得分最低的元素。
     * 这些索引可以是负数，在那里它们表明起始于具有最高得分的元素偏移。例如：-1是具有最高得分的元素，-2与第二最高得分等的元素。
     *
     * @param mixed $key
     * @param mixed $start
     * @param mixed $len
     */
    public function zRemRangeByRank($key, $start, $len)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->zRemRangeByRank($key, $start, $len);
    }

    /**
     * 移除并返回列表 key 的头元素。
     * @param string $key
     * @return type 列表的头元素。当 key 不存在时，返回 nil 。
     */
    public function lpop($key)
    {
        return $this->handler->lpop($key);
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表尾(最右边)。
     * 如果有多个 value 值，那么各个 value 值按从左到右的顺序依次插入到表尾：比如对一个空列表 mylist 执行 RPUSH mylist a b c ，得出的结果列表为 a b c ，等同于执行命令 RPUSH mylist a 、 RPUSH mylist b 、 RPUSH mylist c 。
     * 如果 key 不存在，一个空列表会被创建并执行 RPUSH 操作。
     * 当 key 存在但不是列表类型时，返回一个错误。
     *
     * @param string $key
     * @param array $dat
     * @return int 执行 RPUSH 操作后，表的长度。
     */
    public function rpush($key, $dat)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->rpush($key, $dat);
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表头
     * 如果有多个 value 值，那么各个 value 值按从左到右的顺序依次插入到表头： 比如说，对空列表 mylist 执行命令 LPUSH mylist a b c ，列表的值将是 c b a ，这等同于原子性地执行 LPUSH mylist a 、 LPUSH mylist b 和 LPUSH mylist c 三个命令。
     * 如果 key 不存在，一个空列表会被创建并执行 LPUSH 操作。
     * 当 key 存在但不是列表类型时，返回一个错误。
     *
     * @param string $key
     * @param array $dat
     * @return int 执行 LPUSH 操作后，表的长度。
     */
    public function lpush($key, $dat)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->lpush($key, $dat);
    }

}
