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

namespace Think\Driver\Upload\Qiniu;

class QiniuStorage
{
    public $errorNo        = 0;
    public $QINIU_RSF_HOST = 'http://rsf.qbox.me';
    public $QINIU_RS_HOST  = 'http://rs.qbox.me';
    public $QINIU_UP_HOST  = 'http://up.qiniu.com';
    public $timeout        = '';

    public function __construct($config){
        $this->sk      = $config['secretKey'];
        $this->ak      = $config['accessKey'];
        $this->domain  = $config['domain'];
        $this->bucket  = $config['bucket'];
        $this->timeout = isset($config['timeout']) ? $config['timeout'] : 3600;
    }

    public static function sign($sk, $ak, $data){
        $sign = hash_hmac('sha1', $data, $sk, true);
        return $ak . ':' . self::qiniuEncode($sign);
    }

    public static function signWithData($sk, $ak, $data){
        $data = self::qiniuEncode($data);
        return self::sign($sk, $ak, $data) . ':' . $data;
    }

    public function accessToken($url, $body = ''){
        $parsed_url = parse_url($url);
        $path       = $parsed_url['path'];
        $access     = $path;
        if (isset($parsed_url['query'])) {
            $access .= "?" . $parsed_url['query'];
        }
        $access .= "\n";

        if ($body) {
            $access .= $body;
        }
        return self::sign($this->sk, $this->ak, $access);
    }

    public function getErrorInfo($status = 0){
        if (!$status) {
            $status = $this->errorNo;
        }
        switch ($status) {
            case 200:
                $msg = '操作执行成功。';
                break;
            case 298:
                $msg = '部分操作执行成功。';
                break;
            case 400:
                $msg = '请求报文格式错误。（包括上传时，上传表单格式错误；URL触发图片处理时，处理参数错误；资源管理操作或触发持久化处理（pfop）操作请求格式错误）';
                break;
            case 401:
                $msg = '认证授权失败。（包括密钥信息不正确；数字签名错误；授权已超时）';
                break;
            case 404:
                $msg = '资源不存在。（包括空间资源不存在；镜像源资源不存在）';
                break;
            case 405:
                $msg = '请求方式错误。（主要指非预期的请求方式）';
                break;
            case 406:
                $msg = '上传的数据 CRC32 校验错误。';
                break;
            case 419:
                $msg = '用户账号被冻结。';
                break;
            case 478:
                $msg = '镜像回源失败。（主要指镜像源服务器出现异常）';
                break;
            case 503:
                $msg = '服务端不可用。';
                break;
            case 504:
                $msg = '服务端操作超时。';
                break;
            case 579:
                $msg = '上传成功但是回调失败。（包括业务服务器异常；七牛服务器异常；服务器间网络异常）';
                break;
            case 599:
                $msg = '服务端操作失败。';
                break;
            case 608:
                $msg = '资源内容被修改。';
                break;
            case 612:
                $msg = '指定资源不存在或已被删除。';
                break;
            case 614:
                $msg = '目标资源已存在。';
                break;
            case 630:
                $msg = '已创建的空间数量达到上限，无法创建新空间。';
                break;
            case 631:
                $msg = '指定空间不存在。';
                break;
            case 640:
                $msg = '调用列举资源（list）接口时，指定非法的marker参数。';
                break;
            case 701:
                $msg = '在断点续上传过程中，后续上传接收地址不正确或ctx信息已过期。';
                break;
            default:
                $msg = '未知错误，请通知管理员，错误码为：' . $status;
                break;
        }
        return $msg;
    }

    public function UploadToken($sk, $ak, $param){
        $param['deadline'] = 0 == $param['Expires'] ? 3600 : $param['Expires'];
        $param['deadline'] += time();
        $data              = ['scope' => $this->bucket, 'deadline' => $param['deadline']];
        if (!empty($param['CallbackUrl'])) {
            $data['callbackUrl'] = $param['CallbackUrl'];
        }
        if (!empty($param['CallbackBody'])) {
            $data['callbackBody'] = $param['CallbackBody'];
        }
        if (!empty($param['ReturnUrl'])) {
            $data['returnUrl'] = $param['ReturnUrl'];
        }
        if (!empty($param['ReturnBody'])) {
            $data['returnBody'] = $param['ReturnBody'];
        }
        if (!empty($param['AsyncOps'])) {
            $data['asyncOps'] = $param['AsyncOps'];
        }
        if (!empty($param['EndUser'])) {
            $data['endUser'] = $param['EndUser'];
        }
        $data = json_encode($data);
        return self::SignWithData($sk, $ak, $data);
    }

