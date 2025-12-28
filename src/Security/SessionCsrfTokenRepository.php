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
 * Session 存储的 CSRF Token Repository
 *
 * 使用 Session 存储 Token，支持固定 token 和一次性 token 两种模式
 *
 * @package Think\Security
 */
class SessionCsrfTokenRepository implements CsrfTokenRepositoryInterface
{
    /**
     * @var string Session 键名前缀
     */
    private $sessionKey;

    /**
     * @var int Token 有效期（秒）
     */
    private $lifetime;

    /**
     * @var bool 是否为一次性 token
     */
    private $oneTime;

    /**
     * 构造函数
     *
     * @param string $sessionKey Session 键名前缀
     * @param int $lifetime Token 有效期（秒），0 表示不过期
     * @param bool $oneTime 是否为一次性 token
     */
    public function __construct(
        string $sessionKey = '_csrf_tokens',
        int $lifetime = 7200,
        bool $oneTime = false
    ) {
        $this->sessionKey = $sessionKey;
        $this->lifetime = $lifetime;
        $this->oneTime = $oneTime;
    }

    /**
     * 获取 Token
     *
     * @param string $tokenId Token ID
     * @return CsrfToken|null
     */
    public function get(string $tokenId): ?CsrfToken
    {
        if (!isset($_SESSION[$this->sessionKey][$tokenId])) {
            return null;
        }

        $data = $_SESSION[$this->sessionKey][$tokenId];

        // 检查是否过期
        if ($this->lifetime > 0 && isset($data['expires']) && time() > $data['expires']) {
            $this->remove($tokenId);
            return null;
        }

        return new CsrfToken($tokenId, $data['value']);
    }

    /**
     * 保存 Token
     *
     * @param CsrfToken $token Token 对象
     * @return void
     */
    public function save(CsrfToken $token): void
    {
        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }

        $expires = $this->lifetime > 0 ? time() + $this->lifetime : 0;

        $_SESSION[$this->sessionKey][$token->getId()] = [
            'value' => $token->getValue(),
            'created' => time(),
            'expires' => $expires,
        ];
    }

    /**
     * 删除 Token
     *
     * @param string $tokenId Token ID
     * @return void
     */
    public function remove(string $tokenId): void
    {
        unset($_SESSION[$this->sessionKey][$tokenId]);
    }

    /**
     * 检查 Token 是否存在
     *
     * @param string $tokenId Token ID
     * @return bool
     */
    public function has(string $tokenId): bool
    {
        return $this->get($tokenId) !== null;
    }

    /**
     * 清理过期的 Token
     *
     * @return void
     */
    public function purge(): void
    {
        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            return;
        }

        $now = time();

        foreach ($_SESSION[$this->sessionKey] as $tokenId => $data) {
            if ($this->lifetime > 0 && isset($data['expires']) && $now > $data['expires']) {
                unset($_SESSION[$this->sessionKey][$tokenId]);
            }
        }
    }

    /**
     * 清空所有 Token
     *
     * @return void
     */
    public function clear(): void
    {
        $_SESSION[$this->sessionKey] = [];
    }

    /**
     * 获取所有 Token ID
     *
     * @return array
     */
    public function getIds(): array
    {
        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            return [];
        }

        return array_keys($_SESSION[$this->sessionKey]);
    }
}
