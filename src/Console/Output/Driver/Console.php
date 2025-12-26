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

namespace Think\Console\Output\Driver;

use Think\Console\Output;
use Think\Console\Output\Formatter;

class Console
{

    /** @var  Resource */
    private $stdout;

    /** @var  Formatter */
    private $formatter;

    private $terminalDimensions;

    /** @var  int 上次检测终端尺寸的时间戳 */
    private $lastDimensionCheck = 0;

    /** @var  int 终端尺寸缓存有效期（秒），0 表示永久缓存 */
    private $dimensionCacheTTL = 0;

    /** @var  Output */
    private $output;

    public function __construct(Output $output)
    {
        $this->output    = $output;
        $this->formatter = new Formatter();
        $this->stdout    = $this->openOutputStream();
        $decorated       = $this->hasColorSupport($this->stdout);
        $this->formatter->setDecorated($decorated);
    }

    public function setDecorated($decorated)
    {
        $this->formatter->setDecorated($decorated);
    }

    public function write($messages, $newline = false, $type = Output::OUTPUT_NORMAL, $stream = null)
    {
        if (Output::VERBOSITY_QUIET === $this->output->getVerbosity()) {
            return;
        }

        $messages = (array)$messages;

        foreach ($messages as $message) {
            switch ($type) {
                case Output::OUTPUT_NORMAL:
                    $message = $this->formatter->format($message);
                    break;
                case Output::OUTPUT_RAW:
                    break;
                case Output::OUTPUT_PLAIN:
                    $message = strip_tags($this->formatter->format($message));
                    break;
                default:
                    throw new \InvalidArgumentException(L('Unknown output type given ({$type})', ['type' => $type]));
            }

            $this->doWrite($message, $newline, $stream);
        }
    }

    public function writerArrayByStyle($messages, $style = '')
    {
        $stderr   = $this->openErrorStream();
        $maxWidth = 0;
        $tagStart = $tagEnd = '';
        if ($style) {
            $tagStart = '<' . $style . '>';
            $tagEnd   = '</' . $style . '>';
        }
        $width = $this->getTerminalWidth() ? $this->getTerminalWidth() - 1 : PHP_INT_MAX;

        if (defined('HHVM_VERSION') && $width > 1 << 31) {
            $width = 1 << 31;
        }
        $lines = [];
        foreach ($messages as $line) {
            // 4 为前后两个空格
            foreach ($this->splitStringByWidth($line, $width - 4) as $line) {
                $lineWidth = $this->getStringDisplayWidth(preg_replace('/\[[^m]*m/', '', $line)) + 4;
                $lines[]   = [$line, $lineWidth];
                $maxWidth  = max($lineWidth, $maxWidth);
            }
        }

        $messages   = [''];
        $messages[] = $emptyLine = $tagStart . str_repeat(' ', $maxWidth) . $tagEnd;
        foreach ($lines as $line) {
            $messages[] = sprintf($tagStart . '  %s  %s' . $tagEnd, $line[0], str_repeat(' ', $maxWidth - $line[1]));
        }
        $messages[] = $emptyLine;
        $messages[] = '';
        $this->write($messages, true, Output::OUTPUT_NORMAL, $stderr);
    }

