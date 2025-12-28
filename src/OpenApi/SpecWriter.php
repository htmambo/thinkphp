<?php

declare(strict_types=1);

namespace Think\OpenApi;

/**
 * OpenAPI 规范写入器
 *
 * 将 OpenAPI 规范写入文件
 *
 * @package Think\OpenApi
 */
class SpecWriter
{
    /**
     * 写入 JSON 格式
     *
     * @param array $spec OpenAPI 规范
     * @param string $path 输出路径
     * @return int 写入的字节数
     */
    public function writeJson(array $spec, string $path): int
    {
        $json = json_encode(
            $spec,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException('JSON 编码失败: ' . json_last_error_msg());
        }

        return $this->writeFile($path, $json);
    }

    /**
     * 写入 YAML 格式
     *
     * @param array $spec OpenAPI 规范
     * @param string $path 输出路径
     * @return int 写入的字节数
     */
    public function writeYaml(array $spec, string $path): int
    {
        // 检查是否安装了 yaml 扩展
        if (!extension_loaded('yaml')) {
            throw new \RuntimeException('YAML 扩展未安装，无法生成 YAML 文件');
        }

        $yaml = yaml_emit($spec, YAML_UTF8_ENCODING);

        if ($yaml === false) {
            throw new \RuntimeException('YAML 编码失败');
        }

        return $this->writeFile($path, $yaml);
    }

    /**
     * 写入文件
     *
     * @param string $path 文件路径
     * @param string $content 文件内容
     * @return int 写入的字节数
     */
    protected function writeFile(string $path, string $content): int
    {
        $dir = dirname($path);

        // 创建目录（如果不存在）
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("无法创建目录: {$dir}");
            }
        }

        $bytes = file_put_contents($path, $content);

        if ($bytes === false) {
            throw new \RuntimeException("无法写入文件: {$path}");
        }

        return $bytes;
    }

    /**
     * 写入 JSON 或 YAML（自动检测）
     *
     * @param array $spec OpenAPI 规范
     * @param string $path 输出路径
     * @param string $format 格式（json|yaml）
     * @return int 写入的字节数
     */
    public function write(array $spec, string $path, string $format = 'json'): int
    {
        $format = strtolower($format);

        if ($format === 'yaml' || $format === 'yml') {
            return $this->writeYaml($spec, $path);
        }

        return $this->writeJson($spec, $path);
    }
}
