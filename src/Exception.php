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

use Think\Exception\BaseException;
use Think\Exception\ThinkExceptionInterface;

/**
 * ThinkPHP系统异常基类
 * 继承自 BaseException，提供统一异常接口
 */
class Exception extends BaseException implements ThinkExceptionInterface
{
    /**
     * 保存异常页面显示的额外Debug数据
     * @var array
     */
    protected $data = [];

    /**
     * 函数名
     * @var string
     */
    protected $func = '';

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param int $code 异常代码
     * @param array $context 上下文信息
     * @param int $severity 错误严重程度
     * @param bool $recoverable 是否可恢复
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        array $context = [],
        int $severity = E_ERROR,
        bool $recoverable = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $context, $severity, $recoverable, $previous);
    }

    /**
     * 设置异常额外的Debug数据
     * 数据将会显示为下面的格式
     *
     * Exception Data
     * --------------------------------------------------
     * Label 1
     *   key1      value1
     *   key2      value2
     * Label 2
     *   key1      value1
     *   key2      value2
     *
     * @access protected
     * @param  string $label 数据分类，用于异常页面显示
     * @param  array  $data  需要显示的数据，必须为关联数组
     * @deprecated 推荐使用 withContext() 方法
     */
    final protected function setData($label, array $data)
    {
        $this->data[$label] = $data;
    }

    /**
     * 获取异常额外Debug数据
     * 主要用于输出到异常页面便于调试
     * @access public
     * @return array 由setData设置的Debug数据
     * @deprecated 推荐使用 getContext() 方法
     */
    final public function getData()
    {
        return array_merge($this->data, $this->context);
    }

    /**
     * 设置函数名
     * @param string $func 函数名
     * @return void
     */
    final public function setFunc($func = '')
    {
        $this->func = $func;
    }

    /**
     * 获取函数名
     * @return string
     */
    final public function getFunc()
    {
        return $this->func;
    }

    /**
     * 获取完整上下文（包含 legacy data）
     * @return array
     */
    public function getContext(): array
    {
        $context = parent::getContext();

        // 合并旧的 data 格式
        if (!empty($this->data)) {
            $context = array_merge($context, $this->data);
        }

        // 添加函数名
        if (!empty($this->func)) {
            $context['function'] = $this->func;
        }

        return $context;
    }
}
