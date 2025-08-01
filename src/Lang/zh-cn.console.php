<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: htmambo <htmambo@gmail.com>
// +----------------------------------------------------------------------

return [
    'Usage' => '使用说明',
    'AVAILABLE COMMANDS' => '可用命令',
    '<comment> [default: {$default}]</comment>' => '<comment> [默认值: {$default}]</comment>',
    'Display this help message' => '显示本帮助信息',
    'Display this console version' => '显示框架版本号',
    'Do not output any message' => '静默模式，不输出任何信息',
    'Force ANSI output' => '强制使用 ANSI色彩 方式输出',
    'Disable ANSI output' => '不使用 ANSI色彩 方式输出',
    'Do not ask any interactive question' => '忽略所有互动问题',
    'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug' => '增加消息的详细程度：1 表示正常输出，2 表示更详细的输出，3 表示调试',
    'Displays help for a command' => '显示命令的帮助信息',
    'Lists commands' => '列出所有命令',
    'PHP Built-in Server for ThinkPHP' => 'ThinkPHP 的 PHP 内置服务器',
    'Command {$name} is not defined.' => '命令 {$name} 未定义。',
    'The command "{$name}" does not exist.' => '命令 “{$name}” 不存在。',
    'Did you mean this?' => '或者你想找的是这个？',
    'Did you mean one of these?' => '你是不是想找下面这些里的？',
    'Command "{$name}" is ambiguous ({$sug}).' => '命令“{$name}”不明确（{$sug}）。',
    'The namespace "{$name}" is ambiguous ({$sug}).' => '命名空间“{$name}”不明确（{$sug}）。',
    'Unknown output type given ({$type})' => '未知的输出类型 ({$type})',
    'There are no commands defined in the "{$name}" namespace.' => '命名空间“{$name}”中没有定义命令。',
    'Command class "{$class}" is not correctly initialized. You probably forgot to call the parent constructor.' => '命令类“{$class}”未正确初始化。 您可能忘记调用父构造函数。',
    'Command name "{$name}" is invalid.' => '命令名称“{$name}”无效。',
    '$aliases must be an array or an instance of \Traversable' => '$aliases 必须是数组或 \Traversable 的实例',
    'Invalid callable provided to Command::setCode.' => '提供给 Command::setCode 的可调用对象无效。',
    'You must override the execute() method in the concrete command class.' => '您必须在你的命令类中覆盖 execute() 方法。',
    'The command defined in "{$name}" cannot have an empty name.' => '在“{$name}”中定义的命令不能有空的名字。',
    'Available commands for the "{$name}" namespace:' => '“{$name}”命名空间的可用命令：',
    'The "{$name}" option does not exist.' => '“{$name}”选项不存在。',
    'The "{$name}" option does not accept a value.' => '“{$name}”选项不接受传值。',
    'Argument mode "{$name}" is not valid.' => '参数模式“{$name}”无效。',
    'Incorrectly nested style tag found.' => '发现不正确的嵌套样式标签。',
    'Method ($method) not exists' => '方法 ($method) 不存在',
    'Unable to write output.' => '输出设备无法写入。',

    // RUN
    'The host to server the application on' => '为应用程序提供服务的主机',
    'The port to server the application on' => '为应用程序提供服务的端口',
    'The document root of the application' => '应用程序的文档根目录',
    'ThinkPHP Development server is started On <http://{$host}:{$port}/>' => 'ThinkPHP 开发服务器在 <http://{$host}:{$port}/> 上启动',
    'You can exit with <info>`CTRL-C`</info>' => '您可以使用 <info>`CTRL-C`</info> 退出',
    'Document root is: {$root}' => '文档根目录： {$root}',
    'ThinkPHP Development server listen in daemon mode' => 'ThinkPHP 开发服务器在守护进程模式下监听',
    'There are no WebServer processes to stop' => '没有 WebServer 进程需要停止',
    'Successfully sent end signal to process {$pid}' => '成功向进程 {$pid} 发送结束信号',
    'ThinkPHP Development server is not running' => 'ThinkPHP 开发服务器未运行',
    'Process ID: {$pid}' => '进程 ID: {$pid}',
    //Console/Input.php
    'The "{$name}" option requires a value.' => '“--{$name}”选项需要一个值。',
    'The "{$name}" argument does not exist.' => '“{$name}”参数不存在。',
    'Not enough arguments.' => '没有足够的参数。',
    'Too many arguments.' => '提供的参数太多了。',

    //Console/Output/Formatter.php
    'Undefined style: {$name}' => '未定义的样式：{$name}',

    //Console/Input/Definition.php
    'An argument with name "{$name}" already exists.' => '名称为“{$name}”的参数已经存在。',
    'Cannot add an argument after an array argument.' => '不能在数组参数后添加参数。',
    'Cannot add a required argument after an optional one.' => '不能在可选参数之后添加必需参数。',
    'An option named "{$name}" already exists.' => '一个名为“{$name}”的选项已经存在。',
    'An option with shortcut "{$shortcut}" already exists.' => '一个带有快捷方式“{$shortcut}”的选项已经存在。',

    //Console/Input/Option.php
    'Option mode "{$mode}" is not valid.' => '选项模式“{$mode}”无效。',
    'Cannot set a default value when using InputOption::VALUE_NONE mode.' => '使用 InputOption::VALUE_NONE 模式时无法设置默认值。',
    'A default value for an array option must be an array.' => '数组选项的默认值必须是数组。',
    'An option name cannot be empty.' => '选项名称不能为空。',
    'An option shortcut cannot be empty.' => '选项快捷方式不能为空。',
    'Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.' => '如果选项不接受值，则不可能有选项模式 VALUE_IS_ARRAY。',

    //Console/Input/Argument.php
    'Cannot set a default value except for InputArgument::OPTIONAL mode.' => '不能设置默认值，除了 InputArgument::OPTIONAL 模式。',
    'A default value for an array argument must be an array.' => '数组参数的默认值必须是数组。',

    //Console/Output/Descriptor.php
    'Object of type "{$type}" is not describable.' => '"{$type}"类型的对象未描述。',
    'multiple values allowed' => '支持多个值',

    //Console/Command/Optimize/Autoload.php
    'The following message may be helpful:' => '以下信息可能会有所帮助：',
    'File at "{$path}" does not exist, check your classmap definitions' => '“{$path}” 处的文件不存在，请检查您的类映射定义',
    'File at "{$path}" is not readable, check its permissions' => '“{$path}”处的文件不可读，请检查其权限',
    'File at "{$path}" could not be parsed as PHP, it may be binary or corrupted' => '“{$path}”处的文件无法解析为 PHP，它可能是二进制文件或已损坏',
    'Could not scan for classes inside "{$path}" which does not appear to be a file nor a folder' => '无法扫描“{$path}”中的类，它似乎不是文件也不是文件夹',

    //Console/Output/Formatter/Style.php
    'Invalid foreground color specified: "{$color}". Expected one of ({$list})' => '指定的前景色无效：“{$color}”。只能在这个列表中 ({$list}) 选择一个',
    'Invalid background color specified: "{$color}". Expected one of ({$list})' => '指定的背景色无效：“{$color}”。只能在这个列表中 ({$list}) 选择一个',
    'Invalid option specified: "{$name}". Expected one of ($list)' => '指定的选项无效：“{$name}”。只能在这个列表中 ({$list}) 选择一个',

    //Console/Output/Ask.php
    'Aborted' => '中止',
    'Unable to hide the response.' => '无法隐藏回复。',

    //Console/Output/Question.php
    'A hidden question cannot use the autocompleter.' => '隐藏的问题不能使用自动完成器。',
    'Autocompleter values can be either an array, `null` or an object implementing both `Countable` and `Traversable` interfaces.' => '自动完成器值可以是数组、“null”或同时实现“Countable”和“Traversable”接口的对象。',
    'Maximum number of attempts must be a positive value.' => '最大尝试次数必须是正值。',

    //Migration
    'Show migration status' => '显示当前迁移状态',
    'Migrate the database' => '迁移数据库',
    'The version number to migrate to' => '要迁移的版本号',
    'The date to migrate to' => '要迁移的日期',
    'All Done. Took {$seconds}s.' => '全部完成，耗时 {$seconds} 秒。',
    'migrating' => '正在迁移',
    'migrated, took {$seconds}s.' => '已经迁移，耗时 {$seconds} 秒。',
    'reverting' => '正在回滚',
    'reverted, took {$seconds}s.' => '已经回滚，耗时 {$seconds} 秒。',
    'Create a new migration' => '创建一个空的迁移脚本',
    'What is the name of the migration?' => '数据库迁移脚本名',
    'Creates a new database migration' => '创建一个新的数据库迁移脚本',
    'The migration class name "{$name}" is invalid. Please use CamelCase format.' => '迁移脚本名“{$name}”不合法，请使用驼峰格式命名，如：CamelCase。{\n}名称请尽量与业务相关联，比如要实现的是创建一个名为user的数据表，那么合适的名称应该是：CreateUserTable。',
    'The migration class name "{$name}}" already exists' => '迁移脚本“{$name}”已经存在',
    'The file "{$file}" already exists' => '迁移脚本文件“{$file}”已经存在',
    'The file "{$file}" could not be written to' => '迁移脚本文件“{$file}”不能写入，请检查',
    'directory "{$path}" does not exist' => '目录“{$path}”不存在',
    'directory "{$path}" is not writable' => '目录“{$path}”不能写入，请检查',
    'Migration directory "{$path}" does not exist' => '目录“{$path}”不存在',
    'Migration directory "{$path}" is not writable' => '目录“{$path}”不能写入，请检查',
    'Duplicate migration - "{$file}" has the same version as "{$ver}"' => 'Duplicate migration - "{$file}" has the same version as "{$ver}"',
    'Migration "{$file}" has the same name as "{$class}"' => 'Migration "{$file}" has the same name as "{$class}"',
    'Could not find class "{$class}" in file "{$file}"' => 'Could not find class "{$class}" in file "{$file}"',
    'The class "{$class}" in file "{$file}" must extend \Phinx\Migration\AbstractMigration' => 'The class "{$class}" in file "{$file}" must extend \Phinx\Migration\AbstractMigration',
    '<comment>warning</comment> {$ver} is not a valid version' => '<comment>warning</comment> {$ver} is not a valid version',
    'Manage breakpoints' => '管理迁移断点',


    //Queue
    'WebServer process started successfully for pid {$pid}' => 'WebServer 进程已成功启动，PID：{$pid}',
    'WebServer process failed to start' => 'WebServer 进程启动失败',
    'WebServer process already exist for pid {$pid}' => 'WebServer 进程已存在，PID：{$pid}',
    'WebServer process {$pid} running' => 'WebServer 进程 {$pid} 正在运行',
    'The WebServer process is not running' => 'WebServer 进程未运行',
    'Wrong operation, Allow stop|start|status|query|listen|clean|dorun|webstop|webstart|webstatus' => '操作错误，允许的操作为 stop|start|status|query|listen|clean|dorun|webstop|webstart|webstatus',
    'The host of WebServer.' => 'WebServer 主机',
    'The port of WebServer.' => 'WebServer 端口',

    'The queue listen in daemon mode' => '队列在守护进程模式下监听',


    'Queue daemons started successfully for pid {$pid}' => 'Queue 守护进程已成功启动，PID：{$pid}',
    'Queue daemons already exist for pid {$pid}' => 'Queue 守护进程已存在，PID：{$pid}',
    'Queue daemons failed to start' => 'Queue 守护进程启动失败',
    'There are no task processes to stop' => '没有任务进程需要停止',
    'Asynchronous Command Queue Task for ThinkPHP' => 'ThinkPHP 异步命令队列任务',
    'run command' => '执行命令',
    'change {$code} status is {$status}' => '修改 {$code} 状态为 {$status}',
    'Clean {$days} days ago history task record' => '清理 {$days} 天前的历史任务记录',
    'Clean {$count} days ago history task record, close {$timeout} timeout task, reset {$loops} loop task' => '清理 {$count}天前的历史任务记录，关闭 {$timeout} 条超时任务，重置 {$loops} 条循环任务',
    'Mark the task that has not been completed for more than 1 hour as a failure status, and reset the failed loop task' => '标记超过 1 小时未完成的任务为失败状态，循环任务失败重置',
    'The Listening main process is not running' => '监听主进程未运行',
    'Created new process for task  -> [{$code}] {$title}' => '创建新任务进程 -> [{$code}] {$title}',
    'Already in progress -> [{$code}] {$title}' => '已存在进程 -> [{$code}] {$title}',
    'Execution failed -> [{$code}] {$title}，{$exception->getMessage()}' => '执行失败 -> [{$code}] {$title}，{$exception->getMessage()}',
    'Task number needs to be specified for task execution' => '任务编号需要指定才能执行任务',
    'Listening for main process {$pid} running' => '监听主进程 {$pid} 正在运行',
    'Listening for main process {$pid} not running' => '监听主进程 {$pid} 未运行',
    'Listening for main process {$pid} is not running' => '监听主进程 {$pid} 未运行',
    'No related task process found' => '没有相关任务进程',
    'Task [{$code}] {$title} has ended' => '任务 [{$code}] {$title} 已结束',
    'Task [{$code}] {$title} is already running' => '任务 [{$code}] {$title} 正在运行',
    'Task [{$code}] {$title} is beging' => '任务 [{$code}] {$title} 开始执行',
    'Task [{$code}] {$title} has failed' => '任务 [{$code}] {$title} 运行失败',
    

    //Other


];