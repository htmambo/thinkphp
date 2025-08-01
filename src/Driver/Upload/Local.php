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

namespace Think\Driver\Upload;

class Local
{

    private $config = [
        'rootPath' => '',
        'urlpre'   => '',
    ];

    /**
     * 本地上传错误信息
     *
     * @var string
     */
    private $error = ''; //上传错误信息

    /**
     * 构造函数，用于设置上传根路径
     *
     * @param array $config
     */
    public function __construct($config = []){
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 使用 $this->name 获取配置
     *
     * @param string $name 配置名称
     *
     * @return multitype    配置值
     */
    public function __get($name){
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }
        if (method_exists($this, 'get' . $name)) {
            $func = 'get' . $name;
            return call_user_func([$this, $func]);
        }
        $this->error = '指定的参数不存在！';
        return false;
    }

    /**
     * 检测上传根目录
     *
     * @param string $rootpath 根目录
     *
     * @return boolean true-检测通过，false-检测失败
     */
    public function checkRootPath($rootpath){
        if (!(is_dir($rootpath) && is_writable($rootpath))) {
            $this->error = '上传根目录不存在！请尝试手动创建:' . $rootpath;
            return false;
        }
        $this->config['rootPath'] = $rootpath;
        return true;
    }

    /**
     * 检测上传目录
     *
     * @param string $savepath 上传目录
     *
     * @return boolean          检测结果，true-通过，false-失败
     */
    public function checkSavePath($savepath){
        /* 检测并创建目录 */
        if (!$this->mkdir($savepath)) {
            return false;
        } else {
            /* 检测目录是否可写 */
            if (!is_writable($this->config['rootPath'] . $savepath)) {
                $this->error = '上传目录 ' . $savepath . ' 不可写！';
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * 生成缩略图
     *
     * @param string $src    源文件
     * @param int    $width  要截取的宽度
     * @param int    $height 要截取的高度
     * @param int    $type   截取方式，默认居中裁剪
     * @param bool   $delSrc 是否需要删除源文件，默认不删除
     *
     * @return bool|mixed|string
     */
    public function makeThumb($src, $width = 300, $height = 300, $type = \Think\Image::IMAGE_THUMB_CENTER, $delSrc = false){
        if (is_array($src)) {
            $param = [
                '0' => '',
                '1' => 300,
                '2' => 300,
                '3' => \Think\Image::IMAGE_THUMB_CENTER,
                '4' => false,
            ];
            foreach ($param as $k => $v) {
                if (isset($src[$k])) {
                    $param[$k] = $src[$k];
                }
            }
            [$src, $width, $height, $type, $delSrc] = $param;
        }
        if ($delSrc) {
            $target = $src;
        } else {
            $target = $src . '.thumb';
        }
        // 先获取图片信息
        $tmp = getimagesize($src);

        if ($tmp[0]) {
            //是图片,开始处理缩略以及裁剪
            $img = new \Think\Image();
            $img->open($src);

            //缩放到指定尺寸
            $img->thumb($width, $height, $type);
            $delSrc && @unlink($src);
            $result = $img->save($target);
            if ($result) {
                return $target;
            } else {
                return false;
            }
        } else {
            exit('no image');
        }
    }

    public function del($file, $fullpath = false){
        if (is_array($file)) {
            [$file, $fullpath] = $file;
        }
        if (!$fullpath) {
            $file = $this->config['rootPath'] . '/' . $file;
        }
        @unlink($file);
    }

    /**
     * 保存指定文件
     *
     * @param array   $file    保存的文件信息
     * @param boolean $replace 同名文件是否覆盖
     *
     * @return boolean          保存状态，true-成功，false-失败
     */
    public function save(&$file, $replace = true){
        if (I('cutdata')) {
            $filename = $this->config['rootPath'] . '/' . uniqid();
        } else {
            $filename = $this->config['rootPath'] . $file['savepath'] . $file['savename'];
        }

        /* 不覆盖同名文件 */
        if (!$replace && is_file($filename)) {
            $this->error = '存在同名文件' . $file['savename'];
            return false;
        }

        /* 移动文件 */
        if (!move_uploaded_file($file['tmp_name'], $filename)) {
            $this->error = '文件上传保存错误！';
            return false;
        }
        //是否需要缩放裁剪
        if (I('cutdata')) {
            $data     = I('cutdata');
            $data     = json_decode(stripslashes($data));
            $src      = $filename;
            $filename = $this->config['rootPath'] . $file['savepath'] . $file['savename'];
            // 先获取图片信息
            $tmp = getimagesize($src);

            if ($tmp[0]) {
                //是图片,开始处理缩略以及裁剪
                $img = new \Think\Image();
                $img->open($src);

                $imgInfo = [
                    'width'  => $tmp[0],
                    'height' => $tmp[1],
                ];
                $iw      = $imgInfo['width'];
                $ih      = $imgInfo['height'];
                // 计算缩放比例
                $ra1 = $data->ow / $iw;
                $ra2 = $data->oh / $ih;
                // 计算实际截图、缩放的尺寸
                $data->width  = max(0, intval($data->width / $ra1));
                $data->height = max(0, intval($data->height / $ra2));
                $data->x      = max(0, intval($data->x / $ra1));
                $data->y      = max(0, intval($data->y / $ra2));
                // $data->cutwidth = $data->cutwidth/$ra1;
                // $data->cutheight = $data->cutheight/$ra1;

                //裁剪指定位置的尺寸
                $img->crop($data->width, $data->height, $data->x, $data->y);
                //缩放到指定尺寸
                $img->thumb($data->cutwidth, $data->cutheight, \Think\Image::IMAGE_THUMB_CENTER);
                @unlink($src);
                $img->save($filename);
            } else {
                exit('no image');
            }
        }
        $url         = str_replace($this->config['rootPath'], $this->config['urlpre'], $filename);
        $url         .= (strpos($url, '?') ? '&' : '?') . time();
        $file['url'] = str_replace(['http://http://', 'https://http://'], ['http://', 'https://'], $url);

        return true;
    }

    /**
     * 创建目录
     *
     * @param string $savepath 要创建的目录
     *
     * @return boolean          创建状态，true-成功，false-失败
     */
    public function mkdir($savepath){
        $dir = $this->config['rootPath'] . $savepath;
        if (is_dir($dir)) {
            return true;
        }

        $oldmask = umask(0);
        if (mkdir($dir, 0777, true)) {
            umask($oldmask);
            return true;
        } else {
            umask($oldmask);
            $this->error = "目录 {$savepath} 创建失败！";
            return false;
        }
    }

    /**
     * 获取最后一次上传错误信息
     *
     * @return string 错误信息
     */
    public function getError(){
        return $this->error;
    }

}
