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

namespace Think\Console\Command;

use Think\Console\Command\Queue\Command;
use Think\Console\Input;
use Think\Console\Output;
use Think\Console\Input\Option;
use Think\Console\Input\Argument;
class RunServer extends Command
{
    protected $command = '';

    public function configure()
    {
        $this->setName('run')
            ->addOption('php', NULL, Option::VALUE_OPTIONAL, L('PHP bin File'), 'php')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, L('The host to server the application on'), '0.0.0.0')
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, L('The port to server the application on'), 8000)
            ->addOption('root', 'r', Option::VALUE_OPTIONAL, L('The document root of the application'), ROOT_PATH)
            ->addOption('daemon', 'd', Option::VALUE_NONE, L('ThinkPHP Development server listen in daemon mode'))
            ->addArgument('action', Argument::OPTIONAL, 'stop|start|status|listen', 'start')
            ->setDescription(L('PHP Built-in Server for ThinkPHP'));
    }

    public function execute(Input $input, Output $output)
    {
        if (!function_exists('exec')) {
            $output->error('exec is disabled');
            exit;
        }
        if (!file_exists(ROOT_PATH . 'router.php')) {
            $output->info('请在你的网站根目录（与index.php一起，比如:' . ROOT_PATH . '）创建一个router.php，并填写下方所示内容：');
            $output->error([
                '<?php',
                'if (is_file($_SERVER["DOCUMENT_ROOT"] . $_SERVER["SCRIPT_NAME"])) {',
                '    return false;',
                '} else {',
                '    //这里引入你的网站入口文件，注意文件路径！',
                '    require __DIR__ . "/index.php";',
                '}',
                '?>'
            ]);
            $output->comment('然后在该目录中执行本命令即可');
            exit;
        }
        $php  = $input->getOption('php');
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $root = $input->getOption('root');

        $this->command = sprintf(
            '%s -S %s:%d -t %s %s',
            $php,
            $host,
            $port,
            strpos(' ', $root) !== false ? escapeshellarg($root) : $root,
            (strpos(' ', $root) !== false ? escapeshellarg($root) : $root) . DIRECTORY_SEPARATOR . 'router.php'
        );
        $action = $input->hasOption('daemon') ? 'listen' : $input->getArgument('action');
        $result = $this->process->query($this->command);
        if($action === 'stop') {
            if(count($result) > 0) {
                $this->process->close(intval($result[0]['pid']));
                $output->writeln(L('Successfully sent end signal to process {$pid}', ['pid' => $result[0]['pid']]));
            } else {
                $output->writeln(L('ThinkPHP Development server is not running'));
            }
            return;
        }
        if($action === 'status') {
            if(count($result)>0) {
                $output->writeln(L('ThinkPHP Development server is started On <http://{$host}:{$port}/>', ['host' => $host, 'port' => $port]));
                $output->writeln(L('Document root is: {$root}', ['root' => $root]));
                $output->writeln(L('Process ID: {$pid}', ['pid' => $result[0]['pid']]));
            } else {
                $output->warning(L('ThinkPHP Development server is not running'));
            }
            return;
        }
        $output->writeln(L('ThinkPHP Development server is started On <http://{$host}:{$port}/>', ['host' => $host, 'port' => $port]));
        $output->writeln(L('Document root is: {$root}', ['root' => $root]));

        if(count($result) > 0) {
            if($this->process->iswin()) $this->process->exec("start http://{$host}:{$port}");
            elseif($this->process->islinux()) $this->process->exec("xdg-open http://{$host}:{$port}");
            elseif($this->process->ismac()) $this->process->exec("open http://{$host}:{$port}");
            else $this->process->exec("start http://{$host}:{$port}");
            $output->writeln(L('Process ID: {$pid}', ['pid' => $result[0]['pid']]));
        } else {
            $this->showVerboseInfo($this->command);
            if($action === 'listen') {
                $this->process->create($this->command, 2000);
                $result = $this->process->query($this->command);
                $output->writeln(L('Process ID: {$pid}', ['pid' => $result[0]['pid']]));
            } else {
                $output->writeln(L('You can exit with <info>`CTRL-C`</info>'));
                $result = $this->process->exec($this->command, true);
            }
        }
    }

}
