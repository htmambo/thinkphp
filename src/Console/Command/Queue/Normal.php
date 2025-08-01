<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2021 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: https://gitee.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkLibrary
// | github 代码仓库：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace Think\Console\Command\Queue;

use Think\Console\Command\Queue\ProcessService;
use Think\Console\Command\Queue\QueueService;

/**
 * 任务基础类
 * Class Queue
 * @package think\admin
 */
abstract class Normal
{

    /**
     * 任务控制服务
     * @var Think\Console\Command\Queue\QueueService
     */
    protected $queue;

    /**
     * 进程控制服务
     * @var Think\Console\Command\Queue\ProcessService
     */
    protected $process;

    /**
     * Queue constructor.
     * @param ProcessService $process
     */
    public function __construct(ProcessService $process)
    {
        $this->process = $process;
    }

    /**
     * 初始化任务数据
     * @param QueueService $queue
     * @return $this
     */
    public function initialize(QueueService $queue): Normal
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * 执行任务处理内容
     * @param array $data
     */
    abstract public function execute(array $data = []);

    /**
     * 设置失败的消息
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueError(string $message)
    {
        $this->queue->error($message);
    }

    /**
     * 设置成功的消息
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueSuccess(string $message)
    {
        $this->queue->success($message);
    }

    /**
     * 更新任务进度
     * @param integer $total 记录总和
     * @param integer $count 当前记录
     * @param string $message 文字描述
     * @param integer $backline 回退行数
     * @return static
     */
    protected function setQueueMessage(int $total, int $count, string $message = '', int $backline = 0): Normal
    {
        $this->queue->message($total, $count, $message, $backline);
        return $this;
    }

    /**
     * 设置任务的进度
     * @param null|string $message 进度消息
     * @param null|string $progress 进度数值
     * @param integer $backline 回退行数
     * @return Normal
     */
    protected function setQueueProgress(?string $message = null, ?string $progress = null, int $backline = 0): Normal
    {
        $this->queue->progress(2, $message, $progress, $backline);
        return $this;
    }
}