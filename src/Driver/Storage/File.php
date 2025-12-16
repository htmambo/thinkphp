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
            mkdir($dir, 0777, true);
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
        if (!is_null($vars) && is_array($vars)) {
            extract($vars, EXTR_SKIP);
        }
        include $_filename;
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
