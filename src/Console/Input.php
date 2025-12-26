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

namespace Think\Console;

use Think\Console\Input\Argument;
use Think\Console\Input\Definition;
use Think\Console\Input\Option;

class Input
{

    /**
     * @var Definition
     */
    protected $definition;

    /**
     * @var Option[]
     */
    protected $options = [];

    /**
     * @var Argument[]
     */
    protected $arguments = [];

    protected $interactive = true;

    private $tokens;
    private $parsed;

    public function __construct($argv = null)
    {
        if (null === $argv) {
            $argv = $_SERVER['argv'];
            // 去除命令名
            array_shift($argv);
        }

        $this->tokens = $argv;

        $this->definition = new Definition();
    }

    protected function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * 绑定实例
     * @param Definition $definition A InputDefinition instance
     */
    public function bind(Definition $definition)
    {
        $this->arguments  = [];
        $this->options    = [];
        $this->definition = $definition;

        $this->parse();
    }

    /**
     * 解析参数
     */
    protected function parse()
    {
        $parseOptions = true;
        $this->parsed = $this->tokens;
        while (null !== $token = array_shift($this->parsed)) {
            if ($parseOptions && '' == $token) {
                $this->parseArgument($token);
            }
            elseif ($parseOptions && '--' == $token) {
                $parseOptions = false;
            }
            elseif ($parseOptions && 0 === strpos($token, '--')) {
                $this->parseLongOption($token);
            }
            elseif ($parseOptions && '-' === $token[0] && '-' !== $token) {
                $this->parseShortOption($token);
            }
            else {
                $this->parseArgument($token);
            }
        }
    }

    /**
     * 解析短选项
     * @param string $token 当前的指令.
     */
    private function parseShortOption($token)
    {
        $name = substr($token, 1);

        if (strlen($name) > 1) {
            if ($this->definition->hasShortcut($name[0])
                && $this->definition->getOptionForShortcut($name[0])->acceptValue()
            ) {
                $this->addShortOption($name[0], substr($name, 1));
            }
            else {
                $this->parseShortOptionSet($name);
            }
        }
        else {
            $this->addShortOption($name, null);
        }
    }

