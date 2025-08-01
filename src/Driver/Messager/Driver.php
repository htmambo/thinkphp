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

namespace Think\Driver\Messager;

abstract class Driver
{
    protected $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->_initialize();
    }

    // 回调方法 初始化模型
    protected function _initialize()
    {
    }

    /**
     * 参数数据检查
     *
     * @param string $content
     * @param array $data
     *
     * @throws \Exception
     */
    public function check($content, $data)
    {
        if ($content === '' && empty($data)) {
            throw new \Exception('不允许发送空消息');
        }
    }

    protected function curl($url, $query = array(), $data = [], $header = [], $cookie = '')
    {
        $ch = curl_init();
        if ($query) {
            if (strpos($url, '?')) {
                $url .= '&';
            } else {
                $url .= '?';
            }
            $url .= http_build_query($query);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (stripos($url, 'https://') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($cookie) {
            if (is_array($cookie)) {
                $tmp = $cookie;
                $cookie = '';
                foreach ($tmp as $k => $v) {
                    if(is_numeric($k) && strpos($v, '=')) {
                        $cookie .= trim(trim($v), ';');
                    } else {
                        $cookie .= $k . '=' . $v;
                    }
                    $cookie .=  '; ';
                }
            }
            $header[] = 'Cookie: ' . $cookie;
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if(is_array($data)) {
                if(isset($data['form_params'])) {
                    $data = http_build_query($data['form_params'], '', '&');
                } else {
                    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * 送信
     *
     * @param string $content
     * @param string $subject
     * @param array $data
     * @param string|null $recipient
     * @param ...$params
     *
     * @return bool
     */
    public function send($content, $subject = '', $data = [], $recipient = null, ...$params)
    {
    }

    /**
     * 获取页脚
     *
     * @return string
     */
    protected function getFooter()
    {
        $footer = '';
        // 这里可以设置一些公用的信息
        return $footer;
    }
}
