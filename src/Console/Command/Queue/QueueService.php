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

use Think\Cache;
use Think\Console\Command\Queue\Exception;

/**
 * 任务基础服务
 * Class QueueService
 * @package think\admin\service
 */
class QueueService extends Service
{
    const IS_WAITING = 1;
    const IS_RUNNING = 2;
    const IS_RUNNED = 3;
    const IS_FAILED = 4;

    /**
     * 当前任务编号
     * @var string
     */
    public $code = '';

    /**
     * 当前任务标题
     * @var string
     */
    public $title = '';

    /**
     * 当前任务参数
     * @var array
     */
    public $data = [];

    /**
     * 当前任务数据
     * @var array
     */
    public $record = [];

    /**
     * 数据初始化
     * @param string $code
     * @return static
     */
    public function initialize(string $code = ''): QueueService
    {
        if (!empty($code)) {
            $this->code = $code;
            $this->record = M('SystemQueue')->where(['code' => $this->code])->find();
            if (empty($this->record)) {
                throw new Exception("Queue initialize failed, Queue {$code} not found.");
            }
            [$this->code, $this->title] = [$this->record['code'], $this->record['title']];
            if($this->record['exec_data']) {
                $this->data = json_decode($this->record['exec_data'], true) ?: [];
            }
        }
        return $this;
    }

    /**
     * 重发异步任务
     * @param integer $wait 等待时间
     * @return $this
     */
    public function reset(int $wait = 0): QueueService
    {
        if (empty($this->record)) {
            throw new Exception("Queue reset failed, Queue {$this->code} data cannot be empty!");
        }
        M('SystemQueue')->where(['code' => $this->code])
            ->strict(false)
            ->save([
            'exec_pid' => 0, 'exec_time' => time() + $wait, 'status' => static::IS_WAITING,
        ]);
        return $this->initialize($this->code);
    }

    /**
     * 添加定时清理任务
     * @param integer $loops 循环时间
     * @return $this
     */
    public function addCleanQueue(int $loops = 3600): QueueService
    {
        return $this->register('定时清理系统任务数据', "queue clean", 0, [], 0, $loops);
    }

    /**
     * 注册异步处理任务
     * @param string $title    任务名称
     * @param string $command  执行脚本
     * @param integer $later   延时时间
     * @param array $data      任务附加数据
     * @param integer $rscript 任务类型(0单例,1多例)
     * @param integer $loops   循环等待时间
     * @return $this
     * @throws \Think\Console\Command\Queue\Exception
     */
    public function register(string $title, string $command, int $later = 0, array $data = [], int $rscript = 0, int $loops = 0): QueueService
    {
        $sqModel = M('SystemQueue');
        $map = [['title', '=', $title], ['status', 'in', [static::IS_WAITING, static::IS_RUNNING]]];
        if (empty($rscript) && ($queue = $sqModel->where($map)->find())) {
            throw new Exception(L('think_library_queue_exist'), 0, $queue['code']);
        }
        $this->code = 'Q' . date('Ymd') . (date('H') + date('i')) . date('s');
        while (strlen($this->code) < 16) $this->code .= rand(0, 9);
        $sqModel->add([
            'code'       => $this->code,
            'title'      => $title,
            'command'    => $command,
            'attempts'   => 0,
            'rscript'    => intval(boolval($rscript)),
            'exec_data'  => json_encode($data, JSON_UNESCAPED_UNICODE),
            'exec_time'  => $later > 0 ? time() + $later : time(),
            'enter_time' => 0,
            'outer_time' => 0,
            'loops_time' => $loops,
            'create_at'  => date('Y-m-d H:i:s')
        ]);
        $this->progress(1, '>>> 任务创建成功 <<<', '0.00');
        return $this->initialize($this->code);
    }

    /**
     * 设置任务进度信息
     * @param ?integer $status 任务状态
     * @param ?string $message 进度消息
     * @param ?string $progress 进度数值
     * @param integer $backline 回退信息行
     * @return array
     */
    public function progress(?int $status = null, ?string $message = null, ?string $progress = null, int $backline = 0): array
    {
        $ckey = "queue_{$this->code}_progress";
        if (is_numeric($status) && intval($status) === static::IS_RUNNED) {
            if (!is_numeric($progress)) $progress = '100.00';
            if (is_null($message)) $message = '>>> 任务已经完成 <<<';
        }
        if (is_numeric($status) && intval($status) === static::IS_FAILED) {
            if (!is_numeric($progress)) $progress = '0.00';
            if (is_null($message)) $message = '>>> 任务执行失败 <<<';
        }
        $this->output->info($message);
        $cacheObj = Cache::getInstance();
        try {
            $data = $cacheObj->getOrSet($ckey, [
                'code' => $this->code, 'status' => $status, 'message' => $message, 'progress' => $progress, 'history' => [],
            ]);
        } catch (\Exception | \Error $exception) {
            return $this->progress($status, $message, $progress, $backline);
        }
        while (--$backline > -1 && count($data['history']) > 0) array_pop($data['history']);
        if (is_numeric($status)) $data['status'] = intval($status);
        if (is_numeric($progress)) $progress = str_pad(sprintf("%.2f", $progress), 6, '0', STR_PAD_LEFT);
        if (is_string($message) && is_null($progress)) {
            $data['message'] = $message;
            $data['history'][] = ['message' => $message, 'progress' => $data['progress'], 'datetime' => date('Y-m-d H:i:s')];
        } elseif (is_null($message) && is_numeric($progress)) {
            $data['progress'] = $progress;
            $data['history'][] = ['message' => $data['message'], 'progress' => $progress, 'datetime' => date('Y-m-d H:i:s')];
        } elseif (is_string($message) && is_numeric($progress)) {
            $data['message'] = $message;
            $data['progress'] = $progress;
            $data['history'][] = ['message' => $message, 'progress' => $progress, 'datetime' => date('Y-m-d H:i:s')];
        }
        if (is_string($message) || is_numeric($progress)) {
            if (count($data['history']) > 10) {
                $data['history'] = array_slice($data['history'], -10);
            }
            $cacheObj->set($ckey, $data, 86400);
        }
        return $data;
    }

    /**
     * 更新任务进度
     * @param integer $total 记录总和
     * @param integer $count 当前记录
     * @param string $message 文字描述
     * @param integer $backline 回退行数
     */
    public function message(int $total, int $count, string $message = '', int $backline = 0): void
    {
        $total = $total < 1 ? 1 : $total;
        $prefix = str_pad("{$count}", strlen("{$total}"), '0', STR_PAD_LEFT);
        $message = "[{$prefix}/{$total}] {$message}";
        if (defined('WorkQueueCode')) {
            $this->progress(2, $message, sprintf("%.2f", $count / $total * 100), $backline);
        } else {
            $this->output->info($message);
        }
    }

    /**
     * 任务执行成功
     * @param string $message 消息内容
     * @throws Exception
     */
    public function success(string $message): void
    {
        throw new Exception($message, 3, $this->code);
    }

    /**
     * 任务执行失败
     * @param string $message 消息内容
     * @throws Exception
     */
    public function error(string $message): void
    {
        throw new Exception($message, static::IS_FAILED, $this->code);
    }

    /**
     * 执行任务处理
     * @param array $data 任务参数
     * @return void
     */
    public function execute(array $data = [])
    {
    }

}