    public function upload($config, $file){
        if (is_array($config[0])) {
            [$config, $file] = $config;
        }
        $uploadToken = $this->UploadToken($this->sk, $this->ak, $config);

        $url          = "{$this->QINIU_UP_HOST}";
        $mimeBoundary = md5(microtime());
        $header       = ['Content-Type' => 'multipart/form-data;boundary=' . $mimeBoundary];
        $data         = [];

        $fields = [
            'token' => $uploadToken,
            'key'   => $config['saveName'] ?: $file['fileName'],
        ];

        if (is_array($config['custom_fields']) && [] !== $config['custom_fields']) {
            $fields = array_merge($fields, $config['custom_fields']);
        }

        foreach ($fields as $name => $val) {
            array_push($data, '--' . $mimeBoundary);
            array_push($data, "Content-Disposition: form-data; name=\"$name\"");
            array_push($data, '');
            array_push($data, $val);
        }

        //文件
        array_push($data, '--' . $mimeBoundary);
        $name     = $file['name'];
        $fileName = $file['fileName'];
        $fileBody = $file['fileBody'];
        $fileName = self::qiniuEscapequotes($fileName);
        array_push($data, "Content-Disposition: form-data; name=\"$name\"; filename=\"$fileName\"");
        array_push($data, 'Content-Type: application/octet-stream');
        array_push($data, '');
        array_push($data, $fileBody);

        array_push($data, '--' . $mimeBoundary . '--');
        array_push($data, '');

        $body     = implode("\r\n", $data);
        $response = $this->request($url, 'POST', $header, $body);
        return $response;
    }

    public function dealWithType($key, $type){
        $param = $this->buildUrlParam();
        $url   = '';

        switch ($type) {
            case 'img':
                $url = $this->downLink($key);
                if ($param['imageInfo']) {
                    $url .= '?imageInfo';
                } else {
                    if ($param['exif']) {
                        $url .= '?exif';
                    } else {
                        if ($param['imageView']) {
                            $url .= '?imageView/' . $param['mode'];
                            if ($param['w']) {
                                $url .= "/w/{$param['w']}";
                            }

                            if ($param['h']) {
                                $url .= "/h/{$param['h']}";
                            }

                            if ($param['q']) {
                                $url .= "/q/{$param['q']}";
                            }

                            if ($param['format']) {
                                $url .= "/format/{$param['format']}";
                            }

                        }
                    }
                }
                break;
            case 'video': //TODO 视频处理
            case 'doc':
                $url = $this->downLink($key);
                $url .= '?md2html';
                if (isset($param['mode'])) {
                    $url .= '/' . (int)$param['mode'];
                }

                if ($param['cssurl']) {
                    $url .= '/' . self::qiniuEncode($param['cssurl']);
                }

                break;

        }
        return $url;
    }

    public function buildUrlParam(){
        return $_REQUEST;
    }

    //获取某个路径下的文件列表
    public function getList($query = [], $path = ''){
        $query       = array_merge(['bucket' => $this->bucket], $query);
        $url         = "{$this->QINIU_RSF_HOST}/list?" . http_build_query($query);
        $accessToken = $this->accessToken($url);
        $response    = $this->request($url, 'POST', ['Authorization' => "QBox $accessToken"]);
        return $response;
    }

    //获取某个文件的信息
    public function info($key){
        $key         = trim($key);
        $url         = "{$this->QINIU_RS_HOST}/stat/" . self::qiniuEncode("{$this->bucket}:{$key}");
        $accessToken = $this->accessToken($url);
        $response    = $this->request($url, 'POST', [
            'Authorization' => "QBox $accessToken",
        ]);
        return $response;
    }

    //获取文件下载资源链接
    public function downLink($key){
        $key = urlencode($key);
        $key = self::qiniuEscapequotes($key);
        $url = "http://{$this->domain}/{$key}";
        return $url;
    }

    //重命名单个文件
    public function rename($file, $new_file){
        $key = trim($file);
        $url = "{$this->QINIU_RS_HOST}/move/" . self::qiniuEncode("{$this->bucket}:{$key}") . '/' . self::qiniuEncode("{$this->bucket}:{$new_file}");
        trace($url);
        $accessToken = $this->accessToken($url);
        $response    = $this->request($url, 'POST', ['Authorization' => "QBox $accessToken"]);
        return $response;
    }