    public function renderException(\Exception $e)
    {
        $stderr    = $this->openErrorStream();
        $decorated = $this->hasColorSupport($stderr);
        $this->formatter->setDecorated($decorated);

        do {
            $title = '';
            $title = sprintf('[%s]', get_class($e));
            $lines = [$title];
            foreach (preg_split('/\r?\n/', $e->getMessage()) as $line) {
                $lines[] = strip_tags($line);
            }
            $this->writerArrayByStyle($lines, 'error');

            if (Output::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
                $this->write('<comment>Exception trace:</comment>', true, Output::OUTPUT_NORMAL, $stderr);

                // exception related properties
                $trace = processTrace($e);

                for ($i = 0, $count = count($trace); $i < $count; ++$i) {
                    $class    = isset($trace[$i]['class']) ? $trace[$i]['class'] : '';
                    $type     = isset($trace[$i]['type']) ? $trace[$i]['type'] : '';
                    $function = $trace[$i]['function'];
                    $file     = isset($trace[$i]['file']) ? $trace[$i]['file'] : 'n/a';
                    $line     = isset($trace[$i]['line']) ? $trace[$i]['line'] : 'n/a';

                    $this->write(sprintf(' %s%s%s() at <info>%s:%s</info>', $class, $type, $function, $file, $line), true, Output::OUTPUT_NORMAL, $stderr);
                }

                $this->write('', true, Output::OUTPUT_NORMAL, $stderr);
                $this->write('', true, Output::OUTPUT_NORMAL, $stderr);
            }
        } while ($e = $e->getPrevious());

    }

    /**
     * 获取终端宽度
     * @return int|null
     */
    protected function getTerminalWidth()
    {
        $dimensions = $this->getTerminalDimensions();

        return $dimensions[0];
    }

    /**
     * 获取终端高度
     * @return int|null
     */
    protected function getTerminalHeight()
    {
        $dimensions = $this->getTerminalDimensions();

        return $dimensions[1];
    }

    /**
     * 获取当前终端的尺寸
     * @param bool $forceRefresh 是否强制刷新缓存
     * @return array
     */
    public function getTerminalDimensions($forceRefresh = false)
    {
        // 检查是否需要刷新缓存
        if ($forceRefresh) {
            $this->terminalDimensions = null;
        } elseif ($this->terminalDimensions && !$this->shouldRefreshDimensions()) {
            return $this->terminalDimensions;
        }

        // 重新检测终端尺寸
        if ('\\' === DIRECTORY_SEPARATOR) {
            if (preg_match('/^(\d+)x\d+ \(\d+x(\d+)\)$/', trim(getenv('ANSICON')), $matches)) {
                $this->terminalDimensions = [(int)$matches[1], (int)$matches[2]];
                $this->lastDimensionCheck = time();
                return $this->terminalDimensions;
            }
            if (preg_match('/^(\d+)x(\d+)$/', $this->getMode(), $matches)) {
                $this->terminalDimensions = [(int)$matches[1], (int)$matches[2]];
                $this->lastDimensionCheck = time();
                return $this->terminalDimensions;
            }
        }

        if ($sttyString = $this->getSttyColumns()) {
            if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                $this->terminalDimensions = [(int)$matches[2], (int)$matches[1]];
                $this->lastDimensionCheck = time();
                return $this->terminalDimensions;
            }
            if (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                $this->terminalDimensions = [(int)$matches[2], (int)$matches[1]];
                $this->lastDimensionCheck = time();
                return $this->terminalDimensions;
            }
        }

