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

namespace Think;

use Think\Console\Command;
use Think\Console\Command\Help as HelpCommand;
use Think\Console\Input;
use Think\Console\Input\Argument as InputArgument;
use Think\Console\Input\Definition as InputDefinition;
use Think\Console\Input\Option as InputOption;
use Think\Console\Output;
use Think\Console\Output\Driver\Buffer;
use Think\Exception\CommandNotFoundException;
use Think\Helper\Str;

class Console
{

    private $name = 'ThinkPHP';

    /** @var Command[] */
    private $commands = [];

    private $wantHelps = false;

    /**
     * @var bool 是否强制接管Exception
     */
    private $catchExceptions = true;
    private $autoExit        = true;
    private $definition;
    private $defaultCommand;

    private static $defaultCommands = [
        'help' => "Think\\Console\\Command\\Help",
        'lists' => "Think\\Console\\Command\\Lists",
        'build' => "Think\\Console\\Command\\Build",
        'clear' => "Think\\Console\\Command\\Clear",
        'queue' => "Think\\Console\\Command\\Queue",
        'run' => "Think\\Console\\Command\\RunServer",
        'swoole' => "Think\\Console\\Command\\Swoole",
//        'route:list' => "Think\\Console\\Command\\RouteList",
    ];

    private $auto_load_paths = [
        CORE_PATH . 'Console/Command/Tools',
        CORE_PATH . 'Console/Command/Optimize',
        CORE_PATH . 'Migration/Command/Seed',
        CORE_PATH . 'Migration/Command/Migrate',
        CORE_PATH . 'Console/Command/Make',
        CORE_PATH . 'Console/Command/Swoole',
    ];

    /**
     * Console constructor.
     * @access public
     * @param null|string $user 执行用户
     */
    public function __construct($user = null)
    {

        if ($user) {
            $this->setUser($user);
        }

        $this->defaultCommand = 'list';
        $this->definition     = $this->getDefaultInputDefinition();
    }

    /**
     * 设置执行用户
     * @param $user
     */
    public function setUser($user)
    {
        if (DIRECTORY_SEPARATOR == '\\') {
            return;
        }

        $user = posix_getpwnam($user);
        if ($user) {
            posix_setuid($user['uid']);
            posix_setgid($user['gid']);
        }
    }

    /**
     * 初始化 Console
     * @access public
     * @param bool $run 是否运行 Console
     * @return int|Console
     * @throws \Exception
     */
    public static function init($run = true)
    {
        static $console;

        if (!$console) {
            $config = C('CONSOLE');
            $config = array_change_key_case($config, CASE_LOWER);
            if (isset($config['log_record'])) {
                C('LOG_RECORD', $config['log_record']);
            }
            if (!isset($config['user'])) {
                $config['user'] = null;
            }
            // 加载默认语言包
            $langs = [C('LANG_SET'), C('DEFAULT_LANG')];
            $langs = array_change_key_case($langs, CASE_LOWER);
            $langs = array_unique($langs);
            foreach ($langs as $lang) {
                $langSetFile = CORE_PATH . 'Lang/' . $lang . '.console.php';
                if (file_exists($langSetFile)) {
                    L(include $langSetFile);
                    break;
                }
            }

            $console  = new self($config['user']);
            $commands = $console->getDefinedCommands($config);

            // 添加指令集
            $console->addCommands($commands);
        }

        if ($run) {
            // 运行
            return $console->run();
        }
        else {
            return $console;
        }
    }
    private function registerByPath($path) {
        $commands = [];
        // 自动加载指令类
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if(!$path || !is_dir($path)) return $commands;
        $files = scandir($path);
        if (count($files) > 2) {
            $beforeClass = get_declared_classes();

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                    include $path . $file;
                }
            }

