<?php
namespace Think\Blade\Compilers\Concerns;

trait CompilesLoops
{
    /**
     * Counter to keep track of nested forelse statements.
     *
     * @var int
     */
    protected $forElseCounter = 0;

    /**
     * Compile the for-else statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForelse($expression)
    {
        $empty = '$__empty_' . ++$this->forElseCounter;

        // 安全修复：优化正则表达式，防止 ReDoS 攻击
        // 使用非贪婪匹配 + 字符类限制，避免灾难性回溯
        if (!preg_match('/^\(([^)]+?)\s+as\s+([^)]+?)\)$/is', $expression, $matches)) {
            // 如果正则匹配失败，提供更安全的默认处理
            error_log("Security Warning: Invalid forelse expression: {$expression}");
            return "<?php foreach(array() as \$__empty): ?>";
        }

        $iteratee = trim($matches[1]);
        $iteration = trim($matches[2]);

        // 额外的安全检查：限制表达式长度
        if (strlen($iteratee) > 1000 || strlen($iteration) > 1000) {
            error_log("Security Warning: Forelse expression too long, possible ReDoS attack");
            return "<?php foreach(array() as \$__empty): ?>";
        }

        $initLoop = "\$__currentLoopData = {$iteratee}; \$__env->addLoop(\$__currentLoopData);";

        $iterateLoop = '$__env->incrementLoopIndices(); $loop = $__env->getLastLoop();';

        return "<?php {$empty} = true; {$initLoop} foreach(\$__currentLoopData as {$iteration}): {$iterateLoop} {$empty} = false; ?>";
    }

    /**
     * Compile the for-else-empty and empty statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEmpty($expression)
    {
        if ($expression) {
            return "<?php if(empty{$expression}): ?>";
        }

        $empty = '$__empty_' . $this->forElseCounter--;

        return "<?php endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if ({$empty}): ?>";
    }

    /**
     * Compile the end-for-else statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndforelse()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-empty statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndEmpty()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the for statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileFor($expression)
    {
        return "<?php for{$expression}: ?>";
    }

    /**
     * Compile the for-each statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForeach($expression)
    {
        // 安全修复：优化正则表达式，防止 ReDoS 攻击
        if (!preg_match('/^\(([^)]+?)\s+as\s+([^)]+?)\)$/is', $expression, $matches)) {
            error_log("Security Warning: Invalid foreach expression: {$expression}");
            return '';
        }

        $iteratee = trim($matches[1]);

        // 额外的安全检查：限制表达式长度
        if (strlen($iteratee) > 1000 || strlen($matches[2]) > 1000) {
            error_log("Security Warning: Foreach expression too long, possible ReDoS attack");
            return '';
        }

        $iteration = trim($matches[2]);

        $initLoop = "\$__currentLoopData = {$iteratee}; \$__env->addLoop(\$__currentLoopData);";

        $iterateLoop = '$__env->incrementLoopIndices(); $loop = $__env->getLastLoop();';

        return "<?php {$initLoop} foreach(\$__currentLoopData as {$iteration}): {$iterateLoop} ?>";
    }

    /**
     * Compile the break statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileBreak($expression)
    {
        if ($expression) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $expression, $matches);

            return $matches ? '<?php break ' . max(1, $matches[1]) . '; ?>' : "<?php if{$expression} break; ?>";
        }

        return '<?php break; ?>';
    }

    /**
     * Compile the continue statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileContinue($expression)
    {
        if ($expression) {
            preg_match('/\(\s*(-?\d+)\s*\)$/', $expression, $matches);

            return $matches ? '<?php continue ' . max(1, $matches[1]) . '; ?>' : "<?php if{$expression} continue; ?>";
        }

        return '<?php continue; ?>';
    }

    /**
     * Compile the end-for statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndfor()
    {
        return '<?php endfor; ?>';
    }

    /**
     * Compile the end-for-each statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndforeach()
    {
        return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
    }

    /**
     * Compile the while statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileWhile($expression)
    {
        return "<?php while{$expression}: ?>";
    }

    /**
     * Compile the end-while statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndwhile()
    {
        return '<?php endwhile; ?>';
    }
}
