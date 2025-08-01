<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think\Console\Command\Swoole;

use Swoole\Process;
use Think\Console\Input\Argument;
use Think\Console\Input\Option;
use Think\Swoole\Server as ThinkServer;
use Think\Console\Command\Swoole as ThinkSwoole;
use Swoole\Server\Helper;

/**
 * Swoole 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole_server.php文件进行参数配置
 */
class Server extends ThinkSwoole
{
    public function configure()
    {
        $this->setName('swoole:server')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload", 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of swoole server.', null)
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'the port of swoole server.', null)
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->setDescription('Swoole Server for ThinkPHP');
    }

    protected function init()
    {
        $this->config = C('swoole_server');

        if (empty($this->config['pid_file'])) {
            $this->config['pid_file'] = RUNTIME_PATH . '/swoole_server.pid';
        }

        // 避免pid混乱
        $this->config['pid_file'] .= '_' . $this->getPort();
    }

    /**
     * 启动server
     * @access protected
     * @return void
     */
    protected function start()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid)) {
            $this->output->writeln('<error>swoole server process is already running.</error>');
            return false;
        }

        $this->output->writeln('Starting swoole server...');

        if (!empty($this->config['swoole_class'])) {
            $class = $this->config['swoole_class'];

            if (class_exists($class)) {
                $swoole = new $class;
                if (!$swoole instanceof ThinkServer) {
                    $this->output->writeln("<error>Swoole Server Class Must extends \\think\\swoole\\Server</error>");
                    return false;
                }
            } else {
                $this->output->writeln("<error>Swoole Server Class Not Exists : {$class}</error>");
                return false;
            }
        } else {
            $host     = $this->getHost();
            $port     = $this->getPort();
            $type     = !empty($this->config['type']) ? $this->config['type'] : 'socket';
            $mode     = !empty($this->config['mode']) ? $this->config['mode'] : SWOOLE_PROCESS;
            $sockType = !empty($this->config['sock_type']) ? $this->config['sock_type'] : SWOOLE_SOCK_TCP;

            switch ($type) {
                case 'socket':
                    $swooleClass = 'Swoole\Websocket\Server';
                    break;
                case 'http':
                    $swooleClass = 'Swoole\Http\Server';
                    break;
                default:
                    $swooleClass = 'Swoole\Server';
            }

            $swoole = new $swooleClass($host, $port, $mode, $sockType);

            // 开启守护进程模式
            if ($this->input->hasOption('daemon')) {
                $this->config['daemonize'] = true;
            }

            foreach ($this->config as $name => $val) {
                if (0 === strpos($name, 'on')) {
                    $swoole->on(substr($name, 2), $val);
                    unset($this->config[$name]);
                }
            }

            // 设置服务器参数
            $swoole->set($this->checkOptions($this->config));

            $this->output->writeln("Swoole {$type} server started: <{$host}:{$port}>");
            $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

            // 启动服务
            $swoole->start();
        }
    }

    /**
     * 柔性重启server
     * @access protected
     * @return void
     */
    protected function reload()
    {
        // 柔性重启使用管理PID
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln('Reloading swoole server...');
        Process::kill($pid, SIGUSR1);
        $this->output->writeln('> success');
    }

    /**
     * 停止server
     * @access protected
     * @return void
     */
    protected function stop()
    {
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln('Stopping swoole server...');

        Process::kill($pid, SIGTERM);
        $this->removePid();

        $this->output->writeln('> success');
    }

    private function checkOptions(array $options)
    {
        if (class_exists(Helper::class)) {
            $constOptions = Helper::GLOBAL_OPTIONS + Helper::SERVER_OPTIONS + Helper::PORT_OPTIONS + Helper::HELPER_OPTIONS;
            foreach ($options as $k => $v) {
                if (!array_key_exists(strtolower($k), $constOptions)) {
                    unset($options[$k]);
                }
            }
        }
        return $options;
    }

}
