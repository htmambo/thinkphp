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

namespace Think\Migration;

use InvalidArgumentException;
use Phinx\Util\Util;
use RuntimeException;

class Creator
{
    /**
     * @var string|null 自定义模板文件路径
     */
    protected ?string $customTemplate = null;

    public function __construct()
    {
    }

    /**
     * 设置自定义模板文件
     *
     * @param string $path 模板文件路径
     * @return $this
     * @throws InvalidArgumentException 当模板文件不存在时抛出
     */
    public function setTemplate(string $path): self
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(L('Template file "{$file}" does not exist', ['file' => $path]));
        }
        $this->customTemplate = $path;
        return $this;
    }

    /**
     * 获取自定义模板路径
     *
     * @return string|null
     */
    public function getCustomTemplate(): ?string
    {
        return $this->customTemplate;
    }

    /**
     * 创建迁移文件
     *
     * @param string $className 迁移类名
     * @param string $content 迁移内容
     * @param bool $onlyReturnContent 是否只返回内容而不创建文件
     * @param string|null $customTemplate 自定义模板路径（临时覆盖）
     * @return string 返回文件路径或内容
     */
    public function create(string $className, $content = '//这里开始您的业务代码', $onlyReturnContent = false, ?string $customTemplate = null)
    {
        $path = $this->ensureDirectory();

        if (!Util::isValidPhinxClassName($className)) {
            throw new InvalidArgumentException(L('The migration class name "{$name}" is invalid. Please use CamelCase format.', ['name' => $className]));
        }
        if(!$onlyReturnContent) {
            if (!Util::isUniqueMigrationClassName($className, $path)) {
                throw new InvalidArgumentException(L('The migration class name "{$name}}" already exists', ['name' => $className]));
            }
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new InvalidArgumentException(L('The file "{$file}" already exists', ['file' => $filePath]));
        }

        // Load the alternative template if it is defined.
        $contents = file_get_contents($this->getTemplate());

        // inject the class names appropriate to this migration
        $contents = strtr($contents, [
            '{CLASSNAME}' => $className,
            '{MIGRATION_CONTENT}' => $content
        ]);

        if($onlyReturnContent) {
            return $contents;
        }
        if (false === file_put_contents($filePath, $contents)) {
            throw new RuntimeException(L('The file "{$file}" could not be written to', ['file' => $path]));
        }

        return $filePath;
    }

    protected function ensureDirectory()
    {
        $path = ROOT_PATH . 'database' . DIRECTORY_SEPARATOR . 'migrations';

        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new InvalidArgumentException(L('directory "{$path}" does not exist', ['path' => $path]));
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(L('directory "{$path}" is not writable', ['path' => $path]));
        }

        return $path;
    }

    /**
     * 获取模板文件路径
     *
     * 优先使用自定义模板，其次使用临时指定的模板，最后使用默认模板
     *
     * @param string|null $temporaryTemplate 临时指定的模板路径
     * @return string 模板文件路径
     */
    protected function getTemplate(?string $temporaryTemplate = null): string
    {
        if ($temporaryTemplate !== null && file_exists($temporaryTemplate)) {
            return $temporaryTemplate;
        }
        if ($this->customTemplate !== null && file_exists($this->customTemplate)) {
            return $this->customTemplate;
        }
        return __DIR__ . '/Command/stubs/migrate.stub';
    }
}
