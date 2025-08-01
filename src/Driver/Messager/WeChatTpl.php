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

class WeChatTpl extends Driver
{
    /**
     * @var string 微信APPID
     */
    protected $appid;
    /**
     * @var string 微信密钥
     */
    protected $secrect;

    const TIMEOUT = 33;

    /**
     * @var string 缓存 access_token 的文件
     */
    protected $accessTokenFile;

    protected function _initialize()
    {
        $this->appid = $this->config['appid'];
        $this->secrect = $this->config['secret'];
    }

    /**
     * 获取 access_token
     *
     * @param bool $force
     * @return mixed 获取到的token
     * @throws \Think\Exception
     */
    public function getToken($force = false)
    {
        $key   = 'wechat_token_' . $this->appid;
        if(!$force) {
            $token = S($key);
            if($token) {
                return $token;
            }
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/token';
        $query = [
            'grant_type'=>'client_credential',
            'appid' => $this->appid,
            'secret' => $this->secrect
        ];
        $data = $this->curl($url, $query);
        $data         = json_decode(stripslashes($data));
        $data         = json_decode(json_encode($data), true);
        if(!isset($data['access_token'])) {
            E('获取AccessToken时出错：' . $data['errmsg']);
        }
        $token = $data['access_token'];
        S($key, $token, $data['expires_in'] - 100);
        return $token;
    }

    /**
     * 送信
     *
     * 由于腾讯要求 markdown 语法消息必须使用 企业微信 APP 才能查看，然而我并不想单独安装 企业微信 APP，故本方法不使用 markdown 语法，
     * 而是直接使用纯文本 text 类型，纯文本类型里腾讯额外支持 a 标签，所以基本满足需求
     *
     * 参考：
     * https://work.weixin.qq.com/api/doc/90000/90135/91039
     * https://work.weixin.qq.com/api/doc/90000/90135/90236#%E6%96%87%E6%9C%AC%E6%B6%88%E6%81%AF
     *
     * @param string $content
     * @param string $subject
     * @param array $data
     * @param string|null $recipient
     * @param mixed ...$params
     *
     * @return bool
     * @throws \Exception
     */
    public function send($content, $subject = '', $data = [], $recipient = null, ...$params)
    {
        $this->check($content, $data);
        if(!isset($data['url'])) {
            $data['url'] = '';
        }
        if(!isset($data['color'])) {
            $data['color'] = '#004081';
        }

        try {
            $content = str_replace('\\n', "\n", $content);
            $set = [
                ':subject' => $subject,
                ':content' => $content,
                ':date' => date('Y-m-d'),
                ':time' => date('H:i:s'),
                ':datetime' => date('Y-m-d H:i:s')
            ];
            $set = array_merge($set, $data);
            $data['content'] = [];
            foreach($this->config['tplset'] as $k => $v) {
                if(isset($set[$v])) {
                    $v = $set[$v];
                } else if (isset($set[$k])) {
                    $v = $set[$k];
                }
                $data['content'][$k] = ['value' => $v];
            }
            $users = $this->config['users'];
            if(is_string($users)) {
                $users = explode('|', $users);
            }
            foreach($users as $openid) {
                $this->doSend($openid, $data);
            }
        } catch (\Exception $e) {
            E(sprintf('微信模板消息送信失败：<red>%s</red>', $e->getMessage()));
            return false;
        }
    }

    public function doSend($touser, $data, $topcolor = '#7B68EE')
    {
        $url = $data['url'];
        if(isset($data['color'])) {
            $topcolor = $data['color'];
        }
        $data = $data['content'];
        foreach ($data as &$row) {
            if (empty($row['color'])) {
                $row['color'] = $topcolor;
            }
        }
        $template      = [
            'touser' => $touser,
            'template_id' => $this->config['tplid'],
            'url' => $url,
            'topcolor' => $topcolor,
            'data' => $data
        ];
        $url           = "https://api.weixin.qq.com/cgi-bin/message/template/send";
        $query = ['access_token' => $this->getToken()];
        return $this->curl($url, $query, $template);
    }
}
