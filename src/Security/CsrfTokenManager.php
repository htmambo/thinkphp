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
 * CSRF Token 管理器
 *
 * 提供统一的 Token 生成、验证、刷新接口
 * Laravel 风格：使用 CSPRNG 生成，session 固定 token
 *
 * @package Think\Security
 */
class CsrfTokenManager
{
    /**
     * @var CsrfTokenRepositoryInterface Token 存储接口
     */
    private $repository;

    /**
     * @var string Token ID（默认�� 'default'）
     */
    private $tokenId;

    /**
     * 构造函数
     *
     * @param CsrfTokenRepositoryInterface|null $repository Token 存储接口
     * @param string $tokenId Token ID
     */
    public function __construct(
        ?CsrfTokenRepositoryInterface $repository = null,
        string $tokenId = 'default'
    ) {
        $this->repository = $repository ?: $this->createDefaultRepository();
        $this->tokenId = $tokenId;
    }

    /**
     * 生成 Token
     *
     * 使用 CSPRNG 生成安全的随机 Token
     *
     * @param string|null $tokenId Token ID，为 null 时使用构造函数指定的 ID
     * @return CsrfToken
     */
    public function generate(?string $tokenId = null): CsrfToken
    {
        $tokenId = $tokenId ?: $this->tokenId;

        // 使用 CSPRNG 生成 32 字节随机数，转换为 64 字符十六进制
        $value = $this->generateRandomToken();

        $token = new CsrfToken($tokenId, $value);

        $this->repository->save($token);

        return $token;
    }

    /**
     * 验证 Token
     *
     * 使用常量时间比较，防止时序攻击
     *
     * @param string $value Token 值
     * @param string|null $tokenId Token ID
     * @return bool
     */
    public function validate(string $value, ?string $tokenId = null): bool
    {
        $tokenId = $tokenId ?: $this->tokenId;

        $token = $this->repository->get($tokenId);

        if (!$token) {
            return false;
        }

        // 使用常量时间比较
        return $this->hashEquals($token->getValue(), $value);
    }

    /**
     * 验证并删除 Token（一次性 token）
     *
     * @param string $value Token 值
     * @param string|null $tokenId Token ID
     * @return bool
     */
    public function validateAndRemove(string $value, ?string $tokenId = null): bool
    {
        $tokenId = $tokenId ?: $this->tokenId;

        $isValid = $this->validate($value, $tokenId);

        if ($isValid) {
            $this->repository->remove($tokenId);
        }

        return $isValid;
    }

    /**
     * 刷新 Token
     *
     * 生成新的 Token 并删除旧的
     *
     * @param string|null $tokenId Token ID
     * @return CsrfToken
     */
    public function refresh(?string $tokenId = null): CsrfToken
    {
        $tokenId = $tokenId ?: $this->tokenId;

        $this->repository->remove($tokenId);

        return $this->generate($tokenId);
    }

    /**
     * 获取当前 Token
     *
     * 如果不存在则自动生成
     *
     * @param string|null $tokenId Token ID
     * @return CsrfToken
     */
    public function getToken(?string $tokenId = null): CsrfToken
    {
        $tokenId = $tokenId ?: $this->tokenId;

        $token = $this->repository->get($tokenId);

        if (!$token) {
            $token = $this->generate($tokenId);
        }

        return $token;
    }

    /**
     * 检查 Token 是否存在
     *
     * @param string|null $tokenId Token ID
     * @return bool
     */
    public function hasToken(?string $tokenId = null): bool
    {
        $tokenId = $tokenId ?: $this->tokenId;

        return $this->repository->has($tokenId);
    }

    /**
     * 生成随机 Token
     *
     * 使用 CSPRNG 生成安全的随机字符串
     *
     * @return string 64 字符的十六进制字符串
     */
    private function generateRandomToken(): string
    {
        // 优先使用 random_bytes（PHP 7+）
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }

        // 降级使用 openssl_random_pseudo_bytes
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(32);
            if ($bytes !== false) {
                return bin2hex($bytes);
            }
        }

        // 最后降级使用 mt_rand（不推荐，但保持兼容）
        $token = '';
        for ($i = 0; $i < 32; $i++) {
            $token .= sprintf('%02x', mt_rand(0, 255));
        }

        return $token;
    }

    /**
     * 常量时间字符串比较
     *
     * 防止时序攻击（Timing Attack）
     *
     * @param string $known 已知值
     * @param string $user 用户输入值
     * @return bool
     */
    private function hashEquals(string $known, string $user): bool
    {
        // 优先使用 hash_equals（PHP 5.6+）
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }

        // 降级实现
        if (strlen($known) !== strlen($user)) {
            return false;
        }

        $result = 0;
        $len = strlen($known);

        for ($i = 0; $i < $len; $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }

    /**
     * 创建默认的 Repository
     *
     * @return CsrfTokenRepositoryInterface
     */
    private function createDefaultRepository(): CsrfTokenRepositoryInterface
    {
        $sessionKey = C('CSRF_SESSION_KEY', '_csrf_tokens');
        $lifetime = C('CSRF_LIFETIME', null, 7200);
        $oneTime = C('CSRF_ONE_TIME', null, false);

        return new SessionCsrfTokenRepository($sessionKey, $lifetime, $oneTime);
    }

    /**
     * 设置 Token ID
     *
     * @param string $tokenId
     * @return self
     */
    public function withTokenId(string $tokenId): self
    {
        $instance = clone $this;
        $instance->tokenId = $tokenId;

        return $instance;
    }

    /**
     * 获取 Token ID
     *
     * @return string
     */
    public function getTokenId(): string
    {
        return $this->tokenId;
    }
}
