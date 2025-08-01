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
use Think\Console\Command\Queue\QueueService;
use Think\Console\Input;
use Think\Console\Input\Argument;
use Think\Console\Input\Option;
use Think\Console\Output;
use Think\Container;
use Think\Exception;
use Think\Exception\ThrowableError;
use Think\Log;

/**
 * 异步任务管理指令
 * Class Queue
 * @package \Think\Console\Command
 */
class Queue extends Command
{
    /**
     * 任务进程
     */
    const QUEUE_LISTEN = 'queue listen';

    /**
     * 任务编号
     * @var string
     */
    protected $code;

    /**
     * 指令任务配置
     */
    public function configure()
    {
        $this->setName('queue');
        $this->addOption('host', '-H', Option::VALUE_OPTIONAL, L('The host of WebServer.'));
        $this->addOption('port', '-p', Option::VALUE_OPTIONAL, L('The port of WebServer.'));
        $this->addOption('daemon', 'd', Option::VALUE_NONE, L('The queue listen in daemon mode'));
        $this->addArgument('action', Argument::OPTIONAL, 'stop|start|status|query|listen|clean|dorun', 'status');
        $this->addArgument('code', Argument::OPTIONAL, 'Taskcode');
        $this->addArgument('spts', Argument::OPTIONAL, 'Separator');
        $this->setDescription(L('Asynchronous Command Queue Task for ThinkPHP'));
    }

    /**
     * 任务执行入口
     * @param Input $input
     * @param Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $action = $input->hasOption('daemon') ? 'start' : $input->getArgument('action');
        if (method_exists($this, $method = "{$action}Action")) return $this->$method();
        $this->output->error('># ' . L('Wrong operation, Allow stop|start|status|query|listen|clean|dorun|webstop|webstart|webstatus'));
    }

    /**
     * 停止所有任务
     */
    protected function stopAction()
    {
        if (count($result = $this->process->thinkQuery('queue')) < 1) {
            $this->output->warning('># ' . L('There are no task processes to stop'));
        } else foreach ($result as $item) {
            $this->process->close(intval($item['pid']));
            $this->output->info('># ' . L('Successfully sent end signal to process {$pid}', ['pid' => $item['pid']]));
        }
    }

    /**
     * 启动后台任务
     */
    protected function startAction()
    {
        $this->showVeryVerboseInfo(">$ {$this->process->think(static::QUEUE_LISTEN)}");
        if (count($result = $this->process->thinkQuery(static::QUEUE_LISTEN)) > 0) {
            $this->output->warning('># ' . L('Queue daemons already exist for pid {$pid}', ['pid' => $result[0]['pid']]));
        } else {
            $this->process->thinkCreate(static::QUEUE_LISTEN, 1000);
            if (count($result = $this->process->thinkQuery(static::QUEUE_LISTEN)) > 0) {
                $this->output->info('># ' . L('Queue daemons started successfully for pid {$pid}', ['pid' => $result[0]['pid']]));
            } else {
                $this->output->error('># ' . L('Queue daemons failed to start'));
            }
        }
    }

    /**
     * 查询所有任务
     */
    protected function queryAction()
    {
        $items = $this->process->thinkQuery('queue');
        if (count($items) > 0) foreach ($items as $item) {
            $this->output->info('># ' . L('Queue daemons started successfully for pid {$pid}', ['pid' => $item['pid']]));
        } else {
            $this->output->warning('># ' . L('No related task process found'));
        }
    }

    /**
     * 清理所有任务
     */
    protected function cleanAction()
    {
        $days = 7;//$this->input->getArgument('days') ?: 7;
        // 清理 7 天前的历史任务记录
        $this->showVerboseInfo(L('Clean {$days} days ago history task record', ['days' => $days]));
        $map = [
            'exec_time' => ['LT', time() - $days * 24 * 3600]
        ];
        $sqModel = M('SystemQueue');
        $clean = $sqModel->where($map)->delete();
        // 标记超过 1 小时未完成的任务为失败状态，循环任务失败重置
        $this->showVerboseInfo(L('Mark the task that has not been completed for more than 1 hour as a failure status, and reset the failed loop task'));
        $map = [
            [   //执行失败的任务
                'loops_time' => ['GT', 0],
                'status' => QueueService::IS_FAILED
            ],
            [
                // 执行超时的任务
                'exec_time' => ['LT', time() - 3600],
                'status' => QueueService::IS_RUNNING
            ],
            '_logic' => 'OR'
        ];
        $timeout = $loops = 0;
        $total = $sqModel->where($map)->count('id');
        $step = 100; //每次处理的记录条数
        if($total) {
            while(true) {
                $rows = $sqModel->where($map)->limit(0, $step)->select();
                if(!$rows) {
                    break;
                }
                foreach($rows as $item) {
                    if ($item['loops_time'] > 0) {
                        $loops ++;
                        $this->queue->message($total, $timeout + $loops, "正在重置任务 {$item['code']} 为运行");
                        $data = [
                            'status' => QueueService::IS_WAITING,
                            'exec_desc' => intval($item['status']) === QueueService::IS_FAILED ? '任务执行失败，已自动重置任务！' : '任务执行超时，已自动重置任务！'
                        ];
                    } else {
                        $timeout ++;
                        $this->queue->message($total, $timeout + $loops, "正在标记任务 {$item['code']} 为超时");
                        $data = [
                            'status' => QueueService::IS_FAILED,
                            'exec_desc' => '任务执行超时，已自动标识为失败！'
                        ];
                    }
                    $sqModel->where(['id' => $item['id']])->save($data);
                }
            }
        }
        $this->setQueueSuccess(L('Clean {$count} days ago history task record, close {$timeout} timeout task, reset {$loops} loop task', ['count' => $days, 'timeout' => $timeout, 'loops' => $loops]));
    }

