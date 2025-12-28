<?php

declare(strict_types=1);

namespace Think\Queue;

use ArrayAccess;

/**
 * 简单的配置仓储类
 *
 * 为 Illuminate Queue 提供配置访问，支持点号分隔的键名
 *
 * @package Think\Queue
 */
class ConfigRepository implements ArrayAccess
{
    /**
     * 配置数据
     *
     * @var array
     */
    protected array $items = [];

    /**
     * 构造函数
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * 获取配置值
     *
     * 支持点号分隔的键名，如 'queue.default'
     *
     * @param string $key 配置键（支持点号分隔）
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 设置配置值
     *
     * @param string $key 配置键（支持点号分隔）
     * @param mixed $value 配置值
     * @return void
     */
    public function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $array = &$this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }

        $array[array_shift($segments)] = $value;
    }

    /**
     * 检查配置键是否存在
     *
     * @param string $key 配置键
     * @return bool
     */
    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * 获取所有配置
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 判断配置键是否存在（ArrayAccess 接口）
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * 获取配置值（ArrayAccess 接口）
     *
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * 设置配置值（ArrayAccess 接口）
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * 删除配置值（ArrayAccess 接口）
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $this->set($offset, null);
    }
}
