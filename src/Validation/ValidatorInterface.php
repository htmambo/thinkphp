<?php

declare(strict_types=1);

namespace Think\Validation;

/**
 * 验证器接口
 *
 * 定义验证器的标准方法
 */
interface ValidatorInterface
{
    /**
     * 验证数据
     *
     * @param array<string, mixed> $data 待验证数据
     * @param array<string, string> $rules 验证规则 ['field' => 'rule1|rule2']
     * @param array<string, string> $messages 自定义错误消息
     * @return void
     * @throws ValidateException 验证失败时抛出
     */
    public function validate(array $data, array $rules, array $messages = []): void;

    /**
     * 获取验证错误
     *
     * @return array<string, array<int, string>> 错误消息数组 ['field' => ['error1', 'error2']]
     */
    public function errors(): array;

    /**
     * 判断验证是否失败
     *
     * @return bool
     */
    public function fails(): bool;
}
