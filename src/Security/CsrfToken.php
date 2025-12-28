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

namespace Think\Security;

/**
 * CSRF Token 值对象
 *
 * 不可变的 token 对象，包含 ID 和值
 *
 * @package Think\Security
 */
class CsrfToken
{
    /**
     * @var string Token ID（用于区分不同表单/意图）
     */
    private $id;

    /**
     * @var string Token 值
     */
    private $value;

    /**
     * 构造函数
     *
     * @param string $id Token ID
     * @param string $value Token 值
     */
    public function __construct(string $id, string $value)
    {
        $this->id = $id;
        $this->value = $value;
    }

    /**
     * 获取 Token ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 获取 Token 值
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 转换为字符串
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