    /**
     * 查询兼听状态
     */
    protected function statusAction()
    {
        if (count($result = $this->process->thinkQuery(static::QUEUE_LISTEN)) > 0) {
            $this->output->info(L('Listening for main process {$pid} running', ['pid' => $result[0]['pid']]));
        } else {
            $this->output->warning(L('The Listening main process is not running'));
        }
    }

    /**
     * 立即监听任务
     */
    protected function listenAction()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $this->output->info(L('You can exit with <info>`CTRL-C`</info>'));
        $this->output->info('=============== LISTENING ===============');
        $sqModel = M('SystemQueue');
        $running = [];
        while (true) {
            /**
             * 检查正在执行的任务是否已经结束
             */
            foreach($running as $k => $item) {
                $result = $this->process->thinkQuery($item['args']);
                if(!$result[0]['pid']) {
                    unset($running[$k]);
                    $this->output->info('># ' . L('Task [{$code}] {$title} has ended', ['code' => $item['code'], 'title' => $item['title']]));
                }
            }
            $map = [
                'status' => QueueService::IS_WAITING,
                'exec_time' => ['ELT', time()]
            ];
            $start = microtime(true);
            $rows = $sqModel->where($map)->order('exec_time ASC')->select();
            foreach ($rows as $vo){
                try {
                    $args = "queue dorun {$vo['code']} -";
                    $this->showVeryVerboseInfo(">$ {$this->process->think($args)}");
                    if (count($this->process->thinkQuery($args)) > 0) {
                        $this->output->warning('># ' . L('Already in progress -> [{$code}] {$title}', ['code' => $vo['code'], 'title' => $vo['title']]));
                    } else {
                        $this->process->thinkCreate($args);
                        $this->output->info('># ' . L('Created new process for task -> [{$code}] {$title}', ['code' => $vo['code'], 'title' => $vo['title']]));
                        $running[] = [
                            'args' => $args,
                            'title' => $vo['title'],
                            'code' => $vo['code'],
                        ];
                    }
                } catch (\Exception $exception) {
                    $map = [
                        'code' => $vo['code']
                    ];
                    $data = [
                        'status' => QueueService::IS_FAILED,
                        'outer_time' => time(),
                        'exec_desc' => $exception->getMessage()
                    ];
                    $sqModel->where($map)->save($data);
                    $this->output->error('># ' . L('Execution failed -> [{$code}] {$title}', ['code' => $vo['code'], 'title' => $vo['title']]) . '，' . $exception->getMessage());
                }
            }
            if (microtime(true) < $start + 1) usleep(1000000);
        }
    }

    /**
     * 执行指定的任务内容
     */
    protected function doRunAction()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $this->code = trim($this->input->getArgument('code'));
        if (empty($this->code)) {
            $this->output->error(L('Task number needs to be specified for task execution'));
        } else {
            try {
                $this->queue->initialize($this->code);
                if (empty($this->queue->record) || intval($this->queue->record['status']) !== QueueService::IS_WAITING) {
                    // 这里不做任何处理（该任务可能在其它地方已经在执行）
                    $code = intval($this->queue->record['status']);
                    switch ($code):
                        case QueueService::IS_RUNNED:
                            // 运行结束
                            $this->output->info(L('Task [{$code}] {$title} has ended', ['code' => $this->code, 'title' => $this->queue->record['title']]));
                            break;
                        case QueueService::IS_FAILED:
                            // 运行失败
                            $this->output->error(L('Task [{$code}] {$title} has failed', ['code' => $this->code, 'title' => $this->queue->record['title']]));
                            break;
                        case QueueService::IS_RUNNING:
                            // 运行中
                            $this->output->info(L('Task [{$code}] {$title} is already running', ['code' => $this->code, 'title' => $this->queue->record['title']]));
                            break;
                    endswitch;
                }
                else {
                    $this->output->info(L('Task [{$code}] {$title} is beging', ['code' => $this->code, 'title' => $this->queue->record['title']]));
                    // 锁定任务状态，防止任务再次被执行
                    if (Output::VERBOSITY_DEBUG > $this->output->getVerbosity()) {
                        M('SystemQueue')->strict(false)
                            ->where(['code' => $this->code])
                            ->save(
                                [
                                    'enter_time' => microtime(true),
                                    'attempts' => ['exp', 'attempts+1'],
                                    'outer_time' => 0,
                                    'exec_pid' => getmypid(),
                                    'exec_desc' => '',
                                    'status' => QueueService::IS_RUNNING,
                                ]
                            );
                    }
                    $this->queue->progress(2, '>>> 任务处理开始 <<<', '0');
                    // 执行任务内容
                    defined('WorkQueueCall') or define('WorkQueueCall', true);
                    defined('WorkQueueCode') or define('WorkQueueCode', $this->code);
                    if (class_exists($command = $this->queue->record['command'])) {
                        // 自定义任务，支持返回消息（支持异常结束，异常码可选择 3|4 设置任务状态）
                        $class = Container::getInstance()->make($command, [], true);
                        if ($class instanceof \Think\Console\Command\Queue\Normal) {
                            $this->updateQueue(QueueService::IS_RUNNED, $class->initialize($this->queue)->execute($this->queue->data) ?: '');
                        } elseif ($class instanceof QueueService) {
                            $this->updateQueue(QueueService::IS_RUNNED, $class->initialize($this->queue->code)->execute($this->queue->data) ?: '');
                        } else {
                            throw new Exception("自定义 {$command} 未继承 Queue 或 QueueService");
                        }
                    }
                    else {
                        // 自定义指令，不支持返回消息（支持异常结束，异常码可选择 3|4 设置任务状态）
                        $attr = explode(' ', trim(preg_replace('|\s+|', ' ', $this->queue->record['command'])));
                        $console = $this->getConsole();
                        $this->updateQueue(QueueService::IS_RUNNED, $console->call(array_shift($attr), $attr)->fetch(), false);
                    }
                }
            }
            catch (Exception|\Throwable|\Error $exception) {
                if (!$exception instanceof \Exception) {
                    $exception = new ThrowableError($exception);
                }
                $code = $exception->getCode();
                if (intval($code) !== QueueService::IS_RUNNED) $code = QueueService::IS_FAILED;
                $data = [];
                if(method_exists($exception, 'getdata')) {
                    $data = $exception->getData();
                }
                $msg  = $exception->getMessage();
                if ($data && isset($data['Error SQL'])) {
                    $msg = $data['Error SQL'] . $msg;
                }
                else if ($data) {
                    $msg = Normal . print_r($data, 1);
                }
                $this->updateQueue($code, mb_substr($msg, 0, 65530, 'utf-8'));
                if (Output::VERBOSITY_DEBUG <= $this->output->getVerbosity()) {
                    $this->output->renderException($exception);
                }
                else {
                    $this->showVerboseInfo($msg, 'error');
                }
            }
        }
    }

    /**
     * 修改当前任务状态
     * @param integer $status 任务状态
     * @param string $message 消息内容
     * @param boolean $isSplit 是否分隔
     */
    private function updateQueue(int $status, string $message, bool $isSplit = true)
    {
        // 更新当前任务
        $desc = $isSplit ? explode("\n", trim($message)) : [$message];
        $this->showVerboseInfo(L('change {$code} status is {$status}', ['code' => $this->code, 'status' => $status]));
        if (Output::VERBOSITY_DEBUG > $this->output->getVerbosity()) {
            M('SystemQueue')->strict(false)
                ->where(['code' => $this->code])
                ->save([
                    'status' => $status, 'outer_time' => microtime(true), 'exec_pid' => getmypid(), 'exec_desc' => $message,
                ]);
        }
        $this->output->writeln($message);
        // 任务进度标记
        if (!empty($desc[0])) {
            $this->queue->progress($status, '>>> ' . trim($desc[0]) . ' <<<');
        }
        if ($status == QueueService::IS_RUNNED) {
            $this->queue->progress($status, '>>> 任务处理完成 <<<', '100.00');
        } elseif ($status == QueueService::IS_FAILED) {
            $this->queue->progress($status, '>>> 任务处理失败 <<<');
        }
        // 注册循环任务
        if (isset($this->queue->record['loops_time']) && $this->queue->record['loops_time'] > 0) {
            try {
                $this->queue->initialize($this->code)->reset($this->queue->record['loops_time']);
            } catch (Exception|ThrowableError|Throwable|Error  $exception) {
                Log::write("Queue {$this->queue->record['code']} Loops Failed. {$exception->getMessage()}");
            }
        }
    }
}