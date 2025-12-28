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

namespace Think\Middleware;

use Think\Behavior;
use Think\Exception;

/**
 * 认证中间件
 *
 * 验证用户登录状态，支持 Session/JWT 两种方式
 * 自动注入当前用户到控制器
 *
 * @package Think\Middleware
 */
class AuthMiddleware extends Behavior
{
    /**
     * @var array 白名单路由（不需要认证）
     */
    private $whitelist;

    /**
     * @var string 认证类型
     */
    private $authType;

    /**
     * @var string Session 键名
     */
    private $sessionKey;

    /**
     * @var string Token Header 名称
     */
    private $tokenHeader;

    /**
     * 执行行为
     *
     * @param mixed $params 参数
     * @return void
     * @throws Exception
     */
    public function run(&$params)
    {
        // 检查是否启用认证
        if (!$this->isEnabled()) {
            return;
        }

        // 检查是否在白名单中
        if ($this->isInWhitelist()) {
            return;
        }

        // 验证用户身份
        $userId = $this->authenticate();

        if (!$userId) {
            $this->handleAuthFailure();
        }

        // 注入当前用户 ID 到配置
        C('CURRENT_USER_ID', $userId);
        C('CURRENT_USER', $this->getUser($userId));
    }

    /**
     * 验证用户身份
     *
     * @return string|null 用户 ID
     */
    private function authenticate(): ?string
    {
        $this->authType = C('AUTH_TYPE', 'session');

        if ($this->authType === 'jwt') {
            return $this->authenticateByJwt();
        }

        return $this->authenticateBySession();
    }

    /**
     * 通过 Session 验证
     *
     * @return string|null 用户 ID
     */
    private function authenticateBySession(): ?string
    {
        $this->sessionKey = C('AUTH_SESSION_KEY', 'user_id');

        if (isset($_SESSION[$this->sessionKey]) && !empty($_SESSION[$this->sessionKey])) {
            return (string)$_SESSION[$this->sessionKey];
        }

        return null;
    }

