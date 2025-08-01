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

class WeChat extends Driver
{
    const TIMEOUT = 33;

    /**
     * @var string 企业 ID
     */
    protected $corpId;

    /**
     * @var string 企业微信应用的凭证密钥
     */
    protected $corpSecret;

    /**
     * @var integer 企业微信应用 ID
     */
    protected $agentId;

    /**
     * @var integer 企业微信用户id, 用于指定接收消息的用户，多个用户用“|”分割 https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token=ACCESS_TOKEN&fetch_child=FETCH_CHILD&department_id=1
     */
    protected $userId;

    protected function _initialize()
    {
        $this->corpId = $this->config['corp_id'];
        $this->corpSecret = $this->config['corp_secret'];
        $this->agentId = $this->config['agent_id'];
        $this->userId = $this->config['user_id']?:'@all';
    }

    /**
     * 获取 access_token
     *
     * @param bool $force
     *
     * @return mixed|string
     * @throws \Exception
     */
    protected function getAccessToken($force = false)
    {
        $key = 'wechat_' . $this->corpId . '_' . $this->corpId;
        if (!$force) {
            $accessToken = S($key);
            if($accessToken) {
                return $accessToken;
            }
        }
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
        $query = [
            'corpid' => $this->corpId,
            'corpsecret' => $this->corpSecret
        ];
        $resp = $this->curl($url, $query);
        if(!$resp) {
            E('error');
        }
        $resp = (array)json_decode($resp, true);

        if (isset($resp['errcode']) && $resp['errcode'] === 0 && isset($resp['access_token']) && isset($resp['expires_in'])) {
            S($key, $resp['access_token'], $resp['expires_in'] - 100);
            return $resp['access_token'];
        }

        throw new \Exception('获取企业微信 access_token 失败：' . ($resp['errmsg'] ?:'未知原因'));
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

        if ($subject !== '') {
            $content = $subject . "\n\n" . $content;
        }
        $footer = $this->getFooter();
        if($footer) {
            $content .= "\n\n" . $content;
        }
        try {
            $accessToken = $this->getAccessToken();

            $body = [
                'touser' => $this->userId, // 可直接通过此地址获取 userId，指定接收用户，多个用户用“|”分割 https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token=ACCESS_TOKEN&fetch_child=FETCH_CHILD&department_id=1
                'msgtype' => 'text', // 消息类型，text 类型支持 a 标签以及 \n 换行，基本满足需求。由于腾讯要求 markdown 语法必须使用 企业微信APP 才能查看，不想安装，故弃之
                'agentid' => $this->agentId, // 企业应用的 ID，整型，可在应用的设置页面查看
                'text' => [
                    'content' => $content, // 消息内容，最长不超过 2048 个字节，超过将截断
                ],
                'enable_duplicate_check' => 1,
                'duplicate_check_interval' => 60,
            ];

            return $this->doSend($accessToken, $body);
        } catch (\Exception $e) {
            E(sprintf('企业微信送信失败：<red>%s</red>', $e->getMessage()));
            return false;
        }
    }

    /**
     * 执行送信
     *
     * @param string $accessToken
     * @param array $body
     * @param int $numOfRetries
     *
     * @return bool
     * @throws \Exception
     */
    private function doSend($accessToken, $body, &$numOfRetries = 0)
    {
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/message/send';
        $query = [
            'access_token' => $accessToken
        ];
        $header = [
            'Content-Type' => 'application/json',
        ];
        $resp = $this->curl($url, $query, $body, $header);
        if(!$resp) {
            E('curl ' . $url . ' is error');
        }
        $resp = (array)json_decode($resp, true);

        if (!isset($resp['errcode']) || !isset($resp['errmsg'])) {
            throw new \Exception('企业微信接口未返回预期的数据响应，本次响应数据为：' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        }

        if ($resp['errcode'] === 0) {
            return true;
        } else if ($resp['errcode'] === 40014) { // invalid access_token
            $accessToken = $this->getAccessToken(true);

            if ($numOfRetries > 2) {
                throw new \Exception('检测到多次提示 access_token 失效，可能是未能正确获取 access_token，请介入调查：' . $resp['errmsg']);
            }

            $numOfRetries++;

            return $this->doSend($accessToken, $body, $numOfRetries);
        }

        throw new \Exception($resp['errmsg']);
    }
}
