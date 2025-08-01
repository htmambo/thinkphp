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

use Think\Driver\Upload\Qiniu\QiniuStorage;

class Qiniu
{

    /**
     * 上传文件根目录
     *
     * @var string
     */
    private $rootPath = '';

    /**
     * 上传错误信息
     *
     * @var string
     */
    private $error  = '';
    private $config = [
        'secretKey' => '', //七牛服务器
        'accessKey' => '', //七牛用户
        'domain'    => '', //七牛密码
        'bucket'    => '', //空间名称
        'timeout'   => 300, //超时时间
    ];

    /**
     * 七牛存储类型
     *
     * @var Think\Driver\Upload\Qiniu\QiniuStorage;
     */
    public $qiniu;
    var    $urlpre = '';

    /**
     * 构造函数，用于设置上传根路径
     *
     * @param array $config FTP配置
     */
    public function __construct($config){
        $this->config = array_merge($this->config, $config);
        //$this->rootPath = '/' . $this->config['bucket'] . '/';
        //$this->config['rootPath'] = $this->rootPath;
        $this->urlpre = $this->config['urlpre'] = $this->config['domain'] . '/';
        $this->qiniu = new QiniuStorage($config);
    }

    /**
     * 使用 $this->name 获取配置
     *
     * @param string $name 配置名称
     *
     * @return mixed 配置值
     */
    public function __get($name){
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }
        $func = 'get' . $name;
        if (method_exists($this, $func)) {
            return call_user_func([$this, $func]);
        }
        $this->error = '指定的参数不存在！';
        return false;
    }

    /**
     * 利用__call方法实现一些需要七牛存储驱动来处理的方法
     *
     * @access public
     *
     * @param string $method 方法名称
     * @param array  $args   调用参数
     *
     * @return mixed
     */
    public function __call($method, $args){
        if (method_exists($this->qiniu, $method)) {
            return call_user_func([$this->qiniu, $method], $args[0]);
        } else {
            E(__CLASS__ . ':' . $method . L('_METHOD_NOT_EXIST_'));
            return;
        }
    }

    /**
     * 检测上传根目录(七牛上传时支持自动创建目录，直接返回)
     *
     * @param string $rootpath 根目录
     *
     * @return boolean true-检测通过，false-检测失败
     */
    public function checkRootPath($rootpath){
        $this->rootPath = trim($rootpath, './') . '/';
        return true;
    }

    /**
     * 检测上传目录(七牛上传时支持自动创建目录，直接返回)
     *
     * @param string $savepath 上传目录
     *
     * @return boolean          检测结果，true-通过，false-失败
     */
    public function checkSavePath($savepath){
        return true;
    }

    /**
     * 返回指定条件的文件列表
     *
     * @param string $prefix 查询条件
     * @param number $limit  最大返回数量
     */
    public function getFiles($prefix = '', $limit = 100){
        if (is_array($prefix) && isset($prefix[0])) {
            $prefix = $prefix[0];
        }
        if (is_array($prefix)) {
            $query = $prefix;
            if (!isset($query['limit'])) {
                $query['limit'] = $limit;
            }
        } else {
            $query = [
                'prefix' => $prefix,
                'limit'  => $limit,
            ];
        }
        return $this->qiniu->getList($query);
    }

    /**
     * 创建文件夹 (七牛上传时支持自动创建目录，直接返回)
     *
     * @param string $savepath 目录名称
     *
     * @return boolean          true-创建成功，false-创建失败
     */
    public function mkdir($savepath){
        return true;
    }

    /**
     * 生成缩略图
     *
     * @param string $src    源文件
     * @param int    $width  要截取的宽度
     * @param int    $height 要截取的高度
     * @param int    $type   截取方式，默认居中裁剪
     * @param bool   $delSrc 是否需要删除源文件，默认不删除
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

        $url = $this->qiniu->downlink(str_replace($this->rootPath, '', $src));

        $opt = '?imageMogr2/';
        // 缩放到指定尺寸
        $opt .= 'thumbnail/' . $width . 'x' . $height . '/quality/100';
        // 保存处理结果
        $eurl   = base64_encode($this->config['bucket'] . ':' . $target);
        $purl   = $url . $opt . "|saveas/" . $eurl;
        $purl   = str_replace('http://', '', $purl);
        $purl   = str_replace('%2F', '/', $purl);
        $sign   = $this->qiniu->sign($this->config['secretKey'], $this->config['accessKey'], $purl);
        $purl   .= '/sign/' . $sign;
        $result = $this->qiniu->request('http://' . $purl, 'GET');
        if (!$result) {
            echo $purl . '<br>';
            dump($this->qiniu);
            exit;
            // TODO 缩略图处理失败后怎么处理?
        }
        return $target;
        // 生成新的访问地址
        $url = $this->qiniu->downlink($target);
        return $url;
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
        $file['savename'] = strtolower($file['savename']);
        $file['name']     = $file['savepath'] . $file['savename'];
        $key              = $file['name']; // str_replace('/', '_', $file['name']);
        if (I('cutdata')) {
            $key = uniqid(); // 为了绕开七牛的缓存，一旦发现要对图片进行处理，这里就先扔给七牛一个临时文件
        }
        $upfile = [
            'name'     => 'file',
            'fileName' => $key,
            'fileBody' => file_get_contents($file['tmp_name']),
        ];
        $config = [];
        $result = $this->qiniu->upload($config, $upfile);
        $url    = $this->qiniu->downlink($key);
        // 是否需要裁剪、缩放等
        if ($data = I('cutdata')) {
            $data = json_decode(stripslashes($data));

            // 先获取图片信息
            $tmp     = getimagesizefromstring($upfile['fileBody']);
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

            $opt = '?imageMogr2/';
            // 裁剪指定位置的尺寸
            $opt .= 'crop/!' . $data->width . 'x' . $data->height . 'a' . $data->x . 'a' . $data->y . '/quality/100/';
            // 缩放到指定尺寸
            $opt .= 'thumbnail/' . $data->cutwidth . 'x' . $data->cutheight . '/quality/100';
            // 保存处理结果
            $eurl   = base64_encode($this->config['bucket'] . ':' . $file['name']);
            $purl   = $url . $opt . "|saveas/" . $eurl;
            $purl   = str_replace('http://', '', $purl);
            $purl   = str_replace('%2F', '/', $purl);
            $sign   = $this->qiniu->sign($this->config['secretKey'], $this->config['accessKey'], $purl);
            $purl   .= '/sign/' . $sign;
            $result = $this->qiniu->request('http://' . $purl, 'GET');
            $this->qiniu->del($key); // 删除掉临时文件
            if (!$result) {
                echo $purl;
                dump($this->qiniu);
                exit;
                // TODO 缩略图处理失败后怎么处理?
            }
            // 生成新的访问地址
            $url = $this->qiniu->downlink($file['name']);
        }
        $url         .= (strpos($url, '?') ? '&' : '?') . time();
        $file['url'] = str_replace([
                                       'http://http://',
                                       'https://http://',
                                       '%2F',
                                   ], [
                                       'http://',
                                       'https://',
                                       '/',
                                   ], $url);
        return false === $result ? false : true;
    }

    /**
     * 获取最后一次上传错误信息
     *
     * @return string 错误信息
     */
    public function getError(){
        return $this->qiniu->errorStr;
    }

}
