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

    public function __construct()
    {
    }

    public function create(string $className, $content = '//这里开始您的业务代码', $onlyReturnContent = false)
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

    protected function getTemplate()
    {
        return __DIR__ . '/Command/stubs/migrate.stub';
    }
}
