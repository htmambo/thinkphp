<?php

declare(strict_types=1);

namespace Think;

/**
 * HTTP 响应抽象层
 *
 * 封装 HTTP 响应的状态码、头部和内容。
 *
 * 设计理念：
 * - 不可变对象（所有修改返回新实例）
 * - 支持链式调用
 * - 提供 JSON、重定向等常用响应工厂方法
 * - 向后兼容（可以逐步替换现有的 header() exit() 模式）
 */
final class Response
{
    /**
     * HTTP 状态码
     * @var int
     */
    private int $statusCode = 200;

    /**
     * HTTP 响应头
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * 响应内容
     * @var string
     */
    private string $body = '';

    /**
     * 构造函数
     *
     * @param string $body 响应内容
     * @param int $statusCode HTTP 状态码
     * @param array<string, string> $headers 响应头
     */
    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * 设置状态码（返回新实例）
     *
     * @param int $statusCode HTTP 状态码
     * @return self
     */
    public function withStatus(int $statusCode): self
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;
        return $clone;
    }

    /**
     * 添加响应头（返回新实例）
     *
     * @param string $name 头名称
     * @param string $value 头值
     * @param bool $replace 是否替换已存在的头
     * @return self
     */
    public function withHeader(string $name, string $value, bool $replace = true): self
    {
        $clone = clone $this;
        $key = strtolower($name);

        if ($replace || !isset($clone->headers[$key])) {
            $clone->headers[$key] = $value;
        }

        return $clone;
    }

    /**
     * 设置响应内容（返回新实例）
     *
     * @param string $body 内容
     * @return self
     */
    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * 添加 Cookie（返回新实例）
     *
     * @param string $name Cookie 名
     * @param string $value Cookie 值
     * @param int $expire 过期时间（秒）
     * @param string $path 路径
     * @param string $domain 域名
     * @param bool $secure 是否仅 HTTPS
     * @param bool $httpOnly 是否仅 HTTP
     * @return self
     */
    public function withCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        $clone = clone $this;

        // 将 Cookie 信息存储在特殊头部中（稍后发送）
        $clone->headers['set-cookie'] = $name . '=' . urlencode($value)
            . '; expires=' . gmdate('D, d M Y H:i:s T', $expire > 0 ? time() + $expire : 0)
            . '; path=' . $path
            . ($domain !== '' ? '; domain=' . $domain : '')
            . ($secure ? '; secure' : '')
            . ($httpOnly ? '; httponly' : '');

        return $clone;
    }

    /**
     * 创建 JSON 响应
     *
     * @param mixed $data 数据
     * @param int $statusCode 状态码
     * @param array<string, string> $headers 额外头部
     * @param int $options JSON 编码选项
     * @return self
     */
    public static function json(
        mixed $data,
        int $statusCode = 200,
        array $headers = [],
        int $options = JSON_UNESCAPED_UNICODE
    ): self {
        $body = json_encode($data, $options);

        if ($body === false) {
            $body = '{"error":"JSON encoding failed"}';
        }

        $response = new self($body, $statusCode);
        $response->headers['content-type'] = 'application/json; charset=utf-8';

        foreach ($headers as $name => $value) {
            $response->headers[strtolower($name)] = $value;
        }

        return $response;
    }

    /**
     * 创建重定向响应
     *
     * @param string $url 目标 URL
     * @param int $statusCode 状态码（默认 302）
     * @return self
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['location' => $url]);
    }

    /**
     * 创建下载响应
     *
     * @param string $content 文件内容
     * @param string $filename 下载时的文件名
     * @return self
     */
    public static function download(string $content, string $filename): self
    {
        return new self($content, 200, [
            'content-type' => 'application/octet-stream',
            'content-disposition' => 'attachment; filename="' . $filename . '"',
            'content-length' => (string)strlen($content),
        ]);
    }

    /**
     * 获取状态码
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取响应头
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 获取响应内容
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * 发送响应到客户端
     *
     * @return void
     */
    public function send(): void
    {
        // 检查是否已发送头部
        if (!headers_sent()) {
            // 设置状态码
            http_response_code($this->statusCode);

            // 发送响应头
            foreach ($this->headers as $name => $value) {
                if ($name === 'set-cookie') {
                    // Cookie 需要特殊处理
                    header('Set-Cookie: ' . $value, false);
                } else {
                    header($name . ': ' . $value, true);
                }
            }
        }

        // 输出响应体
        echo $this->body;
    }

    /**
     * 发送响应并终止脚本
     *
     * @return never
     */
    public function sendAndExit(): never
    {
        $this->send();
        exit;
    }

    /**
     * 创建 HTML 响应
     *
     * @param string $html HTML 内容
     * @param int $statusCode 状态码
     * @return self
     */
    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, [
            'content-type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * 创建文本响应
     *
     * @param string $text 文本内容
     * @param int $statusCode 状态码
     * @return self
     */
    public static function text(string $text, int $statusCode = 200): self
    {
        return new self($text, $statusCode, [
            'content-type' => 'text/plain; charset=utf-8',
        ]);
    }

    /**
     * 创建空响应（204 No Content）
     *
     * @return self
     */
    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * 创建未授权响应（401 Unauthorized）
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::json(['error' => $message], 401);
    }

    /**
     * 创建禁止访问响应（403 Forbidden）
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::json(['error' => $message], 403);
    }

    /**
     * 创建未找到响应（404 Not Found）
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function notFound(string $message = 'Not Found'): self
    {
        return self::json(['error' => $message], 404);
    }

    /**
     * 创建错误响应（500 Internal Server Error）
     *
     * @param string $message 错误消息
     * @param int $statusCode 状态码
     * @return self
     */
    public static function error(string $message = 'Internal Server Error', int $statusCode = 500): self
    {
        return self::json(['error' => $message], $statusCode);
    }
}