            $afterClass = get_declared_classes();
            $commands   = array_diff($afterClass, $beforeClass);
        }
        return $commands;
    }
    /**
     * @access public
     * @param array $config
     * @return array
     */
    public function getDefinedCommands(array $config = [])
    {
        $commands = self::$defaultCommands;
        $skips = [];
        if(!empty($config['auto_path'])) {
            $this->auto_load_paths[] = $config['auto_path'];
        }
        $otherCmds = [];
        foreach($this->auto_load_paths as $path) {
            if(Str::startsWith($path, CORE_PATH)) {
                $tmp = str_replace([CORE_PATH, '/'], ['Think\\', '\\'], $path);
                $skips[] = $tmp;
                $tmp = explode('\\', $tmp);
                while(count($tmp)>1) {
                    $skips[] = implode('\\', $tmp);
                    array_pop($tmp);
                }
            }
            $tmp = $this->registerByPath($path);
            if($tmp) {
                $otherCmds = array_merge($otherCmds, $tmp);
            }
        }
        array_unique($otherCmds);
        foreach($otherCmds as $cmd) {
            $tmp = strtolower($cmd);
            $tmp = explode('\\', $tmp);
            if(end($tmp) === 'run') {
                $tmp1 = $tmp[count($tmp) -2];
                $otherCmds[$tmp1] = $cmd;
            }
        }
        $commands = array_merge($commands, $otherCmds);
        foreach ($commands as $k => $v) {
            if(in_array($v, $skips) && is_numeric($k)) {
                unset($commands[$k]);
            }
        }
        $file = C('CONF_PATH') . 'command.php';

        if (is_file($file)) {
            $appCommands = include $file;

            if (is_array($appCommands)) {
                $commands = array_merge($commands, $appCommands);
            }
        }
//        $commands = array_unique($commands);
        return $commands;
    }

    /**
     * @access public
     * @param string $command
     * @param array $parameters
     * @param string $driver
     * @return Output|Buffer
     * @throws \Exception
     */
    public static function call($command, array $parameters = [], $driver = 'buffer')
    {
        $console = self::init(false);

        array_unshift($parameters, $command);

        $input  = new Input($parameters);
        $output = new Output($driver);

        $console->setCatchExceptions(false);
        $console->find($command)->run($input, $output);

        return $output;
    }

    /**
     * 执行当前的指令
     * @access public
     * @return int
     * @throws \Exception
     * @api
     */
    public function run()
    {
        $input  = new Input();
        $output = new Output();

        $this->configureIO($input, $output);

        try {
            $exitCode = $this->doRun($input, $output);
        }
        catch (\Exception $e) {
            if (!$this->catchExceptions) {
                throw $e;
            }

            $output->renderException($e);

            $exitCode = $e->getCode();
            if (is_numeric($exitCode)) {
                $exitCode = (int)$exitCode;
                if (0 === $exitCode) {
                    $exitCode = 1;
                }
            }
            else {
                $exitCode = 1;
            }
        }

        if ($this->autoExit) {
            if ($exitCode > 255) {
                $exitCode = 255;
            }

            exit($exitCode);
        }
        return $exitCode;
    }

    /**
     * 执行指令
     * @access public
     * @param Input $input
     * @param Output $output
     * @return int
     */
    public function doRun(Input $input, Output $output)
    {
        if (true === $input->hasParameterOption(['--version', '-V'])) {
            $output->writeln($this->getLongVersion());

            return 0;
        }

        $name = $this->getCommandName($input);

        if (true === $input->hasParameterOption(['--help', '-h'])) {
            if (!$name) {
                $name  = 'help';
                $input = new Input(['help']);
            }
            else {
                $this->wantHelps = true;
            }
        }

        if (!$name) {
            $name  = $this->defaultCommand;
            $input = new Input([$this->defaultCommand]);
        }

        $command = $this->find($name);

        return $this->doRunCommand($command, $input, $output);
    }

    /**
     * 设置输入参数定义
     * @access public
     * @param InputDefinition $definition
     */
    public function setDefinition(InputDefinition $definition)
    {
        $this->definition = $definition;
    }

    /**
     * 获取输入参数定义
     * @access public
     * @return InputDefinition The InputDefinition instance
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * Gets the help message.
     * @access public
     * @return string A help message.
     */
    public function getHelp()
    {
        return $this->getLongVersion();
    }

    /**
     * 是否捕获异常
     * @access public
     * @param bool $boolean
     * @api
     */
    public function setCatchExceptions($boolean)
    {
        $this->catchExceptions = (bool)$boolean;
    }

    /**
     * 是否自动退出
     * @access public
     * @param bool $boolean
     * @api
     */
    public function setAutoExit($boolean)
    {
        $this->autoExit = (bool)$boolean;
    }

    /**
     * 获取名称
     * @access public
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 设置名称
     * @access public
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * 获取完整的版本号
     * @access public
     * @return string
     */
    public function getLongVersion()
    {
        return sprintf(
            '<info>%s</info><comment>%s</comment> %s',
            $this->getName(),
            THINK_VERSION,
            L('Console Tools')
        );
    }

    /**
     * 注册一个指令 （便于动态创建指令）
     * @access public
     * @param string $name 指令名
     * @return Command
     */
    public function register($name)
    {
        return $this->add(new Command($name));
    }

    /**
     * 添加指令集
     * @access public
     * @param array $commands
     */
    public function addCommands(array $commands)
    {
        foreach ($commands as $key => $command) {
            if (is_subclass_of($command, "\\Think\\Console\\Command")) {
                // 注册指令
                $this->add($command, is_numeric($key) ? '' : $key);
            }
        }
    }

    /**
     * 注册一个指令（对象）
     * @access public
     * @param mixed $command 指令对象或者指令类名
     * @param string $name   指令名 留空则自动获取
     * @return mixed
     */
    public function add($command, $name)
    {
        if ($name) {
            $this->commands[$name] = $command;
            return;
        }

        if (is_string($command)) {
            $command = new $command();
        }

        $command->setConsole($this);

        if (!$command->isEnabled()) {
            $command->setConsole(null);
            return;
        }

        if (null === $command->getDefinition()) {
            throw new \LogicException(L('Command class "{$class}" is not correctly initialized. You probably forgot to call the parent constructor.', ['class' => get_class($command)]));
        }

        $this->commands[$command->getName()] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }

        return $command;
    }

    /**
     * 获取指令
     * @access public
     * @param string $name 指令名称
     * @return Command
     * @throws \InvalidArgumentException
     */
    public function get($name)
    {
        if (!isset($this->commands[$name])) {
            throw new CommandNotFoundException(L('The command "{$name}" does not exist.', ['cmd' => $name]));
        }

        $command = $this->commands[$name];

        if (is_string($command)) {
            $command = new $command();
        }

        $command->setConsole($this);

        if ($this->wantHelps) {
            $this->wantHelps = false;

            /** @var HelpCommand $helpCommand */
            $helpCommand = $this->get('help');
            $helpCommand->setCommand($command);

            return $helpCommand;
        }

        return $command;
    }

    /**
     * 某个指令是否存在
     * @access public
     * @param string $name 指令名称
     * @return bool
     */
    public function has($name)
    {
        return isset($this->commands[$name]);
    }

    /**
     * 获取所有的命名空间
     * @access public
     * @return array
     */
    public function getNamespaces()
    {
        $namespaces = [];
        foreach ($this->commands as $name => $command) {
            if (is_string($command)) {
                $namespaces = array_merge($namespaces, $this->extractAllNamespaces($name));
            }
            else {
                $namespaces = array_merge($namespaces, $this->extractAllNamespaces($command->getName()));

                foreach ($command->getAliases() as $alias) {
                    $namespaces = array_merge($namespaces, $this->extractAllNamespaces($alias));
                }
            }

        }

        return array_values(array_unique(array_filter($namespaces)));
    }

    /**
     * 查找注册命名空间中的名称或缩写。
     * @access public
     * @param string $namespace
     * @return string
     * @throws \InvalidArgumentException
     */
    public function findNamespace($namespace)
    {
        $allNamespaces = $this->getNamespaces();
        $expr          = preg_replace_callback('{([^:]+|)}', function ($matches) {
            return preg_quote($matches[1]) . '[^:]*';
        }, $namespace);

        $namespaces = preg_grep('{^' . $expr . '}', $allNamespaces);

        if (empty($namespaces)) {
            $message = L('There are no commands defined in the "{$name}" namespace.', ['name' => $namespace]);

            $this->_cmdNotFound($namespace, $allNamespaces, $message);
        }

        $exact = in_array($namespace, $namespaces, true);
        if (count($namespaces) > 1 && !$exact) {
            throw new \InvalidArgumentException(
                L(
                    'The namespace "{$name}" is ambiguous ({$sug}).',
                    ['name' => $namespace, 'sug' => $this->getAbbreviationSuggestions(array_values($namespaces))]
                )
            );
        }

        return $exact ? $namespace : reset($namespaces);
    }

    /**
     * 查找指令
     * @access public
     * @param string $name 名称或者别名
     * @return Command
     * @throws \InvalidArgumentException
     */
    public function find($name)
    {
        $allCommands = array_keys($this->commands);

        $expr = preg_replace_callback('{([^:]+|)}', function ($matches) {
            return preg_quote($matches[1]) . '[^:]*';
        }, $name);

        $commands = preg_grep('{^' . $expr . '}', $allCommands);

        if (empty($commands) || count(preg_grep('{^' . $expr . '$}', $commands)) < 1) {
            if (false !== $pos = strrpos($name, ':')) {
                $this->findNamespace(substr($name, 0, $pos));
            }

            $message = L('Command {$name} is not defined.', ['name' => $name]);

            $this->_cmdNotFound($name, $allCommands, $message);
        }

        $exact = in_array($name, $commands, true);
        if (count($commands) > 1 && !$exact) {
            $suggestions = $this->getAbbreviationSuggestions(array_values($commands));

            throw new \InvalidArgumentException(L('Command "{$name}" is ambiguous ({$sug}).', ['name' => $name, 'sug' => $suggestions]));
        }

        return $this->get($exact ? $name : reset($commands));
    }

    /**
     * 获取所有的指令
     * @access public
     * @param string $namespace 命名空间
     * @return Command[]
     * @api
     */
    public function all($namespace = null)
    {
        if (null === $namespace) {
            return $this->commands;
        }

        $commands = [];
        foreach ($this->commands as $name => $command) {
            if ($this->extractNamespace($name, substr_count($namespace, ':') + 1) === $namespace) {
                $commands[$name] = $command;
            }
        }

        return $commands;
    }

    /**
     * 获取可能的指令名
     * @access public
     * @param array $names
     * @return array
     */
    public static function getAbbreviations($names)
    {
        $abbrevs = [];
        foreach ($names as $name) {
            for ($len = strlen($name); $len > 0; --$len) {
                $abbrev             = substr($name, 0, $len);
                $abbrevs[$abbrev][] = $name;
            }
        }

        return $abbrevs;
    }

    /**
     * 配置基于用户的参数和选项的输入和输出实例。
     * @access protected
     * @param Input $input   输入实例
     * @param Output $output 输出实例
     */
    protected function configureIO(Input $input, Output $output)
    {
        if (true === $input->hasParameterOption(['--ansi'])) {
            $output->setDecorated(true);
        }
        elseif (true === $input->hasParameterOption(['--no-ansi'])) {
            $output->setDecorated(false);
        }

        if (true === $input->hasParameterOption(['--no-interaction', '-n'])) {
            $input->setInteractive(false);
        }

        if (true === $input->hasParameterOption(['--quiet', '-q'])) {
            $output->setVerbosity(Output::VERBOSITY_QUIET);
        }
        else {
            if ($input->hasParameterOption('-vvv') || $input->hasParameterOption('--verbose=3') || $input->getParameterOption('--verbose') === 3) {
                $output->setVerbosity(Output::VERBOSITY_DEBUG);
            }
            elseif ($input->hasParameterOption('-vv') || $input->hasParameterOption('--verbose=2') || $input->getParameterOption('--verbose') === 2) {
                $output->setVerbosity(Output::VERBOSITY_VERY_VERBOSE);
            }
            elseif ($input->hasParameterOption('-v') || $input->hasParameterOption('--verbose=1') || $input->hasParameterOption('--verbose') || $input->getParameterOption('--verbose')) {
                $output->setVerbosity(Output::VERBOSITY_VERBOSE);
            }
        }
    }

    /**
     * 执行指令
     * @access protected
     * @param Command $command 指令实例
     * @param Input $input     输入实例
     * @param Output $output   输出实例
     * @return int
     * @throws \Exception
     */
    protected function doRunCommand(Command $command, Input $input, Output $output)
    {
        return $command->run($input, $output);
    }

    /**
     * 获取指令的基础名称
     * @access protected
     * @param Input $input
     * @return string
     */
    protected function getCommandName(Input $input)
    {
        return $input->getFirstArgument();
    }

    /**
     * 获取默认输入定义
     * @access protected
     * @return InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED, L('The command to execute')),
            new InputOption('--help', '-h', InputOption::VALUE_NONE, L('Display this help message')),
            new InputOption('--version', '-V', InputOption::VALUE_NONE, L('Display this console version')),
            new InputOption('--quiet', '-q', InputOption::VALUE_NONE, L('Do not output any message')),
            new InputOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, L('Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug')),
            new InputOption('--ansi', '', InputOption::VALUE_NONE, L('Force ANSI output')),
            new InputOption('--no-ansi', '', InputOption::VALUE_NONE, L('Disable ANSI output')),
            new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, L('Do not ask any interactive question')),
        ]);
    }

    public static function addDefaultCommands(array $classnames)
    {
        self::$defaultCommands = array_merge(self::$defaultCommands, $classnames);
    }

    /**
     * 获取可能的建议
     * @access private
     * @param array $abbrevs
     * @return string
     */
    private function getAbbreviationSuggestions($abbrevs)
    {
        return sprintf('%s, %s%s', $abbrevs[0], $abbrevs[1], count($abbrevs) > 2 ? sprintf(' and %d more', count($abbrevs) - 2) : '');
    }

    /**
     * 返回命名空间部分
     * @access public
     * @param string $name  指令
     * @param string $limit 部分的命名空间的最大数量
     * @return string
     */
    public function extractNamespace($name, $limit = null)
    {
        $parts = explode(':', $name);
        array_pop($parts);

        return implode(':', null === $limit ? $parts : array_slice($parts, 0, $limit));
    }

    /**
     * 查找可替代的建议
     * @access private
     * @param string $name
     * @param array|\Traversable $collection
     * @return array
     */
    private function findAlternatives($name, $collection)
    {
        $threshold    = 1e3;
        $alternatives = [];

        $collectionParts = [];
        foreach ($collection as $item) {
            $collectionParts[$item] = explode(':', $item);
        }

        foreach (explode(':', $name) as $i => $subname) {
            foreach ($collectionParts as $collectionName => $parts) {
                $exists = isset($alternatives[$collectionName]);
                if (!isset($parts[$i]) && $exists) {
                    $alternatives[$collectionName] += $threshold;
                    continue;
                }
                elseif (!isset($parts[$i])) {
                    continue;
                }

                $lev = levenshtein($subname, $parts[$i]);
                if ($lev <= strlen($subname) / 3 || '' !== $subname && false !== strpos($parts[$i], $subname)) {
                    $alternatives[$collectionName] = $exists ? $alternatives[$collectionName] + $lev : $lev;
                }
                elseif ($exists) {
                    $alternatives[$collectionName] += $threshold;
                }
            }
        }

        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= strlen($name) / 3 || false !== strpos($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $alternatives = array_filter($alternatives, function ($lev) use ($threshold) {
            return $lev < 2 * $threshold;
        });
        asort($alternatives);

        return array_keys($alternatives);
    }

    /**
     * 设置默认的指令
     * @access public
     * @param string $commandName The Command name
     */
    public function setDefaultCommand($commandName)
    {
        $this->defaultCommand = $commandName;
    }

    /**
     * 返回所有的命名空间
     * @access private
     * @param string $name
     * @return array
     */
    private function extractAllNamespaces($name)
    {
        $parts      = explode(':', $name, -1);
        $namespaces = [];

        foreach ($parts as $part) {
            if (count($namespaces)) {
                $namespaces[] = end($namespaces) . ':' . $part;
            }
            else {
                $namespaces[] = $part;
            }
        }

        return $namespaces;
    }

    public function __debugInfo()
    {
        $data = get_object_vars($this);
        unset($data['commands'], $data['definition']);

        return $data;
    }

    /**
     * @param $name
     * @param array $allCommands
     * @param $message
     * @return mixed
     */
    private function _cmdNotFound($name, array $allCommands, $message)
    {
        if ($alternatives = $this->findAlternatives($name, $allCommands)) {
            $message .= "\n\n";
            if (1 == count($alternatives)) {
                $message .= L('Did you mean this?');
            }
            else {
                $message .= L('Did you mean one of these?');
            }
            $message .= "\n    ";
            $message .= implode("\n    ", $alternatives);
        }
        throw new CommandNotFoundException($message);
    }
}