        $this->terminalDimensions = [null, null];
        $this->lastDimensionCheck = time();
        return $this->terminalDimensions;
    }

    /**
     * 判断是否需要刷新终端尺寸缓存
     * @return bool
     */
    private function shouldRefreshDimensions(): bool
    {
        // 如果 TTL 为 0，永久缓存
        if ($this->dimensionCacheTTL <= 0) {
            return false;
        }

        // 检查是否超过缓存有效期
        $elapsed = time() - $this->lastDimensionCheck;
        return $elapsed >= $this->dimensionCacheTTL;
    }

    /**
     * 设置终端尺寸缓存有效期
     * @param int $seconds 缓存有效期（秒），0 表示永久缓存
     * @return void
     */
    public function setDimensionCacheTTL(int $seconds): void
    {
        $this->dimensionCacheTTL = max(0, $seconds);
    }

    /**
     * 获取终端尺寸缓存有效期
     * @return int
     */
    public function getDimensionCacheTTL(): int
    {
        return $this->dimensionCacheTTL;
    }

    /**
     * 强制刷新终端尺寸
     * @return void
     */
    public function refreshTerminalDimensions(): void
    {
        $this->terminalDimensions = null;
        $this->getTerminalDimensions(true);
    }

    /**
     * 获取stty列数
     * @return string
     */
    private function getSttyColumns()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process        = proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, ['suppress_errors' => true]);
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $info;
        }
        return;
    }

    /**
     * 获取终端模式
     * @return string <width>x<height> 或 null
     */
    private function getMode()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process        = proc_open('mode CON', $descriptorspec, $pipes, null, null, ['suppress_errors' => true]);
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if (preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
                return $matches[2] . 'x' . $matches[1];
            }
        }
        return;
    }

    private function getStringDisplayWidth($string)
    {
        if (!function_exists('mb_strwidth')) {
            return strlen($string);
        }

        if (false === $encoding = mb_detect_encoding($string)) {
            return strlen($string);
        }

        return mb_strwidth($string, $encoding);
    }

    /**
     * 根据宽度分割字符串为多行
     * @param string $string 要分割的字符串
     * @param int $width 最大宽度
     * @param int $pad 填充模式
     * @param string $other 额外内容
     * @param int $lineSpace 行首空格数
     * @return array|false
     */
    public function splitStringByWidth(string $string, int $width, int $pad = STR_PAD_RIGHT, string $other = '', int $lineSpace = 0)
    {
        $width = $this->calculateEffectiveWidth($width, $lineSpace);

        // 如果没有 mb_strwidth 函数，使用简单分割
        if (!function_exists('mb_strwidth')) {
            return str_split($string, $width);
        }

        // 检测编码
        $encoding = mb_detect_encoding($string);
        if (false === $encoding) {
            return str_split($string, $width);
        }

        // 转换为 UTF-8 并分割
        $utf8String = mb_convert_encoding($string, 'utf8', $encoding);
        $lines = $this->splitIntoLines($utf8String, $width, $pad, $lineSpace);

        // 处理额外内容
        if ($other) {
            $lines = $this->handleExtraContent($lines, $other, $width);
        }

        // 转换回原编码
        mb_convert_variables($encoding, 'utf8', $lines);

        return $lines;
    }

    /**
     * 计算有效宽度
     * @param int $width 原始宽度
     * @param int $lineSpace 行首空格数
     * @return int
     */
    private function calculateEffectiveWidth(int $width, int $lineSpace): int
    {
        if ($width < 0) {
            $width = ($this->getTerminalWidth() ? $this->getTerminalWidth() - 1 : PHP_INT_MAX) + 1 + $width;
        }
        if ($width < 10) {
            $width = 10;
        }
        if ($lineSpace) {
            $width = $width - $lineSpace;
        }
        return $width;
    }

    /**
     * 将字符串分割为多行
     * @param string $utf8String UTF-8 编码的字符串
     * @param int $width 最大宽度
     * @param int $pad 填充模式
     * @param int $lineSpace 行首空格数
     * @return array
     */
    private function splitIntoLines(string $utf8String, int $width, int $pad, int $lineSpace): array
    {
        $lines = [];
        $line = '';
        $chars = preg_split('//u', $utf8String);

        foreach ($chars as $char) {
            if ($this->isLineBreak($char)) {
                $lines[] = $this->finalizeLine($line, $width, $pad, $lineSpace, count($lines) === 0);
                $line = '';
                continue;
            }

            if ($this->canAppendToLine($line, $char, $width, $lineSpace, count($lines) === 0)) {
                $line .= $char;
            } else {
                $lines[] = $this->finalizeLine($line, $width, $pad, $lineSpace, false);
                $line = $char;
            }
        }

        // 添加最后一行
        if (strlen($line)) {
            $lines[] = $this->finalizeLine($line, $width, $pad, $lineSpace, count($lines) > 0);
        }

        return $lines;
    }

    /**
     * 判断字符是否为换行符
     * @param string $char 字符
     * @return bool
     */
    private function isLineBreak(string $char): bool
    {
        return in_array($char, ["\r", "\n"], true);
    }

    /**
     * 判断是否可以追加字符到当前行
     * @param string $line 当前行
     * @param string $char 要追加的字符
     * @param int $width 最大宽度
     * @param int $lineSpace 行首空格数
     * @param bool $isFirstLine 是否为第一行
     * @return bool
     */
    private function canAppendToLine(string $line, string $char, int $width, int $lineSpace, bool $isFirstLine): bool
    {
        $effectiveWidth = $width;
        if ($lineSpace && $isFirstLine) {
            $effectiveWidth += $lineSpace;
        }
        return mb_strwidth($line . $char, 'utf8') <= $effectiveWidth;
    }

    /**
     * 完成一行并应用格式化
     * @param string $line 行内容
     * @param int $width 最大宽度
     * @param int $pad 填充模式
     * @param int $lineSpace 行首空格数
     * @param bool $isFirstLine 是否为第一行
     * @return string
     */
    private function finalizeLine(string $line, int $width, int $pad, int $lineSpace, bool $isFirstLine): string
    {
        if ($lineSpace) {
            $line = str_repeat(' ', $lineSpace) . $line;
        }

        if ($pad) {
            $line = str_pad($line, $width, ' ', $pad);
        }

        return $line;
    }

    /**
     * 处理额外内容
     * @param array $lines 当前行数组
     * @param string $other 额外内容
     * @param int $width 最大宽度
     * @return array
     */
    private function handleExtraContent(array $lines, string $other, int $width): array
    {
        if (empty($lines)) {
            return $lines;
        }

        $lastLine = end($lines);
        $combinedWidth = mb_strwidth(strip_tags($lastLine . $other), 'UTF-8');

        if ($combinedWidth > $width) {
            $lines[] = '';
        }

        return $lines;
    }

    private function isRunningOS400()
    {
        $checks = [
            function_exists('php_uname') ? php_uname('s') : '',
            getenv('OSTYPE'),
            PHP_OS,
        ];
        return false !== stripos(implode(';', $checks), 'OS400');
    }

    /**
     * 当前环境是否支持写入控制台输出到stdout.
     *
     * @return bool
     */
    protected function hasStdoutSupport()
    {
        return false === $this->isRunningOS400();
    }

    /**
     * 当前环境是否支持写入控制台输出到stderr.
     *
     * @return bool
     */
    protected function hasStderrSupport()
    {
        return false === $this->isRunningOS400();
    }

    /**
     * @return resource
     */
    private function openOutputStream()
    {
        if (!$this->hasStdoutSupport()) {
            return fopen('php://output', 'w');
        }
        return @fopen('php://stdout', 'w') ?: fopen('php://output', 'w');
    }

    /**
     * @return resource
     */
    private function openErrorStream()
    {
        return fopen($this->hasStderrSupport() ? 'php://stderr' : 'php://output', 'w');
    }

    /**
     * 将消息写入到输出。
     * @param string $message 消息
     * @param bool $newline   是否另起一行
     * @param null $stream
     */
    protected function doWrite($message, $newline, $stream = null)
    {
        if (null === $stream) {
            $stream = $this->stdout;
        }
        if (false === @fwrite($stream, $message . ($newline ? PHP_EOL : ''))) {
            throw new \RuntimeException(L('Unable to write output.'));
        }

        fflush($stream);
    }

    /**
     * 是否支持着色
     * @param $stream
     * @return bool
     */
    protected function hasColorSupport($stream)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return
                '10.0.10586' <= PHP_WINDOWS_VERSION_MAJOR . '.' . PHP_WINDOWS_VERSION_MINOR . '.' . PHP_WINDOWS_VERSION_BUILD
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        return function_exists('posix_isatty') && @posix_isatty($stream);
    }

}
