<?php
declare(strict_types=1);

namespace Think\Blade\Compilers\Concerns;

trait CompilesStyles
{
    /**
     * Compile the conditional style statement into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileStyle($expression)
    {
        $expression = is_null($expression) ? '([])' : $expression;

        return "style=\"<?php echo \Think\Blade\Support\Arr::toCssStyles{$expression} ?>\"";
    }
}