    /**
     * 解析短选项
     * @param string $name 当前指令
     * @throws \RuntimeException
     */
    private function parseShortOptionSet($name)
    {
        $len = strlen($name);
        for ($i = 0; $i < $len; ++$i) {
            if (!$this->definition->hasShortcut($name[$i])) {
                throw new \RuntimeException(L('The "{$name}" option does not exist.', ['name' => '-' . $name[$i]]));
            }

            $option = $this->definition->getOptionForShortcut($name[$i]);
            if ($option->acceptValue()) {
                $this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));

                break;
            }
            else {
                $this->addLongOption($option->getName(), null);
            }
        }
    }

    /**
     * 解析完整选项
     * @param string $token 当前指令
     */
    private function parseLongOption($token)
    {
        $name = substr($token, 2);

        if (false !== $pos = strpos($name, '=')) {
            $this->addLongOption(substr($name, 0, $pos), substr($name, $pos + 1));
        }
        else {
            $this->addLongOption($name, null);
        }
    }

    /**
     * 解析参数
     * @param string $token 当前指令
     * @throws \RuntimeException
     */
    private function parseArgument($token)
    {
        $c = count($this->arguments);

        if ($this->definition->hasArgument($c)) {
            $arg = $this->definition->getArgument($c);

            $this->arguments[$arg->getName()] = $arg->isArray() ? [$token] : $token;

        }
        elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray()) {
            $arg = $this->definition->getArgument($c - 1);

            $this->arguments[$arg->getName()][] = $token;
        }
        else {
            throw new \RuntimeException(L('Too many arguments.'));
        }
    }

    /**
     * 添加一个短选项的值
     * @param string $shortcut 短名称
     * @param mixed $value     值
     * @throws \RuntimeException
     */
    private function addShortOption($shortcut, $value)
    {
        if (!$this->definition->hasShortcut($shortcut)) {
            throw new \RuntimeException(L('The "{$name}" option does not exist.', ['name' => '-' . $shortcut]));
        }

        $this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
    }

    /**
     * 添加一个完整选项的值
     * @param string $name 选项名
     * @param mixed $value 值
     * @throws \RuntimeException
     */
    private function addLongOption($name, $value)
    {
        if (!$this->definition->hasOption($name)) {
            throw new \RuntimeException(L('The "{$name}" option does not exist.', ['name' => '--' . $name]));
        }

        $option = $this->definition->getOption($name);

        if (false === $value) {
            $value = null;
        }

        if (null !== $value && !$option->acceptValue()) {
            throw new \RuntimeException(L('The "{$name}" option does not accept a value.', ['name' => $name, 'value' => $value]));
        }

        if (null === $value && $option->acceptValue() && count($this->parsed)) {
            $next = array_shift($this->parsed);
            if (isset($next[0]) && '-' !== $next[0]) {
                $value = $next;
            }
            elseif (empty($next)) {
                $value = '';
            }
            else {
                array_unshift($this->parsed, $next);
            }
        }

        if (null === $value) {
            if ($option->isValueRequired()) {
                throw new \RuntimeException(L('The "{$name}" option requires a value.', ['name' => '--' . $name]));
            }

            if (!$option->isArray()) {
                $value = $option->isValueOptional() ? $option->getDefault() : true;
            }
        }

        if ($option->isArray()) {
            $this->options[$name][] = $value;
        }
        else {
            $this->options[$name] = $value;
        }
    }

    /**
     * 获取第一个参数
     * @return string|null
     */
    public function getFirstArgument()
    {
        foreach ($this->tokens as $token) {
            if ($token && '-' === $token[0]) {
                continue;
            }

            return $token;
        }
        return;
    }

    /**
     * 检查原始参数是否包含某个值
     * @param string|array $values 需要检查的值
     * @return bool
     */
    public function hasParameterOption($values)
    {
        $values = (array)$values;

        foreach ($this->tokens as $token) {
            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value . '=')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取原始选项的值
     * @param string|array $values 需要检查的值
     * @param mixed $default       默认值
     * @return mixed The option value
     */
    public function getParameterOption($values, $default = false)
    {
        $values = (array)$values;
        $tokens = $this->tokens;

        while (0 < count($tokens)) {
            $token = array_shift($tokens);

            foreach ($values as $value) {
                if ($token === $value || 0 === strpos($token, $value . '=')) {
                    if (false !== $pos = strpos($token, '=')) {
                        return substr($token, $pos + 1);
                    }

                    return array_shift($tokens);
                }
            }
        }

        return $default;
    }

    /**
     * 验证输入
     *
     * 检查是否所有必需的参数都已提供
     *
     * @access public
     * @return void
     * @throws \RuntimeException 当缺少必需参数时抛出
     */
    public function validate(): void
    {
        if (count($this->arguments) < $this->definition->getArgumentRequiredCount()) {
            throw new \RuntimeException(L('Not enough arguments.'));
        }
    }

    /**
     * 检查输入是否是交互的
     * @return bool
     */
    public function isInteractive()
    {
        return $this->interactive;
    }

    /**
     * 设置输入的交互
     * @param bool
     */
    public function setInteractive($interactive)
    {
        $this->interactive = (bool)$interactive;
    }

    /**
     * 获取所有的参数
     *
     * 返回所有参数的值，包括默认值和实际传入的值
     *
     * @access public
     * @return array<string,mixed> 参数数组，键为参数名
     */
    public function getArguments(): array
    {
        return array_merge($this->definition->getArgumentDefaults(), $this->arguments);
    }

    /**
     * 根据名称获取参数值
     *
     * 获取指定参数的值，如果未提供则返回默认值
     *
     * @access public
     * @param string $name 参数名
     * @return mixed 参数值
     * @throws \InvalidArgumentException 当参数不存在时抛出
     */
    public function getArgument(string $name)
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(L('The "{$name}" argument does not exist.', ['name' => $name]));
        }

        return isset($this->arguments[$name]) ? $this->arguments[$name] : $this->definition->getArgument($name)
            ->getDefault();
    }

    /**
     * 设置参数的值
     *
     * 覆盖指定参数的值
     *
     * @access public
     * @param string $name  参数名
     * @param mixed $value 参数值
     * @return void
     * @throws \InvalidArgumentException 当参数不存在时抛出
     */
    public function setArgument(string $name, $value): void
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(L('The "{$name}" argument does not exist.', ['name' => $name]));
        }

        $this->arguments[$name] = $value;
    }

    /**
     * 检查是否存在某个参数
     * @param string|int $name 参数名或位置
     * @return bool
     */
    public function hasArgument($name)
    {
        return $this->definition->hasArgument($name);
    }

    /**
     * 获取所有的选项
     *
     * 返回所有选项的值，包括默认值和实际传入的值
     *
     * @access public
     * @return array<string,mixed> 选项数组，键为选项名
     */
    public function getOptions(): array
    {
        return array_merge($this->definition->getOptionDefaults(), $this->options);
    }

    /**
     * 获取选项值
     *
     * 获取指定选项的值，如果未提供则返回默认值
     *
     * @access public
     * @param string $name 选项名称
     * @return mixed 选项值
     * @throws \InvalidArgumentException 当选项不存在时抛出
     */
    public function getOption(string $name)
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(L('The "{$name}" option does not exist.', ['name' => $name]));
        }

        return isset($this->options[$name]) ? $this->options[$name] : $this->definition->getOption($name)->getDefault();
    }

    /**
     * 设置选项值
     *
     * 覆盖指定选项的值
     *
     * @access public
     * @param string $name 选项名
     * @param mixed $value 选项值
     * @return void
     * @throws \InvalidArgumentException 当选项不存在时抛出
     */
    public function setOption(string $name, $value): void
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(L('The "{$name}" option does not exist.', ['name' => $name]));
        }

        $this->options[$name] = $value;
    }

    /**
     * 是否有某个选项
     * @param string $name 选项名
     * @return bool
     */
    public function hasOption($name)
    {
        return $this->definition->hasOption($name) && isset($this->options[$name]);
    }

    /**
     * 转义指令
     * @param string $token
     * @return string
     */
    public function escapeToken($token)
    {
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }

    /**
     * 返回传递给命令的参数的字符串
     * @return string
     */
    public function __toString()
    {
        $tokens = array_map(function ($token) {
            if (preg_match('{^(-[^=]+=)(.+)}', $token, $match)) {
                return $match[1] . $this->escapeToken($match[2]);
            }

            if ($token && '-' !== $token[0]) {
                return $this->escapeToken($token);
            }

            return $token;
        }, $this->tokens);

        return implode(' ', $tokens);
    }
}
