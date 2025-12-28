<?php

declare(strict_types=1);

namespace Think\Pipeline;

use Closure;

/**
 * 中间件管道
 *
 * 实现 Laravel 风格的洋葱模型中间件。
 *
 * 使用方式：
 * ```php
 * $pipeline = (new Pipeline())
 *     ->send($request)
 *     ->through([Middleware1::class, Middleware2::class])
 *     ->then(function ($request) {
 *         return $response;
 *     });
 * ```
 */
final class Pipeline
{
    /**
     * @var mixed
     */
    private $passable;

    /**
     * @var array<int, callable>
     */
    private array $pipes = [];

    /**
     * 设置要传递的数据
     *
     * @param mixed $passable
     * @return $this
     */
    public function send($passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * 设置中��件数组
     *
     * @param array<int, callable> $pipes 中间件数组
     * @return $this
     */
    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * 执行管道
     *
     * @param Closure $destination 终点闭包 function($passable): mixed
     * @return mixed
     */
    public function then(Closure $destination)
    {
        // 使用 array_reduce 反向构建中间件链（洋葱模型）
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            function (Closure $stack, $pipe): Closure {
                return function ($passable) use ($stack, $pipe) {
                    // 验证中间件可调用
                    if (!is_callable($pipe)) {
                        throw new \InvalidArgumentException('Pipeline pipe must be callable');
                    }

                    // 调用中间件：$pipe($passable, $next)
                    return $pipe($passable, $stack);
                };
            },
            $destination
        );

        return $pipeline($this->passable);
    }

    /**
     * 清空管道状态
     *
     * @return void
     */
    public function flush(): void
    {
        $this->passable = null;
        $this->pipes = [];
    }
}
