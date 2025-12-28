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
 * CSRF Token 存储接口
 *
 * 定义 Token 的存储规范，支持多种存储方式（Session、Cookie、缓存等）
 *
 * @package Think\Security
 */
interface CsrfTokenRepositoryInterface
{
    /**
     * 获取 Token
     *
     * @param string $tokenId Token ID
     * @return CsrfToken|null Token 对象，不存在时返回 null
     */
    public function get(string $tokenId): ?CsrfToken;

    /**
     * 保存 Token
     *
     * @param CsrfToken $token Token 对象
     * @return void
     */
    public function save(CsrfToken $token): void;

    /**
     * 删除 Token
     *
     * @param string $tokenId Token ID
     * @return void
     */
    public function remove(string $tokenId): void;

    /**
     * 检查 Token 是否存在
     *
     * @param string $tokenId Token ID
     * @return bool
     */
    public function has(string $tokenId): bool;
}
