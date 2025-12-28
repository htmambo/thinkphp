<?php

declare(strict_types=1);

namespace Think\Support;

use Think\Container;

/**
 * 上下文绑定构建器
 *
 * 用于为特定类提供不同的依赖实现。
 *
 * 使用示例：
 * ```php
 * $container->when(PhotoController::class)
 *           ->needs(FilesystemInterface::class)
 *           ->give(S3Filesystem::class);
 * ```
 */
final class ContextualBindingBuilder
{
    /**
     * 容器实例
     * @var Container
     */
    private Container $container;

    /**
     * 具体类名（需要注入依赖的类）
     * @var string
     */
    private string $concrete;

    /**
     * 需要替换的抽象接口/类名
     * @var string|null
     */
    private ?string $needs = null;

    /**
     * 构造函数
     *
     * @param Container $container 容器实例
     * @param string $concrete 具体类名
     */
    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * 指定需要替换的依赖
     *
     * @param string $abstract 抽象接口/类名
     * @return $this
     */
    public function needs(string $abstract): self
    {
        $clone = clone $this;
        $clone->needs = $abstract;
        return $clone;
    }

    /**
     * 提供具体实现
     *
     * @param mixed $implementation 类名、闭包或对象实例
     * @return void
     * @throws \InvalidArgumentException 如果之前未调用 needs()
     */
    public function give(mixed $implementation): void
    {
        if ($this->needs === null) {
            throw new \InvalidArgumentException(
                'Contextual binding requires needs() before give()'
            );
        }

        $this->container->addContextualBinding(
            $this->concrete,
            $this->needs,
            $implementation
        );
    }
}
