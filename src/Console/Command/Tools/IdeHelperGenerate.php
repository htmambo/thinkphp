<?php

declare(strict_types=1);

namespace Think\Console\Command\Tools;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Output;
use Think\Console\Input\Option;
use Think\IdeHelper\PhpStormMetaGenerator;
use Think\IdeHelper\StubGenerator;

/**
 * IDE Helper 生成命令
 *
 * 生成 PhpStorm IDE 提示文件
 *
 * @package Think\Console\Command\Tools
 */
class IdeHelperGenerate extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('ide-helper:generate')
            ->setDescription('生成 IDE Helper 文件（.phpstorm.meta.php 和 _ide_helper.php）')
            ->addOption('force', 'f', Option::VALUE_NONE, '覆盖已存在的文件')
            ->addOption('only', 'o', Option::VALUE_OPTIONAL, '仅生成指定类型（meta|stub|all）', 'all')
            ->addOption('meta-output', null, Option::VALUE_OPTIONAL, '.phpstorm.meta.php 输出路径')
            ->addOption('stub-output', null, Option::VALUE_OPTIONAL, '_ide_helper.php 输出路径');
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
        $only = $input->getOption('only');
        $force = $input->getOption('force');

        // 获取输出路径
        $metaOutput = $input->getOption('meta-output') ?: $this->getDefaultMetaOutput();
        $stubOutput = $input->getOption('stub-output') ?: $this->getDefaultStubOutput();

        $generated = [];

        // 生成 .phpstorm.meta.php
        if ($only === 'all' || $only === 'meta') {
            $output->writeln('<info>正在生成 .phpstorm.meta.php...</info>');

            $generator = new PhpStormMetaGenerator();
            $generator->loadFromConfig();

            $content = $generator->generate();

            // 检查文件是否已存在
            if (!$force && file_exists($metaOutput)) {
                $output->writeln("<comment>文件已存在: {$metaOutput}</comment>");
                $output->writeln('使用 --force 选项覆盖');
                $overwrite = $this->askConfirmation($input, $output, '是否覆盖? [y/N] ');
                if (!$overwrite) {
                    $output->writeln('<comment>跳过 .phpstorm.meta.php</comment>');
                } else {
                    $this->writeFile($metaOutput, $content, $output);
                    $generated[] = $metaOutput;
                }
            } else {
                $this->writeFile($metaOutput, $content, $output);
                $generated[] = $metaOutput;
            }
        }

        // 生成 _ide_helper.php
        if ($only === 'all' || $only === 'stub') {
            $output->writeln('<info>正在生成 _ide_helper.php...</info>');

            $generator = new StubGenerator();
            $generator->loadFromConfig();

            $content = $generator->generate();

            // 检查文件是否已存在
            if (!$force && file_exists($stubOutput)) {
                $output->writeln("<comment>文件已存在: {$stubOutput}</comment>");
                $output->writeln('使用 --force 选项覆盖');
                $overwrite = $this->askConfirmation($input, $output, '是否覆盖? [y/N] ');
                if (!$overwrite) {
                    $output->writeln('<comment>跳过 _ide_helper.php</comment>');
                } else {
                    $this->writeFile($stubOutput, $content, $output);
                    $generated[] = $stubOutput;
                }
            } else {
                $this->writeFile($stubOutput, $content, $output);
                $generated[] = $stubOutput;
            }
        }

        // 输出结果
        if ($generated !== []) {
            $output->writeln('<info>✓ 成功生成以下文件:</info>');
            foreach ($generated as $file) {
                $output->writeln("  - {$file}");
            }

            $output->writeln('');
            $output->writeln('<comment>提示:</comment>');
            $output->writeln('1. 如果这是首次生成，请重启 PhpStorm 以索引新文件');
            $output->writeln('2. PhpStorm 会自动识别 .phpstorm.meta.php 并提供类型提示');
            $output->writeln('3. _ide_helper.php 会为全局函数提供智能提示');

            return 0;
        }

        $output->writeln('<comment>没有生成任何文件</comment>');
        return 0;
    }

    /**
     * 写入文件
     *
     * @param string $path 文件路径
     * @param string $content 文件内容
     * @param Output $output 输出对象
     * @return void
     */
    protected function writeFile(string $path, string $content, Output $output): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bytes = file_put_contents($path, $content);

        if ($bytes === false) {
            $output->writeln("<error>写入文件失败: {$path}</error>");
            return;
        }

        $output->writeln("<info>✓ 已写入: {$path} ({$bytes} bytes)</info>");
    }

    /**
     * 获取 .phpstorm.meta.php 默认输出路径
     *
     * @return string
     */
    protected function getDefaultMetaOutput(): string
    {
        if (function_exists('C')) {
            $path = C('IDE_HELPER_META_OUTPUT');
            if ($path) {
                return $path;
            }
        }

        return defined('ROOT_PATH') ? ROOT_PATH . '.phpstorm.meta.php' : '.phpstorm.meta.php';
    }

    /**
     * 获取 _ide_helper.php 默认输出路径
     *
     * @return string
     */
    protected function getDefaultStubOutput(): string
    {
        if (function_exists('C')) {
            $path = C('IDE_HELPER_OUTPUT');
            if ($path) {
                return $path;
            }
        }

        return defined('ROOT_PATH') ? ROOT_PATH . '_ide_helper.php' : '_ide_helper.php';
    }

    /**
     * 询问用户确认
     *
     * @param Input $input
     * @param Output $output
     * @param string $question 问题
     * @return bool
     */
    protected function askConfirmation(Input $input, Output $output, string $question): bool
    {
        // 在非交互模式下默认返回 false
        if (!$input->isInteractive()) {
            return false;
        }

        $output->write($question);
        $line = trim(fgets(STDIN) ?: '');

        return strtolower($line) === 'y' || strtolower($line) === 'yes';
    }
}
