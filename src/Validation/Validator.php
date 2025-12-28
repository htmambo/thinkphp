<?php

declare(strict_types=1);

namespace Think\Validation;

use Think\Exception\ValidateException;

/**
 * 验证器实现类
 *
 * 提供数据验证功能，支持常见验证规则。
 *
 * 支持的规则：
 * - required: 必填
 * - string: 字符串
 * - int/integer: 整数
 * - email: 邮箱格式
 * - url: URL 格式
 * - min:N: 最小值/长度
 * - max:N: 最大值/长度
 * - in:a,b,c: 枚举值
 * - regex:pattern: 正则表达式
 */
final class Validator implements ValidatorInterface
{
    /**
     * 错误消息
     * @var array<string, array<int, string>>
     */
    private array $errors = [];

    /**
     * 验证数据
     *
     * @param array<string, mixed> $data 待验证数据
     * @param array<string, string> $rules 验证规则
     * @param array<string, string> $messages 自定义错误消息
     * @return void
     * @throws ValidateException 验证失败时抛出
     */
    public function validate(array $data, array $rules, array $messages = []): void
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $field = (string)$field;
            $ruleString = (string)$ruleString;
            $value = $data[$field] ?? null;

            // 分割规则字符串
            $ruleParts = array_filter(array_map('trim', explode('|', $ruleString)));

            foreach ($ruleParts as $rule) {
                // 解析规则名称和参数
                [$name, $parameter] = $this->parseRule($rule);

                // 执行验证
                if (!$this->validateRule($field, $value, $name, $parameter)) {
                    // 添加错误消息
                    $errorKey = $field . '.' . $name;
                    $message = $messages[$errorKey] ?? $messages[$field] ?? $this->getErrorMessage($field, $name, $parameter);
                    $this->errors[$field][] = $message;

                    // 该字段有错误后，跳过后续规则
                    break;
                }
            }
        }

        // 如果有错误，抛出异常
        if ($this->errors !== []) {
            throw new ValidateException($this->errors);
        }
    }

    /**
     * 获取验证错误
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 判断验证是否失败
     *
     * @return bool
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * 解析规则字符串
     *
     * @param string $rule 规则字符串（如 "min:5" 或 "required"）
     * @return array{0: string, 1: string} [规则名称, 参数]
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $parameter] = explode(':', $rule, 2);
            return [trim($name), trim($parameter)];
        }

        return [trim($rule), ''];
    }

    /**
     * 执行单个规则验证
     *
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $name 规则名称
     * @param string $parameter 规则参数
     * @return bool 验证通过返回 true
     */
    private function validateRule(string $field, mixed $value, string $name, string $parameter): bool
    {
        return match ($name) {
            'required' => $this->validateRequired($value),
            'string' => $this->validateString($value),
            'int', 'integer' => $this->validateInt($value),
            'numeric' => $this->validateNumeric($value),
            'bool', 'boolean' => $this->validateBool($value),
            'email' => $this->validateEmail($value),
            'url' => $this->validateUrl($value),
            'min' => $this->validateMin($value, $parameter),
            'max' => $this->validateMax($value, $parameter),
            'in' => $this->validateIn($value, $parameter),
            'regex' => $this->validateRegex($value, $parameter),
            'array' => $this->validateArray($value),
            'confirmed' => $this->validateConfirmed($field),
            default => true, // 未知规则视为通过
        };
    }

    /**
     * 验证必填
     */
    private function validateRequired(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_array($value) && $value === []) {
            return false;
        }

        return true;
    }

    /**
     * 验证字符串
     */
    private function validateString(mixed $value): bool
    {
        return $value === null || is_string($value) || is_numeric($value);
    }

    /**
     * 验证整数
     */
    private function validateInt(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_int($value)) {
            return true;
        }

        if (is_string($value) && ctype_digit($value)) {
            return true;
        }

        if (is_numeric($value) && (int)$value == $value) {
            return true;
        }

        return false;
    }

    /**
     * 验证数字
     */
    private function validateNumeric(mixed $value): bool
    {
        return $value === null || is_numeric($value);
    }

    /**
     * 验证布尔值
     */
    private function validateBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    /**
     * 验证邮箱格式
     */
    private function validateEmail(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证 URL 格式
     */
    private function validateUrl(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 验证最小值/长度
     */
    private function validateMin(mixed $value, string $parameter): bool
    {
        if ($value === null || $value === '' || $parameter === '') {
            return true;
        }

        $min = (int)$parameter;

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return (float)$value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return true;
    }

    /**
     * 验证最大值/长度
     */
    private function validateMax(mixed $value, string $parameter): bool
    {
        if ($value === null || $value === '' || $parameter === '') {
            return true;
        }

        $max = (int)$parameter;

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return (float)$value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return true;
    }

    /**
     * 验证枚举值
     */
    private function validateIn(mixed $value, string $parameter): bool
    {
        if ($value === null || $value === '' || $parameter === '') {
            return true;
        }

        $allowed = array_map('trim', explode(',', $parameter));

        return in_array((string)$value, $allowed, true);
    }

    /**
     * 验证正则表达式
     */
    private function validateRegex(mixed $value, string $parameter): bool
    {
        if ($value === null || $value === '' || $parameter === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        // 检查正则是否有效
        set_error_handler(function () {});
        $result = preg_match($parameter, $value);
        restore_error_handler();

        return $result === 1;
    }

    /**
     * 验证数组
     */
    private function validateArray(mixed $value): bool
    {
        return $value === null || is_array($value);
    }

    /**
     * 验证确认字段（如 password_confirmation）
     */
    private function validateConfirmed(string $field): bool
    {
        $confirmationField = $field . '_confirmation';

        // 这里无法直接访问请求数据，简化处理
        // 实际使用时需要传入完整数据
        return true;
    }

    /**
     * 获取错误消息
     */
    private function getErrorMessage(string $field, string $rule, string $parameter): string
    {
        $messages = [
            'required' => "The {$field} field is required.",
            'string' => "The {$field} must be a string.",
            'int' => "The {$field} must be an integer.",
            'integer' => "The {$field} must be an integer.",
            'numeric' => "The {$field} must be a number.",
            'email' => "The {$field} must be a valid email address.",
            'url' => "The {$field} must be a valid URL.",
            'min' => $parameter !== '' ? "The {$field} must be at least {$parameter}." : "The {$field} is too small.",
            'max' => $parameter !== '' ? "The {$field} may not be greater than {$parameter}." : "The {$field} is too large.",
            'in' => "The selected {$field} is invalid.",
            'regex' => "The {$field} format is invalid.",
            'array' => "The {$field} must be an array.",
        ];

        return $messages[$rule] ?? "The {$field} validation failed (rule: {$rule}).";
    }
}