    /**
     * 通过 JWT 验证
     *
     * @return string|null 用户 ID
     */
    private function authenticateByJwt(): ?string
    {
        $this->tokenHeader = C('AUTH_TOKEN_HEADER', 'Authorization');
        $header = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $this->tokenHeader))] ?? '';

        if (empty($header)) {
            return null;
        }

        // 提取 Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = $matches[1];
            return $this->verifyJwtToken($token);
        }

        return null;
    }

    /**
     * 验证 JWT Token (完整签名验证)
     *
     * 安全改进：
     * - 强制校验签名（禁止无签名或伪造签名）
     * - 防止算法混淆攻击（只接受配置中指定的算法）
     * - 支持 HS256（HMAC-SHA256）和 RS256（RSA-SHA256）
     * - 完整的 claims 验证（exp/nbf/iss/aud）
     *
     * 配置要求：
     * - AUTH_JWT_ALG: 算法类型，'HS256' 或 'RS256'（默认 HS256）
     * - AUTH_JWT_SECRET: HS256 共享密钥（必填）
     * - AUTH_JWT_PUBLIC_KEY: RS256 公钥（PEM 内容或文件路径）
     * - AUTH_JWT_LEEWAY: 时间容忍秒数（默认 0）
     * - AUTH_JWT_ISSUER: 可选签发者验证
     * - AUTH_JWT_AUDIENCE: 可选受众验证
     *
     * @param string $token JWT Token
     * @return string|null 用户 ID
     */
    private function verifyJwtToken(string $token): ?string
    {
        $token = trim($token);

        // 基础验证：长度限制
        if ($token === '' || strlen($token) > 8192) {
            return null;
        }

        // JWT 格式：header.payload.signature
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // Base64URL 解码
        $headerJson = $this->base64UrlDecode($encodedHeader);
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($headerJson === null || $payloadJson === null || $signature === null) {
            return null;
        }

        // 解析和验证 header
        $header = $this->jsonDecodeArray($headerJson);
        if ($header === null) {
            return null;
        }

        // 防止算法混淆：禁止 'none' 和空算法
        $algInToken = strtoupper((string)($header['alg'] ?? ''));
        if ($algInToken === '' || $algInToken === 'NONE') {
            return null;
        }

        // 只接受配置中指定的算法
        $expectedAlg = strtoupper((string)C('AUTH_JWT_ALG', 'HS256'));
        if ($algInToken !== $expectedAlg) {
            error_log("Security Warning: JWT algorithm mismatch. Expected: {$expectedAlg}, Got: {$algInToken}");
            return null;
        }

        // 验证签名
        $signingInput = $encodedHeader . '.' . $encodedPayload;
        if (!$this->verifyJwtSignature($expectedAlg, $signingInput, $signature)) {
            error_log('Security Warning: JWT signature verification failed');
            return null;
        }

        // 解析和验证 claims
        $claims = $this->jsonDecodeArray($payloadJson);
        if ($claims === null) {
            return null;
        }

        if (!$this->validateJwtClaims($claims)) {
            return null;
        }

        // 提取用户 ID
        $userId = $claims['user_id'] ?? null;
        if (!is_string($userId) && !is_int($userId)) {
            return null;
        }

        return (string)$userId;
    }

    /**
     * Base64URL 解码（JWT 规范）
     *
     * @param string $input Base64URL 编码的字符串
     * @return string|null 解码后的原始字符串，失败返回 null
     */
    private function base64UrlDecode(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // 只允许 base64url 字符集（防止异常字符绕过）
        if (preg_match('/[^A-Za-z0-9\-_]/', $input)) {
            return null;
        }

        // 补齐 padding
        $remainder = strlen($input) % 4;
        if ($remainder !== 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        // 转换 base64url 为 base64
        $decoded = base64_decode(strtr($input, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    /**
     * JSON 解码为数组
     *
     * @param string $json JSON 字符串
     * @return array|null 解码后的数组，失败返回 null
     */
    private function jsonDecodeArray(string $json): ?array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    /**
     * 验证 JWT 签名
     *
     * @param string $alg 算法（HS256 或 RS256）
     * @param string $signingInput 待签名数据（header.payload）
     * @param string $signatureRaw 原始签名二进制数据
     * @return bool 验证成功返回 true
     */
    private function verifyJwtSignature(string $alg, string $signingInput, string $signatureRaw): bool
    {
        if ($alg === 'HS256') {
            // HMAC-SHA256 验证
            $secret = (string)C('AUTH_JWT_SECRET', '');
            if ($secret === '') {
                error_log('Security Warning: AUTH_JWT_SECRET not configured');
                return false;
            }

            $expected = hash_hmac('sha256', $signingInput, $secret, true);

            // 使用 hash_equals 防止时序攻击
            return hash_equals($expected, $signatureRaw);
        }

        if ($alg === 'RS256') {
            // RSA-PSS (PKCS#1 v1.5) SHA256 验证
            if (!function_exists('openssl_verify')) {
                error_log('Security Warning: OpenSSL extension not available');
                return false;
            }

            $publicKey = $this->loadJwtPublicKey();
            if ($publicKey === null) {
                error_log('Security Warning: Failed to load JWT public key');
                return false;
            }

            $result = openssl_verify($signingInput, $signatureRaw, $publicKey, OPENSSL_ALGO_SHA256);

            return $result === 1;
        }

        return false;
    }

    /**
     * 读取 RS256 公钥（PEM 内容或文件路径）
     *
     * @return string|null PEM 格式的公钥，失败返回 null
     */
    private function loadJwtPublicKey(): ?string
    {
        $value = C('AUTH_JWT_PUBLIC_KEY', '');
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // 如果是文件路径，读取文件内容
        if (is_file($value) && is_readable($value)) {
            $content = @file_get_contents($value);
            if ($content === false) {
                error_log("Security Warning: Failed to read JWT public key file: {$value}");
                return null;
            }
            return $content;
        }

        // 否则作为 PEM 内容直接使用
        return $value;
    }

    /**
     * 验证 JWT Claims（标准字段）
     *
     * 验证字段：
     * - exp (expiration time): 过期时间
     * - nbf (not before): 生效时间
     * - iss (issuer): 签发者（可选）
     * - aud (audience): 受众（可选，支持 string 或 array）
     *
     * @param array $claims JWT claims
     * @return bool 验证成功返回 true
     */
    private function validateJwtClaims(array $claims): bool
    {
        $now = time();
        $leeway = (int)C('AUTH_JWT_LEEWAY', 0);

        // nbf：未到生效时间
        if (isset($claims['nbf']) && is_numeric($claims['nbf'])) {
            if (($now + $leeway) < (int)$claims['nbf']) {
                error_log('Security Warning: JWT token not yet valid (nbf)');
                return false;
            }
        }

        // exp：已过期（JWT 规范：now >= exp 即为过期）
        if (isset($claims['exp']) && is_numeric($claims['exp'])) {
            if (($now - $leeway) >= (int)$claims['exp']) {
                error_log('Security Warning: JWT token expired (exp)');
                return false;
            }
        }

        // iss：可选签发者验证
        $expectedIss = C('AUTH_JWT_ISSUER', '');
        if (is_string($expectedIss) && $expectedIss !== '') {
            if (!isset($claims['iss']) || (string)$claims['iss'] !== $expectedIss) {
                error_log("Security Warning: JWT issuer mismatch. Expected: {$expectedIss}");
                return false;
            }
        }

        // aud：可选受众验证（支持 string 或 array）
        $expectedAud = C('AUTH_JWT_AUDIENCE', '');
        if (is_string($expectedAud) && $expectedAud !== '') {
            $aud = $claims['aud'] ?? null;
            if (is_string($aud)) {
                if ($aud !== $expectedAud) {
                    error_log("Security Warning: JWT audience mismatch. Expected: {$expectedAud}");
                    return false;
                }
            } elseif (is_array($aud)) {
                if (!in_array($expectedAud, $aud, true)) {
                    error_log("Security Warning: JWT audience not in list. Expected: {$expectedAud}");
                    return false;
                }
            } else {
                error_log('Security Warning: JWT audience invalid type');
                return false;
            }
        }

        return true;
    }

    /**
     * 获取用户信息
     *
     * @param string $userId 用户 ID
     * @return array|null
     */
    private function getUser(string $userId): ?array
    {
        $userModel = C('AUTH_USER_MODEL', 'User');

        try {
            $user = M($userModel)->find($userId);

            if ($user) {
                return is_array($user) ? $user : $user->toArray();
            }
        } catch (\Exception $e) {
            // 用户模型不存在或查询失败
            // 记录 debug 级别日志，避免暴露敏感信息
            if (C('LOG_RECORD')) {
                \Think\Log::record("Auth middleware: Failed to load user model '{$userModel}' for user '{$userId}'. Error: " . $e->getMessage(), 'DEBUG');
            }
        }

        return null;
    }

    /**
     * 判断认证是否启用
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return C('AUTH_ON', false) === true;
    }

    /**
     * 判断当前路由是否在白名单中
     *
     * @return bool
     */
    private function isInWhitelist(): bool
    {
        $this->whitelist = C('AUTH_WHITE_LIST', []);

        if (empty($this->whitelist)) {
            return false;
        }

        $route = $this->getCurrentRoute();

        return $this->matchWildcard($route, $this->whitelist);
    }

    /**
     * 获取当前路由
     *
     * @return string
     */
    private function getCurrentRoute(): string
    {
        $module = defined('MODULE_NAME') ? MODULE_NAME : '';
        $controller = defined('CONTROLLER_NAME') ? CONTROLLER_NAME : '';
        $action = defined('ACTION_NAME') ? ACTION_NAME : '';

        $parts = array_filter([$module, $controller, $action]);
        return implode('/', array_map('strtolower', $parts));
    }

    /**
     * 通配符匹配
     *
     * @param string $value 待匹配的值
     * @param array $patterns 模式列表
     * @return bool
     */
    private function matchWildcard(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if ($pattern === $value) {
                return true;
            }

            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (@preg_match($regex, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 处理认证失败
     *
     * @return void
     * @throws Exception
     */
    private function handleAuthFailure(): void
    {
        // 判断是否为 AJAX 请求
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // 判断是否期望 JSON 响应
        $expectJson = $isAjax || $this->expectsJson();

        if ($expectJson) {
            $this->sendJsonResponse();
        }

        // 跳转到登录页
        $loginUrl = C('AUTH_LOGIN_URL', U('Home/User/login'));
        redirect($loginUrl)->send();
        exit;
    }

    /**
     * 判断是否期望 JSON 响应
     *
     * @return bool
     */
    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return stripos($accept, 'application/json') !== false;
    }

    /**
     * 发送 JSON 响应
     *
     * @return void
     */
    private function sendJsonResponse(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 401 Unauthorized');
        }

        echo json_encode([
            'code' => 401,
            'message' => C('AUTH_FAIL_MESSAGE', '未登录或登录已过期'),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
