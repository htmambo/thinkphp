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

declare (strict_types=1);

namespace Think\Console\Command\Queue;

use Think\Console\Output;

/**
 * 系统进程管理服务
 * Class ProcessService
 * @package think\admin\service
 */
class ProcessService extends Service
{
    public $log = [];
    /**
     * 获取 Think 指令内容
     * @param string $args 指定参数
     * @param boolean $simple 指令内容
     * @return string
     */
    public function think(string $args = '', bool $simple = false): string
    {
        $command = realpath(ROOT_PATH . '/think');
        $command = trim($command . ' ' . $args);
        if ($simple) return $command;
        $binary = C('CONSOLE.PHP_BINARY') ?: PHP_BINARY;
        if (!in_array(basename($binary), ['php', 'php.exe'])) {
            $binary = 'php';
        }
        return $binary . ' ' . $command;
    }

    /**
     * 将参数字符串按空白拆分为 token（最小实现：覆盖本项目现有 queue 用法）
     * 注意：此方法不尝试解析复杂 shell 引号语法；安全性依赖后续 escapeshellarg()。
     *
     * @param string $args
     * @return array<int, string>
     */
    private function splitArgs(string $args): array
    {
        $args = trim($args);
        if ($args === '') {
            return [];
        }

        // 防御性：拒绝 NUL 字节，避免底层 C 处理截断等异常行为
        if (strpos($args, "\0") !== false) {
            throw new \InvalidArgumentException('Invalid CLI args: contains NUL byte.');
        }

        $tokens = preg_split('/\s+/', $args);
        if ($tokens === false) {
            return [];
        }

        // 过滤空 token
        $tokens = array_values(array_filter($tokens, static fn($v) => $v !== ''));

        return $tokens;
    }

    /**
     * 将 argv 安全拼成可执行命令字符串（所有 token 逐个 escapeshellarg）
     *
     * @param array<int, string> $argv
     * @return string
     */
    private function shellJoin(array $argv): string
    {
        $parts = [];
        foreach ($argv as $arg) {
            $parts[] = escapeshellarg((string)$arg);
        }
        return implode(' ', $parts);
    }

    /**
     * 构造"可安全执行"的 think 命令（用于 exec/create）
     *
     * @param string $args
     * @return string
     */
    private function thinkForExec(string $args = ''): string
    {
        // realpath 失败时回退到固定路径（保持可用性）
        $think = realpath(ROOT_PATH . '/think');
        if ($think === false) {
            $think = ROOT_PATH . '/think';
        }

        $binary = C('CONSOLE.PHP_BINARY') ?: PHP_BINARY;
        if (!in_array(basename($binary), ['php', 'php.exe'], true)) {
            $binary = 'php';
        }

        $argv = array_merge([$binary, $think], $this->splitArgs($args));
        return $this->shellJoin($argv);
    }

    /**
     * 检查 Think 运行进程
     * @param string $args
     * @return array
     */
    public function thinkQuery(string $args): array
    {
        return $this->query($this->think($args, true));
    }

    /**
     * 执行 Think 指令内容（改为使用安全构造，防止 args 命令注入）
     * @param string $args 执行参数
     * @param integer $usleep 延时时间
     * @return ProcessService
     */
    public function thinkCreate(string $args, int $usleep = 0): ProcessService
    {
        return $this->create($this->thinkForExec($args), $usleep);
    }

    /**
     * 创建异步进程
     * @param string $command 任务指令
     * @param integer $usleep 延时时间
     * @return ProcessService
     */
    public function create(string $command, int $usleep = 0): ProcessService
    {
        if ($this->iswin()) {
            $this->exec(__DIR__ . "/../../bin/console.exe {$command}");
        } else {
            $this->exec("{$command} > /dev/null 2>&1 &");
        }
        if ($usleep > 0) {
            usleep($usleep);
        }
        return $this;
    }

    /**
     * 查询相关进程列表
     * @param string $cmd 任务指令（用于匹配子串）
     * @param string $name 进程名称
     * @return array
     */
    public function query(string $cmd, string $name = 'php.exe'): array
    {
        $list = [];
        if ($this->iswin()) {
            $safeName = escapeshellarg($name);
            $lines = $this->exec('wmic process where name=' . $safeName . ' get processid,CommandLine', true);
            foreach ($lines as $line) if ($this->_issub($line, $cmd) !== false) {
                $attr = explode(' ', $this->_space($line));
                $list[] = ['pid' => array_pop($attr), 'cmd' => join(' ', $attr)];
            }
        } else {
            // 关键修复：去掉 ps|grep 管道，避免 shell 组合注入面
            $lines = $this->exec('ps ax', true);
            foreach ($lines as $line) if ($this->_issub($line, $cmd) !== false) {
                $attr = explode(' ', $this->_space($line));
                [$pid] = [array_shift($attr), array_shift($attr), array_shift($attr), array_shift($attr)];
                $list[] = ['pid' => $pid, 'cmd' => join(' ', $attr)];
            }
        }
        return $list;
    }

    /**
     * 关闭任务进程
     * @param integer $pid 进程号
     * @return boolean
     */
    public function close(int $pid): bool
    {
        if ($this->iswin()) {
            $this->exec("wmic process {$pid} call terminate");
        } else {
            $this->exec("kill -9 {$pid}");
        }
        return true;
    }

    /**
     * 立即执行指令
     * @param string $command 执行指令
     * @param boolean|array $outarr 返回类型
     * @return string|array
     */
    public function exec(string $command, $outarr = false)
    {
        if (Output::VERBOSITY_VERY_VERBOSE <= $this->output->getVerbosity()) {
            $this->output->info(L('run command') . ':' . $command);
        }
        exec($command, $output);
        $this->log = $output;
        return $outarr ? $output : join("\n", $output);
    }

    /**
     * 判断系统类型是否是 Windows
     * @return boolean
     */
    public function iswin(): bool
    {
        return PATH_SEPARATOR === ';';
    }

    public function islinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    public function ismac(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    /**
     * 读取组件版本号
     * @return string
     */
    public function version(): string
    {
        return THINK_VERSION;
    }

    /**
     * 清除空白字符过滤
     * @param string $content
     * @return string
     */
    private function _space(string $content): string
    {
        return preg_replace('|\s+|', ' ', strtr(trim($content), '\\', '/'));
    }

    /**
     * 判断是否包含字符串
     * @param string $content
     * @param string $substr
     * @return boolean
     */
    private function _issub(string $content, string $substr): bool
    {
        return stripos($this->_space($content), $this->_space($substr)) !== false;
    }
}