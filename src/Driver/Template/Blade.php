<?php

namespace Think\Driver\Template;

use Think\Blade\Compilers\BladeCompiler;
use Think\Blade\Contracts\Filesystem\FileNotFoundException;
use Think\Blade\Engines\CompilerEngine;
use Think\Blade\Engines\EngineResolver;
use Think\Blade\Factory;
use Think\Blade\Filesystem\Filesystem;
use Think\Blade\Engines\FileEngine;
use Think\Blade\Engines\PhpEngine;
use Think\Blade\FileViewFinder;
use Think\Helper\Str;
use Think\Hook as Hook;
use Think\Storage as Storage;

class Blade
{
    // 模板引擎实例
    private $template;
    private $app;

    // 模板引擎参数
    protected $config = [
        // 模板目录名
        'view_dir_name'   => 'view',
        // 模板起始路径
        'view_path'       => '',
        // 模板后缀
        'template_suffix'     => 'blade.php',
        // 扩展的模板文件名
        'view_ext_suffix' => ['php', 'css', 'html'],
        // 模板文件名分隔符
        'view_depr'       => DIRECTORY_SEPARATOR,
        // 模板缓存路径，不设置则在runtime/temp下
        'cache_path'      => ''
    ];

    public function __construct(array $config = [])
    {
        include CORE_PATH . 'Blade/helpers.php';
        $this->config = array_merge($this->config, (array) $config);
    }

    /**
     * 渲染模板文件
     * @access public
     * @param string $template 模板文件
     * @param array  $data     模板变量
     * @return void
     */
    public function fetch(string $template, array $data = []): void
    {
        // 模板不存在 抛出异常
        if (!is_file($template)) {
            throw new FileNotFoundException('template not exists:' . $template);
        }

        if (empty($this->config['cache_path'])) {
            $this->config['cache_path']      = C('CACHE_PATH');
            $this->config['cache_path']      = C('CACHE_PATH');
            if (is_ssl()) {
                $this->config['cache_path'] .= 'HTTPS/';
                @mkdir($this->config['cache_path'], 0755, true);
            }
        }

        if (!is_dir($this->config['cache_path'])) {
            mkdir($this->config['cache_path'], 0755, true);
        }

        $file = new Filesystem;

        $compiler = new BladeCompiler($file, $this->config['cache_path']);

        $resolver = new EngineResolver;
        $resolver->register('file', function () {
            return new FileEngine;
        });
        $resolver->register('php', function () {
            return new PhpEngine;
        });
        $resolver->register('blade', function () use ($compiler) {
            return new CompilerEngine($compiler);
        });

        $finder = new FileViewFinder($file, [THEME_PATH . DIRECTORY_SEPARATOR], [$this->config['template_suffix']] + $this->config['view_ext_suffix']);

        $this->template = new Factory($resolver, $finder);

        echo (string) $this->template->file($template, $data);
    }
}
