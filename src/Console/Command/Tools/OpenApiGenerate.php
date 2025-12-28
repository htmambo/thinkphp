<?php

declare(strict_types=1);

namespace Think\Console\Command\Tools;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Output;
use Think\Console\Input\Option;
use Think\OpenApi\RouterLoader;
use Think\OpenApi\OpenApiGenerator;
use Think\OpenApi\SpecWriter;

/**
 * OpenAPI 生成命令
 *
 * 从路由生成 OpenAPI 规范文件
 *
 * @package Think\Console\Command\Tools
 */
class OpenApiGenerate extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('openapi:generate')
            ->setDescription('从路由生成 OpenAPI 规范文件')
            ->addOption('format', 'f', Option::VALUE_OPTIONAL, '输出格式（json|yaml|both）', 'json')
            ->addOption('output', 'o', Option::VALUE_OPTIONAL, '输出文件路径（覆盖配置）')
            ->addOption('force', null, Option::VALUE_NONE, '覆盖已存在的文件');
    }

    /**
     * 执行命令
     *
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $format = $input->getOption('format');
        $customOutput = $input->getOption('output');
        $force = $input->getOption('force');

        $output->writeln('<info>正在生成 OpenAPI 规范...</info>');

        try {
            // 1. 加载路由
            $output->writeln('  - 加载路由...');
            $loader = new RouterLoader();
            $routes = $loader->getCompiledRoutes();

            if ($routes === []) {
                $output->writeln('<comment>警告：没有找到任何路由</comment>');
                return 0;
            }

            $output->writeln("    已加载 <info>" . count($routes) . "</info> 条路由");

            // 2. 生成 OpenAPI 规范
            $output->writeln('  - 生成 OpenAPI 规范...');
            $generator = new OpenApiGenerator();
            $spec = $generator->generate($routes);

            // 3. 写入文件
            $writer = new SpecWriter();
            $generated = [];

            // JSON 格式
            if ($format === 'json' || $format === 'both') {
                $jsonPath = $customOutput ?: $this->getDefaultJsonOutput();
                $output->writeln("  - 写入 JSON: {$jsonPath}");

                if (!$this->canWriteFile($jsonPath, $force, $output)) {
                    $output->writeln("<comment>跳过: {$jsonPath}</comment>");
                } else {
                    $bytes = $writer->writeJson($spec, $jsonPath);
                    $output->writeln("    ✓ 已写入 ({$bytes} bytes)");
                    $generated[] = $jsonPath;
                }
            }

            // YAML 格式
            if ($format === 'yaml' || $format === 'yml' || $format === 'both') {
                $yamlPath = $customOutput ?: $this->getDefaultYamlOutput();

                try {
                    $output->writeln("  - 写入 YAML: {$yamlPath}");

                    if (!$this->canWriteFile($yamlPath, $force, $output)) {
                        $output->writeln("<comment>跳过: {$yamlPath}</comment>");
                    } else {
                        $bytes = $writer->writeYaml($spec, $yamlPath);
                        $output->writeln("    ✓ 已写入 ({$bytes} bytes)");
                        $generated[] = $yamlPath;
                    }
                } catch (\RuntimeException $e) {
                    $output->writeln("<comment>警告：{$e->getMessage()}</comment>");
                }
            }

            // 4. 输出结果
            if ($generated !== []) {
                $output->writeln('');
                $output->writeln('<info>✓ 成功生成 OpenAPI 规范</info>');
                foreach ($generated as $file) {
                    $output->writeln("  - {$file}");
                }

                // 提示如何访问
                $docsPath = $this->getDocsPath();
                $specPath = $this->getSpecPath();
                $output->writeln('');
                $output->writeln('<comment>访问方式：</comment>');
                $output->writeln("  API 文档: {$docsPath}");
                $output->writeln("  OpenAPI Spec: {$specPath}");

                return 0;
            }

            $output->writeln('<comment>没有生成任何文件</comment>');
            return 0;

        } catch (\Exception $e) {
            $output->writeln("<error>错误：{$e->getMessage()}</error>");
            $output->writeln($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * 检查文件是否可写
     *
     * @param string $path
     * @param bool $force
     * @param Output $output
     * @return bool
     */
    protected function canWriteFile(string $path, bool $force, Output $output): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        if ($force) {
            return true;
        }

        // 非交互模式下默认跳过
        if (!$this->getInput()->isInteractive()) {
            $output->writeln("    <comment>文件已存在，跳过（使用 --force 强制覆盖）</comment>");
            return false;
        }

        $output->write("    文件已存在，是否覆盖? [y/N] ");
        $line = trim(fgets(STDIN) ?: '');

        return strtolower($line) === 'y' || strtolower($line) === 'yes';
    }

    /**
     * 获取默认 JSON 输出路径
     *
     * @return string
     */
    protected function getDefaultJsonOutput(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_OUTPUT_JSON');
            if ($path) {
                return $path;
            }
        }

        return defined('RUNTIME_PATH')
            ? RUNTIME_PATH . 'openapi/openapi.json'
            : __DIR__ . '/../../../../runtime/openapi/openapi.json';
    }

    /**
     * 获取默认 YAML 输出路径
     *
     * @return string
     */
    protected function getDefaultYamlOutput(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_OUTPUT_YAML');
            if ($path) {
                return $path;
            }
        }

        return defined('RUNTIME_PATH')
            ? RUNTIME_PATH . 'openapi/openapi.yaml'
            : __DIR__ . '/../../../../runtime/openapi/openapi.yaml';
    }

    /**
     * 获取 /docs 路径
     *
     * @return string
     */
    protected function getDocsPath(): string
    {
        if (function_exists('C')) {
            $path = C('OPENAPI_DOCS_PATH');
            if ($path) {
                return $path;
            }
        }

        return '/docs';
    }

    /**
     * 获取 /openapi.json 路径
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
