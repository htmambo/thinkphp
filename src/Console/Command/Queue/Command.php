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

namespace Think\Console\Command\Queue;

use Think\Console\Command as ThinkCommand;
use Think\Console\Input;
use Think\Console\Output;

/**
 * 自定义指令基类
 * Class Command
 * @package think\admin
 */
abstract class Command extends ThinkCommand
{
    /**
     * 任务控制服务
     * @var QueueService
     */
    protected $queue;

    /**
     * 进程控制服务
     * @var ProcessService
     */
    protected $process;

    /**
     * 初始化指令变量
     * @return static
     */
    protected function initialize(Input $input, Output $output): Command
    {
        $this->queue = QueueService::instance([$output]);
        $this->process = ProcessService::instance([$output]);
        if (defined('WorkQueueCode')) {
            if (!$this->queue instanceof QueueService) {
                $this->queue = QueueService::instance([$output]);
            }
            if ($this->queue->code !== WorkQueueCode) {
                $this->queue->initialize(WorkQueueCode);
            }
        }
        return $this;
    }

    /**
     * 设置失败消息并结束进程
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueError(string $message)
    {
        if (defined('WorkQueueCode')) {
            $this->queue->error($message);
        } else {
            $this->output->writeln($message);
            exit("\r\n");
        }
    }

    /**
     * 设置成功消息并结束进程
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueSuccess(string $message)
    {
        if (defined('WorkQueueCode')) {
            $this->queue->success($message);
        } else {
            $this->output->writeln($message);
            exit("\r\n");
        }
    }

    /**
     * 设置进度消息并继续执行
     * @param null|string $message 进度消息
     * @param null|string $progress 进度数值
     * @param integer $backline 回退行数
     * @return static
     */
    protected function setQueueProgress(?string $message = null, ?string $progress = null, int $backline = 0): Command
    {
        if (defined('WorkQueueCode')) {
            $this->queue->progress(2, $message, $progress, $backline);
        } elseif (is_string($message)) {
            $this->output->writeln($message);
        }
        return $this;
    }

    /**
     * 更新任务进度
     * @param integer $total 记录总和
     * @param integer $count 当前记录
     * @param string $message 文字描述
     * @param integer $backline 回退行数
     * @return static
     */
    public function setQueueMessage(int $total, int $count, string $message = '', int $backline = 0): Command
    {
        $total = max($total, 1);
        $prefix = str_pad("{$count}", strlen("{$total}"), '0', STR_PAD_LEFT);
        return $this->setQueueProgress("[{$prefix}/{$total}] {$message}", sprintf("%.2f", $count / $total * 100), $backline);
    }
}