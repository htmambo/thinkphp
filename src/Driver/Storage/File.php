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

namespace Think\Driver\Storage;

use Think\Storage;

// 本地文件写入存储类
class File extends Storage
{

    private $contents = array();

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
    }

    /**
     * 读取指定文件夹中的文件列表
     *
     * @param string $path 系统路径
     * @param string $filter 过滤条件
     * @param boolean $recurse 是否搜索子目录
     * @param boolean $fullpath 是否包含完整路径
     * @param array $excludeDirs 排除的目录
     * @param array $excludeFiles 排除的文件
     *
     * @return array
     */
    public static function listFiles($path, $filter = '.', $recurse = false, $fullpath = false, $excludeDirs = [], $excludeFiles = []) {
        $arr  = array();
        if (!is_dir($path)) {
            return $arr;
        }

        // read the source directory
        $handle = opendir($path);
        $path .= DIRECTORY_SEPARATOR;
        while (($file   = readdir($handle)) !== false) {
            if($file === '.' || $file === '..' || in_array($file, $excludeFiles)) {
                continue;
            }
            $fullname   = $path . $file;
            $isDir = is_dir($fullname);
            if ($isDir) {
                if(in_array($file, $excludeDirs)) {
                    continue;
                }
                if ($recurse) {
                    $arr2 = self::listFiles($fullname, $filter, $recurse, $fullpath, $excludeDirs, $excludeFiles);
                    $arr  = array_merge($arr, $arr2);
                }
            } else {
                if (preg_match("/$filter/", $file)) {
                    if ($fullpath) {
                        $arr[] = realpath($fullname);
                    } else {
                        $arr[$file] = $file;
                    }
                }
            }
        }
        closedir($handle);
        asort($arr);
        return $arr;
    }

    /**
     * 文件内容读取
     * @access public
     * @param string $filename  文件名
     * @return string
     */
    public function read($filename, $type = '')
    {
        return $this->get($filename, 'content', $type);
    }

    /**
     * 文件写入
     * @access public
     * @param string $filename  文件名
     * @param string $content  文件内容
     * @return boolean
     */
    public function put($filename, $content, $type = '')
    {
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            // 安全修复：使用更严格的权限 0755 而非 0777
            // 0755 = 所有者:rwx, 组:r-x, 其他人:r-x
            mkdir($dir, 0755, true);
        }
        if (false === file_put_contents($filename, $content)) {
            E(L('_STORAGE_WRITE_ERROR_') . ':' . $filename);
            // throw new ErrorException(403, L('_STORAGE_WRITE_ERROR_') . ':' . str_replace([ROOT_PATH, CORE_PATH], ['ROOT_PATH/', 'CORE_PATH/'], $filename));
        } else {
            $this->contents[$filename] = $content;
            return true;
        }
    }

    /**
     * 文件追加写入
     * @access public
     * @param string $filename  文件名
     * @param string $content  追加的文件内容
     * @return boolean
     */
    public function append($filename, $content, $type = '')
    {
        if (is_file($filename)) {
            $content = $this->read($filename, $type) . $content;
        }
        return $this->put($filename, $content, $type);
    }

    /**
     * 加载文件
     * @access public
     * @param string $filename  文件名
     * @param array $vars  传入变量
     * @return void
     */
    public function load($_filename, $vars = null)
    {
        // 安全修复：验证文件路径，防止路径遍历和任意文件包含
        $realPath = realpath($_filename);

        // 1. 验证文件是否存在
        if ($realPath === false) {
            error_log("Security Warning: File not found or access denied: {$_filename}");
            return;
        }

        // 2. 获取允许的基础路径
        $allowedPaths = [
            realpath(APP_PATH) ?: APP_PATH,
            realpath(ROOT_PATH) ?: ROOT_PATH,
            realpath(C('CACHE_PATH')) ?: C('CACHE_PATH'),
            realpath(C('TEMP_PATH')) ?: C('TEMP_PATH'),
        ];

        // 3. 验证文件在允许的路径内
        $isAllowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if ($allowedPath && strpos($realPath, $allowedPath) === 0) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            error_log("Security Warning: Attempted to load file outside allowed directories: {$_filename} (resolved: {$realPath})");
            return;
        }

        // 4. 验证文件扩展名（可选，根据需要启用）
        // $allowedExtensions = ['.php', '.html', '.htm'];
        // $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        // if (!in_array('.' . $extension, $allowedExtensions)) {
        //     error_log("Security Warning: Disallowed file extension: {$extension}");
        //     return;
        // }

        if (!is_null($vars) && is_array($vars)) {
            extract($vars, EXTR_SKIP);
        }

        include $realPath;
    }

    /**
     * 文件是否存在
     * @access public
     * @param string $filename  文件名
     * @return boolean
     */
    public function has($filename, $type = '')
    {
        return is_file($filename);
    }

    /**
     * 文件删除
     * @access public
     * @param string $filename  文件名
     * @return boolean
     */
    public function unlink($filename, $type = '')
    {
        unset($this->contents[$filename]);
        return is_file($filename) ? unlink($filename) : false;
    }

    /**
     * 读取文件信息
     * @access public
     * @param string $filename  文件名
     * @param string $name  信息名 mtime或者content
     * @return boolean
     */
    public function get($filename, $name, $type = '')
    {
        if (!isset($this->contents[$filename])) {
            if (!is_file($filename)) {
                return false;
            }

            $this->contents[$filename] = file_get_contents($filename);
        }
        $content = $this->contents[$filename];
        $info    = array(
            'mtime'   => filemtime($filename),
            'content' => $content,
        );
        return $info[$name];
    }
}
