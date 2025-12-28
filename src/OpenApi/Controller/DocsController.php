<?php

declare(strict_types=1);

namespace Think\OpenApi\Controller;

use Think\Response;

/**
 * API 文档控制器
 *
 * 提供 Swagger UI 界面和 OpenAPI Spec
 *
 * @package Think\OpenApi\Controller
 */
class DocsController
{
    /**
     * 显示 API 文档首页（Swagger UI）
     *
     * @return Response
     */
    public function index(): Response
    {
        $specUrl = $this->getSpecUrl();

        // 使用 Swagger UI CDN（开发环境可接受）
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "{$specUrl}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>
</html>
HTML;

        return Response::html($html);
    }

    /**
     * 返回 OpenAPI Spec (JSON)
     *
     * @return Response
     */
    public function specJson(): Response
    {
        $specFile = $this->getSpecFile();

        if (!file_exists($specFile)) {
            return Response::json([
                'error' => 'OpenAPI spec file not found',
                'message' => '请先运行 php think openapi:generate 生成文档',
            ], 404);
        }

        $json = file_get_contents($specFile);

        if ($json === false) {
            return Response::json([
                'error' => 'Failed to read spec file',
            ], 500);
        }

        // 直接输出 JSON 内容
        return Response::create($json)->contentType('application/json');
    }

    /**
     * 获取 Spec URL
     *
     * @return string
     */
    protected function getSpecUrl(): string
    {
        $path = $this->getSpecPath();

        // 返回绝对路径（以 / 开头）
        // 这样 Swagger UI 会从根路径请求，而不是相对路径
        return $path;
    }

    /**
     * 获取 Spec 文件路径
     *
     * @return string
     */
    protected function getSpecFile(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_OUTPUT_JSON');
            if ($path) {
                return $path;
            }
        }

        return defined('RUNTIME_PATH')
            ? RUNTIME_PATH . 'openapi/openapi.json'
            : __DIR__ . '/../../runtime/openapi/openapi.json';
    }

    /**
     * 获取 Spec 路径
     *
     * @return string
     */
    protected function getSpecPath(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_SPEC_PATH');
            if ($path) {
                return $path;
            }
        }

        return '/openapi.json';
    }
}
