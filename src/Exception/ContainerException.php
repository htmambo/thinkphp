<?php

declare(strict_types=1);

namespace Think\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * 容器异常类
 *
 * 实现 PSR-11 ContainerExceptionInterface
 */
class ContainerException extends \RuntimeException implements ContainerExceptionInterface
{
    /**
     * 创建依赖循环检测异常
     *
     * @param string $concrete 类名
     * @return self
     */
    public static function circularDependency(string $concrete): self
    {
        return new self("Circular dependency detected while resolving: {$concrete}");
    }

    /**
     * 创建依赖解析失败异常
     *
     * @param string $concrete 类名
     * @param \Throwable|null $previous 前一个���常
     * @return self
     */
    public static function resolutionFailed(string $concrete, ?\Throwable $previous = null): self
    {
        return new self("Failed to resolve dependency: {$concrete}", 0, $previous);
    }
}
