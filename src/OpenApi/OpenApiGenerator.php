<?php

declare(strict_types=1);

namespace Think\OpenApi;

use Think\Container;

/**
 * OpenAPI 生成器
 *
 * 从路由信息生成 OpenAPI 3.0 规范
 *
 * @package Think\OpenApi
 */
class OpenApiGenerator
{
    /**
     * DocBlock 解析器
     *
     * @var DocBlockParser
     */
    protected DocBlockParser $docParser;

    /**
     * OpenAPI 信息
     *
     * @var array
     */
    protected array $info = [];

    /**
     * 构造函数
     *
     * @param DocBlockParser|null $docParser
     */
    public function __construct(?DocBlockParser $docParser = null)
    {
        $this->docParser = $docParser ?? new DocBlockParser();
        $this->initializeInfo();
    }

    /**
     * 初始化 OpenAPI 信息
     *
     * @return void
     */
    protected function initializeInfo(): void
    {
        $this->info = [
            'title' => $this->getConfig('OPENAPI_TITLE', 'ThinkPHP API'),
            'version' => $this->getConfig('OPENAPI_VERSION', '1.0.0'),
        ];
    }

    /**
     * 生成 OpenAPI 规范
     *
     * @param array $routes 路由列表
     * @return array OpenAPI 规范数组
     */
    public function generate(array $routes): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->info['title'],
                'version' => $this->info['version'],
            ],
            'paths' => $this->generatePaths($routes),
            'tags' => $this->generateTags(),
        ];

        return $spec;
    }

    /**
     * 生成 paths 部分
     *
     * @param array $routes 路由列表
     * @return array
     */
    protected function generatePaths(array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $uriTemplate = $route['uriTemplate'] ?? '';
            $methods = $route['methods'] ?? [];
            $handler = $route['handler'] ?? null;

            if (!$uriTemplate || $methods === []) {
                continue;
            }

            // 跳过非字符串 handler（如闭包）
            if (!is_string($handler)) {
                continue;
            }

            // 解析 handler（Controller@action 格式）
            [$controller, $action] = $this->parseHandler($handler);

            // 跳过无法解析的 handler
            if (!$controller || !$action) {
                continue;
            }

            // 解析 DocBlock
            $docInfo = $this->docParser->parseControllerAction($controller, $action);

            // 为每个 HTTP 方法生成 operation
            foreach ($methods as $method) {
                $method = strtolower($method);

                if (!isset($paths[$uriTemplate])) {
                    $paths[$uriTemplate] = [];
                }

                $paths[$uriTemplate][$method] = $this->generateOperation(
                    $method,
                    $uriTemplate,
                    $controller,
                    $action,
                    $docInfo
                );
            }
        }

        return $paths;
    }

    /**
     * 生成单个 operation
     *
     * @param string $method HTTP 方法
     * @param string $uriTemplate URI 模板
     * @param string $controller 控制器
     * @param string $action 方法
     * @param array $docInfo 文档信息
     * @return array
     */
    protected function generateOperation(
        string $method,
        string $uriTemplate,
        string $controller,
        string $action,
        array $docInfo
    ): array {
        $operation = [
            'summary' => $docInfo['summary'] ?: "{$method} {$uriTemplate}",
            'description' => $docInfo['description'],
            'operationId' => $this->generateOperationId($controller, $action, $method),
            'tags' => $docInfo['tags'],
            'responses' => [
                '200' => [
                    'description' => 'OK',
                ],
            ],
        ];

        // 添加路径参数
        $parameters = $this->docParser->parsePathParameters($uriTemplate);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        return $operation;
    }

    /**
     * 生成 operationId
     *
     * @param string $controller
     * @param string $action
     * @param string $method
     * @return string
     */
    protected function generateOperationId(string $controller, string $action, string $method): string
    {
        // 移除命名空间前缀
        $controllerShort = strrchr($controller, '\\');
        if ($controllerShort === false) {
            $controllerShort = $controller;
        } else {
            $controllerShort = substr($controllerShort, 1);
        }

        return strtolower($method . '_' . $controllerShort . '_' . $action);
    }

    /**
     * 生成 tags 列表
     *
     * @return array
     */
    protected function generateTags(): array
    {
        // 这里可以返回预定义的 tags
        // 目前先返回一个默认 tag
        return [
            [
                'name' => 'default',
                'description' => 'Default API operations',
            ],
        ];
    }

    /**
     * 解析 handler
     *
     * @param string|null $handler
     * @return array [controller, action]
     */
    protected function parseHandler(?string $handler): array
    {
        if (!$handler || strpos($handler, '@') === false) {
            return ['', ''];
        }

        [$controller, $action] = explode('@', $handler, 2);

        return [$controller, $action];
    }

    /**
     * 获取配置值
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        if (function_exists('C')) {
            return C($key) ?: $default;
        }

        return $default;
    }

    /**
     * 设置 OpenAPI 信息
     *
     * @param string $title
     * @param string $version
     * @return self
     */
    public function setInfo(string $title, string $version): self
    {
        $this->info['title'] = $title;
        $this->info['version'] = $version;
        return $this;
    }
}
