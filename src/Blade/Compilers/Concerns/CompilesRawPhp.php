<?php
namespace Think\Blade\Compilers\Concerns;

trait CompilesRawPhp
{
    /**
     * Indicates if @php directive is enabled.
     *
     * @var bool
     */
    protected $phpDirectiveEnabled = false;

    /**
     * Compile the raw PHP statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compilePhp($expression)
    {
        // 安全修复：默认禁用 @php 指令
        if (!$this->phpDirectiveEnabled) {
            throw new \InvalidArgumentException(
                'The @php directive is disabled for security reasons. ' .
                'If you need to execute PHP code, please do it in your controller or service layer.'
            );
        }

        if ($expression) {
            return "<?php {$expression}; ?>";
        }

        return '@php';
    }

    /**
     * Compile the unset statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnset($expression)
    {
        // 同样禁用
        if (!$this->phpDirectiveEnabled) {
            throw new \InvalidArgumentException(
                'The @unset directive is disabled for security reasons.'
            );
        }

        return "<?php unset{$expression}; ?>";
    }

    /**
     * Enable or disable the @php directive.
     *
     * @param  bool  $enabled
     * @return $this
     */
    public function enablePhpDirective($enabled = true)
    {
        $this->phpDirectiveEnabled = $enabled;
        return $this;
    }
}
