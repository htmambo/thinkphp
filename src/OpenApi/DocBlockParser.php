<?php

declare(strict_types=1);

namespace Think\OpenApi;

use ReflectionClass;
use ReflectionMethod;
use ReflectionException;

/**
 * DocBlock 解析器
 *
 * 从 PHPDoc 注释中提取 OpenAPI 相关信息
 *
 * @package Think\OpenApi
 */
class DocBlockParser
{
    /**
     * 解析控制器的 DocBlock
     *
     * @param string $controllerClass 控制器类名
     * @param string $action 方法名
     * @return array 解析结果 ['summary' => string, 'description' => string, 'tags' => array]
     */
    public function parseControllerAction(string $controllerClass, string $action): array
    {
        try {
            $reflection = new ReflectionClass($controllerClass);

            if (!$reflection->hasMethod($action)) {
                return $this->emptyResult();
            }

            $method = $reflection->getMethod($action);
            $docComment = $method->getDocComment();

            if ($docComment === false) {
                return $this->emptyResult();
            }

            return $this->parseDocComment($docComment);
        } catch (ReflectionException $e) {
            return $this->emptyResult();
        }
    }

    /**
     * 解析 DocBlock 注释
     *
     * @param string $docComment
     * @return array
     */
    protected function parseDocComment(string $docComment): array
    {
        // 移除注释标记
        $comment = preg_replace('/^\s*\/\*\*\s*|\s*\*\/\s*$/m', '', $docComment);
        $comment = preg_replace('/^\s*\*\s?/m', '', $comment);

        // 分割行
        $lines = array_filter(array_map('trim', explode("\n", $comment)));

        $result = [
            'summary' => '',
            'description' => '',
            'tags' => [],
        ];

        $summaryLines = [];
        $inDescription = false;
        $descriptionLines = [];

        foreach ($lines as $line) {
            // 检查是否是标签（如 @tag, @group）
            if (preg_match('/^@(\w+)\s*(.*)$/', $line, $matches)) {
                $inDescription = true;
                $tag = $matches[1];
                $value = $matches[2];

                if ($tag === 'tag' || $tag === 'group') {
                    if ($value) {
                        $result['tags'][] = $value;
                    }
                }
                // 其他标签可以后续扩展（如 @param, @response 等）
                continue;
            }

            // 收集 summary 和 description
            if (!$inDescription) {
                $summaryLines[] = $line;
            } else {
                $descriptionLines[] = $line;
            }
        }

        // 第一行是 summary
        if ($summaryLines !== []) {
            $result['summary'] = array_shift($summaryLines);
            // 剩余的 summary 行也放入 description
            if ($summaryLines !== []) {
                $descriptionLines = array_merge($summaryLines, $descriptionLines);
            }
        }

        $result['description'] = implode("\n", $descriptionLines);

        // 如果没有显式标签，尝试从类名推断
        if ($result['tags'] === []) {
            $result['tags'] = ['default'];
        }

        return $result;
    }

    /**
     * 返回空结果
     *
     * @return array
     */
    protected function emptyResult(): array
    {
        return [
            'summary' => '',
            'description' => '',
            'tags' => ['default'],
        ];
    }

    /**
     * 解析路径参数
     *
     * 从 URI 模板中提取参数（如 {id}, {id?}, {id:\d+}）
     *
     * @param string $uriTemplate URI 模板（如 /users/{id}）
     * @return array 参数列表
     */
    public function parsePathParameters(string $uriTemplate): array
    {
        $parameters = [];

        // 匹配 {param} 或 {param?} 或 {param:pattern} 或 {param?:pattern} 格式
        // 示例: {id}, {id?}, {id:\d+}, {id?:\d+}
        if (preg_match_all('/\{(\w+)(\?)?(?::([^}]+))?\}/', $uriTemplate, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $paramName = $match[1];
                $isOptional = isset($match[2]) && $match[2] === '?';
                $pattern = $match[3] ?? null;

                $param = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => !$isOptional,
                    'schema' => [
                        'type' => 'string',
                    ],
                    'description' => "Path parameter '{$paramName}'",
                ];

                // 如果有正则表达式，添加到 description 或 schema
                if ($pattern) {
                    $param['description'] .= " (pattern: {$pattern})";
                    // 尝试推断类型
                    if (preg_match('/^\d+$/', $pattern)) {
                        $param['schema']['type'] = 'integer';
                    }
                }

                $parameters[] = $param;
            }
        }

        return $parameters;
    }
}