    //删除单个文件
    public function del($file){
        $key         = trim($file);
        $url         = "{$this->QINIU_RS_HOST}/delete/" . self::qiniuEncode("{$this->bucket}:{$key}");
        $accessToken = $this->accessToken($url);
        $response    = $this->request($url, 'POST', ['Authorization' => "QBox $accessToken"]);
        if (!$response) {
            /**
             * 删除文件时的返回码
             * 200    删除成功
             * 400    请求报文格式错误
             * 401    管理凭证无效
             * 599    服务端操作失败
             * 如遇此错误，请将完整错误信息（包括所有HTTP响应头部）通过邮件发送给我们
             * 612    待删除资源不存在
             */
            if (in_array($this->errorNo, [400, 401, 599])) {
                return false;
            }
        }
        return true;
    }

    //批量删除文件
    public function delBatch($files){
        $url = $this->QINIU_RS_HOST . '/batch';
        $ops = [];
        foreach ($files as $file) {
            $ops[] = "/delete/" . self::qiniuEncode("{$this->bucket}:{$file}");
        }
        $params = 'op=' . implode('&op=', $ops);
        $url    .= '?' . $params;
        trace($url);
        $accessToken = $this->accessToken($url);
        $response    = $this->request($url, 'POST', ['Authorization' => "QBox $accessToken"]);
        if (!$response) {
            /**
             * 删除文件时的返回码
             * 200    删除成功
             * 400    请求报文格式错误
             * 401    管理凭证无效
             * 599    服务端操作失败
             * 如遇此错误，请将完整错误信息（包括所有HTTP响应头部）通过邮件发送给我们
             * 612    待删除资源不存在
             */
            if (in_array($this->errorNo, [400, 401, 599])) {
                return false;
            }
        }
        return true;
    }

    public static function qiniuEncode($str){
        // URLSafeBase64Encode
        $find    = ['+', '/'];
        $replace = ['-', '_'];
        return str_replace($find, $replace, base64_encode($str));
    }

    public static function qiniuEscapequotes($str){
        $find    = ["\\", "\""];
        $replace = ["\\\\", "\\\""];
        return str_replace($find, $replace, $str);
    }

    /**
     * 请求云服务器
     *
     * @param string   $path    请求的PATH
     * @param string   $method  请求方法
     * @param array    $headers 请求header
     * @param resource $body    上传文件资源
     *
     * @return mixed
     */
    public function request($path, $method, $headers = NULL, $body = NULL){
        $ch = curl_init($path);

        $_headers = ['Expect:'];
        if (!is_null($headers) && is_array($headers)) {
            foreach ($headers as $k => $v) {
                array_push($_headers, "{$k}: {$v}");
            }
        }

        $length = 0;
        $date   = gmdate('D, d M Y H:i:s \G\M\T');

        if (!is_null($body)) {
            if (is_resource($body)) {
                fseek($body, 0, SEEK_END);
                $length = ftell($body);
                fseek($body, 0);

                array_push($_headers, "Content-Length: {$length}");
                curl_setopt($ch, CURLOPT_INFILE, $body);
                curl_setopt($ch, CURLOPT_INFILESIZE, $length);
            } else {
                $length = @strlen($body);
                array_push($_headers, "Content-Length: {$length}");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        } else {
            array_push($_headers, "Content-Length: {$length}");
        }

        // array_push($_headers, 'Authorization: ' . $this->sign($method, $uri, $date, $length));
        array_push($_headers, "Date: {$date}");

        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ('PUT' == $method || 'POST' == $method) {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_POST, 0);
        }

        if ('HEAD' == $method) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        [$header, $body] = explode("\r\n\r\n", $response, 2);
        if (200 == $status) {
            if ('GET' == $method) {
                return $body;
            } else {
                return $this->response($response);
            }
        } else {
            $this->errorNo = $status;
            $this->error($header, $body);
            return false;
        }
    }

    /**
     * 获取响应数据
     *
     * @param string $text 响应头字符串
     *
     * @return array        响应数据列表
     */
    private function response($text){
        $headers = explode(PHP_EOL, $text);
        $items   = [];
        foreach ($headers as $header) {
            $header = trim($header);
            if (strpos($header, '{') !== false) {
                $items = json_decode($header, 1);
                break;
            }
        }
        return $items;
    }

    /**
     * 获取请求错误信息
     *
     * @param string $header 请求返回头信息
     */
    private function error($header, $body){
        [$status, $stash] = explode("\r\n", $header, 2);
        [$v, $code, $message] = explode(" ", $status, 3);
        $message        = is_null($message) ? 'File Not Found' : "[{$status}]:{$message}]";
        $this->error    = $message;
        $this->errorStr = json_decode($body, 1);
        $this->errorStr = $this->errorStr['error'];
    }
}
